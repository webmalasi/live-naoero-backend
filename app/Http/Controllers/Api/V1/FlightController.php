<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Auth\RequestMeta;
use App\Services\Flights\FlightCalendar;
use App\Services\Flights\FlightConfigService;
use App\Services\Flights\FlightSearch;
use App\Services\Logging\ServerLog;
use App\Services\Reference\ReferenceService;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use libphonenumber\PhoneNumberUtil;
use Throwable;

class FlightController extends ApiController
{
    public function __construct(
        private readonly FlightConfigService $flights,
        private readonly ReferenceService $reference,
        private readonly ZenithClient $zenith,
        private readonly ServerLog $log,
    ) {}

    /** Everything the search form needs: routes, flight dates, passenger types, currencies. */
    public function config(): JsonResponse
    {
        try {
            return $this->json($this->flights->config());
        } catch (Throwable $e) {
            $this->log->error('flight-config', $e->getMessage());
            throw new ApiException(503, 'unavailable', 'Flight information is temporarily unavailable. Please try again shortly.');
        }
    }

    /** The airline's country list (ISO 3166 alpha-2), for pickers. */
    public function countries(): JsonResponse
    {
        try {
            return $this->json(['countries' => $this->reference->countries()], 200, ['Cache-Control' => 'public, max-age=86400']);
        } catch (Throwable $e) {
            $this->log->error('countries', $e->getMessage());
            throw new ApiException(503, 'unavailable', 'Country list is temporarily unavailable.');
        }
    }

    /**
     * Every country with its flag and dialling code, for phone pickers:
     * Zenith's country names (it has no dialling codes) with libphonenumber's
     * codes. If Zenith is unreachable the standard names are used, so phone
     * fields always work.
     */
    public function phoneCodes(): JsonResponse
    {
        try {
            $names = array_column($this->reference->countries(), 'name', 'code');
        } catch (Throwable $e) {
            $this->log->warn('countries', 'phone codes without Zenith names', ['error' => $e->getMessage()]);
            $names = [];
            foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $region) {
                $names[$region] = \Locale::getDisplayRegion("-{$region}", 'en') ?: $region;
            }
        }

        return $this->json(['countries' => Phones::countries($names), 'default' => Phones::FIRST[0]], 200, ['Cache-Control' => 'public, max-age=86400']);
    }

    /** A month of a route for the date picker (Calendar): available, waitlisted or full per day. */
    public function calendar(Request $request, FlightCalendar $calendar): JsonResponse
    {
        $input = $request->validate([
            'origin' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/'],
            'destination' => ['required', 'string', 'regex:/^[A-Za-z]{3}$/', 'different:origin'],
            'month' => ['required', 'date_format:Y-m'],
        ], [
            'regex' => 'Choose an airport.',
            'different' => 'Choose a destination different from the departure airport.',
            'required' => ':attribute is required.',
            'date_format' => 'Use the format YYYY-MM.',
        ]);

        return $this->json($calendar->month(strtoupper($input['origin']), strtoupper($input['destination']), $input['month']), 200, ['Cache-Control' => 'private, max-age=60']);
    }

    /** The names of Zenith's value-code lists (GetValueCodeList), for reference screens and support. */
    public function valueCodeLists(): JsonResponse
    {
        try {
            $raw = CachedValue::get('zenith:GetValueCodeList', 24 * 3600, fn () => $this->zenith->call('GetValueCodeList'));
        } catch (Throwable $e) {
            $this->log->error('value-codes', $e->getMessage());
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }
        $lists = array_values(array_unique(array_filter(array_map('strval', Js::list($raw['CodeList'] ?? null)))));
        sort($lists, SORT_STRING);

        return $this->json(['lists' => $lists], 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    /** One value-code list (GetValueCodes), e.g. MCOStatusCode or CabinClass: code and label. */
    public function valueCodes(string $name): JsonResponse
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9]{1,60}$/', $name)) {
            throw ApiException::invalid('Unknown list.');
        }
        try {
            $raw = CachedValue::get('zenith:GetValueCodeList', 24 * 3600, fn () => $this->zenith->call('GetValueCodeList'));
            if (! in_array($name, array_map('strval', Js::list($raw['CodeList'] ?? null)), true)) {
                throw new ApiException(404, 'not_found', 'There is no value-code list with that name.');
            }
            $codes = $this->flights->valueCodes($name);
        } catch (ApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->log->error('value-codes', $e->getMessage(), ['list' => $name]);
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }

        return $this->json(['name' => $name, 'codes' => array_values(array_map(fn ($c) => [
            'code' => (string) ($c['Code'] ?? ''),
            'label' => (string) ($c['Label'] ?? ''),
        ], array_filter(Js::list($codes['Codes'] ?? null), 'is_array')))], 200, ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * Flight search (SearchFlights). With flexibleDays = 1 it also searches a
     * day either side, in parallel, for the date strip.
     */
    public function search(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ApiException::invalid('Request body must be valid JSON.');
        }

        try {
            $config = $this->flights->config();
        } catch (Throwable $e) {
            $this->log->error('flight-search', 'config: '.$e->getMessage());
            throw new ApiException(503, 'unavailable', 'Flight search is temporarily unavailable. Please try again shortly.');
        }

        $input = FlightSearch::validate($body, $config);
        $requested = array_column($input['legs'], 'date');
        $requestedKey = implode('|', $requested);
        $dateSets = $input['flexibleDays']
            ? FlightSearch::flexibleDateSets($input, $config, Js::today())
            : [$requested];
        // The requested dates are always searched (an unserved day just comes
        // back empty without calling Zenith).
        if (! in_array($requestedKey, array_map(fn ($s) => implode('|', $s), $dateSets), true)) {
            array_unshift($dateSets, $requested);
        }

        // Counts upstream Zenith calls (a flexible search makes up to 5).
        $limit = max(1, (int) config('naoero.search_rate_limit'));
        $key = 'flight-search:'.RequestMeta::clientKey($request);
        if (RateLimiter::attempts($key) + count($dateSets) > $limit) {
            return $this->json(
                ['error' => ['code' => 'rate_limited', 'message' => 'Too many searches. Please wait a moment and try again.']],
                429,
                ['Retry-After' => (string) (RateLimiter::attempts($key) === 0 ? 60 : max(1, RateLimiter::availableIn($key)))],
            );
        }
        RateLimiter::increment($key, 60, count($dateSets));

        // Fare family names are cosmetic; never fail a search over them.
        try {
            $families = FlightSearch::fareFamilies($this->flights->valueCodes('WebClass'));
        } catch (Throwable) {
            $families = [];
        }

        $results = [];
        $calls = [];
        foreach ($dateSets as $i => $dates) {
            $legs = array_map(fn ($leg, $date) => [...$leg, 'date' => $date], $input['legs'], $dates);
            // Days the airline doesn't fly never reach Zenith (saves its quota).
            foreach ($legs as $leg) {
                if (! FlightConfigService::isServedOn($config, $leg['origin'], $leg['destination'], $leg['date'])) {
                    $results[$i] = FlightSearch::emptyResult($input['currency'], 'NoAvailability');

                    continue 2;
                }
            }
            $calls[(string) $i] = ['SearchFlights', FlightSearch::buildRequest([...$input, 'legs' => $legs])];
        }
        if (count($calls) === 1) {
            $only = array_key_first($calls);
            try {
                $answers = [$only => $this->zenith->call(...$calls[$only])];
            } catch (Throwable $e) {
                $answers = [$only => $e];
            }
        } else {
            $answers = $calls ? $this->zenith->callMany($calls) : [];
        }
        foreach ($answers as $i => $answer) {
            $results[(int) $i] = $this->searchOutcome($answer, $input['currency'], $families);
        }

        $mainIndex = array_search($requestedKey, array_map(fn ($s) => implode('|', $s), $dateSets), true);
        $main = $results[$mainIndex];
        if ($main instanceof Throwable) {
            return $this->searchFailure($main);
        }

        $response = $main;
        if ($input['flexibleDays']) {
            // Each date set that came back (requested one included) with its
            // cheapest price, for the date strip.
            $flexible = [];
            foreach ($dateSets as $i => $dates) {
                $result = $results[$i];
                if ($result instanceof Throwable) {
                    continue;
                }
                $prices = array_column($result['journeys'], 'lowestPrice');
                $flexible[] = ['dates' => $dates, 'lowestPrice' => $prices ? min($prices) : null, 'result' => $result];
            }
            usort($flexible, fn ($a, $b) => strcmp(implode('|', $a['dates']), implode('|', $b['dates'])));
            $response['flexible'] = $flexible;
        }

        return $this->json($response);
    }

    /** One SearchFlights answer: "nothing to sell" becomes an empty result; failures stay errors. */
    private function searchOutcome(array|Throwable $answer, string $currency, array $families): array|Throwable
    {
        if (is_array($answer)) {
            return FlightSearch::normalize($answer, $families);
        }
        if ($answer instanceof ZenithBusinessException && in_array($answer->zenithCode, ['NoAvailability', 'NoFares'], true)) {
            return FlightSearch::emptyResult($currency, $answer->zenithCode);
        }

        return $answer;
    }

    private function searchFailure(Throwable $e): JsonResponse
    {
        if ($e instanceof ZenithBusinessException) {
            if ($e->zenithCode === 'CallQuotaReached') {
                throw new ApiException(429, 'busy', 'Flight search is very busy right now. Please try again in a few minutes.');
            }
            // Zenith sends no error code for a bad promo code, only this
            // message, which is written for travellers and safe to show.
            if (preg_match('/promo/i', $e->getMessage())) {
                throw new ApiException(400, 'invalid_promo', trim(preg_replace('/\s+/', ' ', $e->getMessage())));
            }
            $this->log->error('flight-search', 'business error', ['code' => $e->zenithCode, 'message' => $e->getMessage()]);
            throw new ApiException(502, 'airline_error', 'The airline system could not complete this search. Please try again.');
        }
        $this->log->error('flight-search', $e->getMessage());
        throw new ApiException(503, 'unavailable', 'Flight search is temporarily unavailable. Please try again shortly.');
    }
}
