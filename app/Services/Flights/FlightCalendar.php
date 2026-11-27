<?php

namespace App\Services\Flights;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * A month of a route for the date picker (Zenith Calendar): each day with a
 * flight open for sale is available (HK), waitlisted (HL) or full. It
 * answers about seats, not fares: a day shown available can still have no
 * fare for the passengers searched. Real time, so cached only briefly.
 */
final class FlightCalendar
{
    /** CalendarDayStatusCode => what the app shows. */
    public const STATUSES = ['HK' => 'available', 'HL' => 'waitlist', 'Full' => 'full'];

    /** How far ahead a month can be asked for. */
    private const MAX_MONTHS_AHEAD = 13;

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly FlightConfigService $flights,
        private readonly ServerLog $log,
    ) {}

    /** @return array{origin: string, destination: string, month: string, days: list<array{date: string, status: string, code: string}>} */
    public function month(string $origin, string $destination, string $month): array
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        $thisMonth = CarbonImmutable::now()->startOfMonth();
        if ($start->lessThan($thisMonth) || $start->greaterThan($thisMonth->addMonths(self::MAX_MONTHS_AHEAD))) {
            throw ApiException::invalid('Choose a month from now to a year ahead.');
        }
        $this->checkRoute($origin, $destination);

        $from = $start->lessThan(CarbonImmutable::today()) ? CarbonImmutable::today() : $start;
        $to = $start->endOfMonth()->startOfDay();
        $raw = CachedValue::get("zenith:Calendar:{$origin}-{$destination}:{$month}", 5 * 60, function () use ($origin, $destination, $from, $to) {
            try {
                return $this->zenith->call('Calendar', ['OriginDestinationQueries' => [[
                    'OriginCode' => $origin,
                    'DestinationCode' => $destination,
                    'StartDate' => $from->format('Y-m-d').'T00:00:00',
                    'EndDate' => $to->format('Y-m-d').'T00:00:00',
                ]]]);
            } catch (ZenithBusinessException $e) {
                if (in_array($e->zenithCode, ['NoAvailability', 'NoFares'], true)) {
                    return ['OriginDestinationResults' => []];
                }
                $this->log->warn('flight-calendar', 'Calendar refused', ['route' => "{$origin}-{$destination}", 'month' => $from->format('Y-m'), 'code' => $e->zenithCode, 'message' => $e->getMessage()]);

                throw new ApiException(409, 'calendar_unavailable', trim('The calendar is not available for this route: '.$e->getMessage()));
            } catch (ZenithUnavailableException) {
                throw new ApiException(503, 'unavailable', 'Flight information is temporarily unavailable. Please try again shortly.');
            }
        });

        $days = [];
        foreach (Js::list($raw['OriginDestinationResults'] ?? null) as $r) {
            if (($r['OriginCode'] ?? $origin) !== $origin || ($r['DestinationCode'] ?? $destination) !== $destination) {
                continue;
            }
            foreach (Js::list($r['Days'] ?? null) as $d) {
                $date = substr((string) ($d['Date'] ?? ''), 0, 10);
                $code = (string) ($d['CalendarDayStatusCode'] ?? '');
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && str_starts_with($date, $month)) {
                    $days[$date] = ['date' => $date, 'status' => self::STATUSES[$code] ?? 'unknown', 'code' => $code];
                }
            }
        }
        ksort($days);

        return ['origin' => $origin, 'destination' => $destination, 'month' => $month, 'days' => array_values($days)];
    }

    /** Only the routes the airline sells (Zenith would answer NoAvailability anyway, after a call). */
    private function checkRoute(string $origin, string $destination): void
    {
        try {
            $routes = $this->flights->config()['routes'] ?? null;
        } catch (Throwable) {
            return;
        }
        if (is_array($routes) && $routes !== [] && ! in_array($destination, (array) ($routes[$origin] ?? []), true)) {
            throw new ApiException(404, 'route_not_served', 'Naoero Airlines does not fly this route.');
        }
    }
}
