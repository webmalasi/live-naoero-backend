<?php

namespace App\Services\Flights;

use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithClient;

/**
 * What the search form needs from the airline: routes, flight dates,
 * passenger types, currencies, fare-type filters. Built from Zenith's
 * SaleConfiguration, EligibleOriginDestinations and Airport value codes, and
 * cached for as long as the Zenith guidance allows.
 */
class FlightConfigService
{
    public function __construct(private readonly ZenithClient $zenith) {}

    /** @return array<string, mixed> */
    public function saleConfiguration(): array
    {
        return CachedValue::get('zenith:SaleConfiguration', 20 * 60, fn () => $this->zenith->call('SaleConfiguration'));
    }

    /** @return array<string, mixed> */
    public function eligibleOriginDestinations(): array
    {
        return CachedValue::get('zenith:EligibleOriginDestinations', 15 * 60, fn () => $this->zenith->call('EligibleOriginDestinations'));
    }

    /** @return array<string, mixed> */
    public function valueCodes(string $name): array
    {
        return CachedValue::get("zenith:GetValueCodes:{$name}", 6 * 3600, fn () => $this->zenith->call('GetValueCodes', ['ValueCodeName' => $name]));
    }

    /** @return array<string, mixed> */
    public function config(): array
    {
        return self::build($this->saleConfiguration(), $this->eligibleOriginDestinations(), $this->valueCodes('Airport'));
    }

    /** True when the airline lists flights on $date for the route, or lists no dates for it at all. */
    public static function isServedOn(array $config, string $origin, string $destination, string $date): bool
    {
        $dates = ((array) $config['flightDates'])["{$origin}-{$destination}"] ?? null;

        return ! $dates || in_array($date, $dates, true);
    }

    public static function property(mixed $code, string $name): ?array
    {
        foreach (Js::list(data_get($code, 'ValueCodeProperties')) as $property) {
            if (($property['Name'] ?? null) === $name) {
                return $property;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(array $sale, array $eligible, array $airportCodes, ?string $now = null): array
    {
        $now ??= Js::iso(now());

        $routes = [];
        foreach (Js::list($sale['AllowedOriginDestinations'] ?? null) as $od) {
            $destinations = array_values(array_unique(Js::list($od['DestinationCodes'] ?? null)));
            sort($destinations, SORT_STRING);
            if (! empty($od['OriginCode']) && $destinations !== []) {
                $routes[$od['OriginCode']] = $destinations;
            }
        }

        $served = [];
        foreach ($routes as $origin => $destinations) {
            $served[$origin] = true;
            foreach ($destinations as $d) {
                $served[$d] = true;
            }
        }

        $airportNames = [];
        foreach (Js::list($airportCodes['Codes'] ?? null) as $a) {
            $airportNames[$a['Code']] = [
                'name' => Js::text($a['Label'] ?? null) ?? $a['Code'],
                'countryCode' => self::property($a, 'CountryCode')['StringValue'] ?? null,
            ];
        }
        $airports = [];
        foreach (array_keys($served) as $code) {
            $code = (string) $code;
            $airports[] = [
                'code' => $code,
                'name' => $airportNames[$code]['name'] ?? $code,
                'countryCode' => $airportNames[$code]['countryCode'] ?? null,
            ];
        }
        usort($airports, fn ($a, $b) => Js::compare($a['name'], $b['name']));

        // Only dates on today or later, on routes the vendor can actually sell.
        $today = substr($now, 0, 10);
        $dateSets = [];
        foreach (Js::list($eligible['OriginDestinations'] ?? null) as $od) {
            $date = substr((string) ($od['TargetDate'] ?? ''), 0, 10);
            if ($date === '' || $date < $today) {
                continue;
            }
            if (! in_array($od['DestinationCode'] ?? null, $routes[$od['OriginCode'] ?? ''] ?? [], true)) {
                continue;
            }
            $dateSets["{$od['OriginCode']}-{$od['DestinationCode']}"][$date] = true;
        }
        ksort($dateSets, SORT_STRING);
        $flightDates = [];
        foreach ($dateSets as $key => $set) {
            $dates = array_map('strval', array_keys($set));
            sort($dates, SORT_STRING);
            $flightDates[$key] = $dates;
        }

        // Offer only currencies the app can actually take payment in; fall back
        // to every allowed currency if no form of payment lists any.
        $allowed = Js::list($sale['AllowedCurrencies'] ?? null);
        $payable = [];
        foreach (Js::list($sale['AllowedFormsOfPayment'] ?? null) as $fop) {
            foreach (Js::list($fop['AllowedCurrencyCodes'] ?? null) as $c) {
                $payable[$c] = true;
            }
        }
        $currencyCodes = $payable ? array_values(array_filter($allowed, fn ($c) => isset($payable[$c['Code']]))) : $allowed;
        $defaultCurrency = Js::text($sale['DefaultCurrency'] ?? null) ?? ($currencyCodes[0]['Code'] ?? 'AUD');
        $currencies = array_map(fn ($c) => ['code' => $c['Code'], 'label' => Js::text($c['Label'] ?? null) ?? $c['Code']], $currencyCodes);
        usort($currencies, fn ($a, $b) => $a['code'] === $defaultCurrency ? -1 : ($b['code'] === $defaultCurrency ? 1 : Js::compare($a['label'], $b['label'])));

        $passengerTypes = array_map(fn ($p) => [
            'code' => $p['Code'],
            'label' => Js::text($p['Label'] ?? null) ?? $p['Code'],
            'minAge' => self::property($p, 'MinAge')['NumberValue'] ?? null,
            'maxAge' => self::property($p, 'MaxAge')['NumberValue'] ?? null,
        ], Js::list($sale['AllowedPassengerTypes'] ?? null));

        $filters = [];
        foreach (Js::list($sale['WebClassFilters'] ?? null) as $f) {
            $classes = array_map(fn ($w) => ['code' => $w['Code'], 'label' => Js::text($w['Label'] ?? null) ?? $w['Code']], Js::list($f['WebClasses'] ?? null));
            if ($classes !== []) {
                $filters[] = ['name' => Js::text($f['FilterName'] ?? null) ?? 'Fare type', 'mandatory' => (bool) ($f['IsMandatory'] ?? false), 'classes' => $classes];
            }
        }

        $maxPassengers = (int) ($sale['MaxPassengerCount'] ?? 0);
        $maxLegs = (int) ($sale['MaxOriginDestinationCount'] ?? 0);

        return [
            'airline' => ['code' => $sale['AirlineDesignator'] ?? '', 'name' => $sale['AirlineName'] ?? ''],
            'environment' => $sale['EnvironmentCode'] ?? '',
            'defaultCurrency' => $defaultCurrency,
            'maxPassengers' => $maxPassengers > 0 ? $maxPassengers : 9,
            'maxLegs' => $maxLegs > 0 ? $maxLegs : 1,
            'allowPromoCode' => (bool) ($sale['AllowPromoCode'] ?? false),
            'webClassFilters' => $filters,
            'passengerTypes' => $passengerTypes,
            'currencies' => $currencies,
            'airports' => $airports,
            'routes' => Js::map($routes),
            'flightDates' => Js::map($flightDates),
            'generatedAt' => $now,
        ];
    }
}
