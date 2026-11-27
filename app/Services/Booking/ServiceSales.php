<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Flights\FlightConfigService;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Throwable;

/**
 * Services the airline sells on their own, not on a flight booking
 * (SearchServices -> BuyServices): travel credit bundles, passes and the
 * like. Buying makes a "service booking" with its own reference, paid like
 * any booking (BookingService::startPayment); the services are usable once
 * paid. Prices always come from Zenith, never from the client.
 */
final class ServiceSales
{
    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly BookingService $bookings,
        private readonly FlightConfigService $flights,
        private readonly ServerLog $log,
    ) {}

    /** @return array{currency: string, currencies: list<string>, services: list<array>} */
    public function catalog(?string $currency = null): array
    {
        $currency = $this->currency($currency);
        $raw = CachedValue::get("zenith:SearchServices:{$currency}", 10 * 60, fn () => $this->search($currency));

        return [
            'currency' => $currency,
            'currencies' => $this->currencies(),
            'services' => array_values(array_map(fn ($o) => self::format($o, $currency), self::sellable($raw))),
        ];
    }

    /**
     * Buys $quantity of one service (only one kind per purchase, as Zenith
     * requires) and returns the new service booking, waiting for payment.
     *
     * @return array{booking: array, message: string}
     */
    public function purchase(object $customer, string $ref, int $quantity, ?string $currency = null): array
    {
        $currency = $this->currency($currency);
        if (empty($customer->zenith_customer_ref)) {
            throw new ApiException(409, 'account_not_linked', 'Your account is not linked to the airline yet. Please sign in again.');
        }
        // The live list: the price and stock at the moment of buying.
        $options = self::sellable($this->search($currency));
        $option = null;
        foreach ($options as $o) {
            if ((string) ($o['Ref'] ?? '') === $ref) {
                $option = $o;
            }
        }
        if ($option === null) {
            throw new ApiException(409, 'service_unavailable', 'This service is no longer on sale. Please refresh the list.');
        }
        $max = self::maxQuantity($option);
        if ($quantity < 1 || $quantity > $max) {
            throw ApiException::invalid($max === 1 ? 'This service can be bought once at a time.' : "Choose between 1 and {$max}.");
        }

        $fare = ['ServiceTypeCode' => $option['ServiceTypeCode'] ?? null, 'ServiceCode' => $option['ServiceCode'] ?? null, 'Ref' => $option['Ref']];
        try {
            $res = $this->zenith->call('BuyServices', [
                'RefCustomer' => (string) $customer->zenith_customer_ref,
                'SaleCurrencyCode' => $currency,
                'EMDTicketFares' => array_fill(0, $quantity, $fare),
            ]);
        } catch (ZenithBusinessException $e) {
            $this->log->warn('services', 'BuyServices refused', ['ref' => $ref, 'quantity' => $quantity, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'purchase_refused', trim('The airline did not accept this purchase: '.$e->getMessage()));
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }
        $booking = is_array($res['Booking'] ?? null) ? $res['Booking'] : null;
        $pnr = strtoupper((string) ($booking['PnrInformation']['PnrCode'] ?? ''));
        if ($booking === null || $pnr === '') {
            $this->log->error('services', 'BuyServices answered without a booking', ['ref' => $ref]);

            throw new ApiException(503, 'unavailable', 'The airline did not confirm the purchase. Please check your trips before trying again.');
        }
        // A service booking may give its currency only on its fares.
        $booking['FareInfo']['SaleCurrencyCode'] ??= $currency;
        $summary = $this->bookings->saveBooking($customer, $booking, ['source' => 'app']);
        $this->log->info('services', 'services bought', ['pnr' => $pnr, 'ref' => $ref, 'quantity' => $quantity, 'due' => $summary['amountDue']]);

        return [
            'booking' => $summary,
            'message' => $summary['amountDue'] > 0
                ? 'Reserved as booking '.$pnr.'. Pay '.number_format($summary['amountDue'], 2)." {$summary['currency']} to get your services."
                : "Done: booking {$pnr}.",
        ];
    }

    private function search(string $currency): array
    {
        try {
            return $this->zenith->call('SearchServices', ['SaleCurrencyCode' => $currency]);
        } catch (ZenithBusinessException $e) {
            if (in_array($e->zenithCode, ['NoAvailability', 'NoFares'], true)) {
                return ['EMDTicketFareOptions' => []];
            }
            $this->log->warn('services', 'SearchServices refused', ['code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'services_unavailable', trim('Services are not available right now: '.$e->getMessage()));
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }
    }

    /**
     * Options a customer can buy here: not ones that need an amount or
     * passenger data typed in by airline staff, nor ones tied to a flight.
     */
    private static function sellable(array $raw): array
    {
        return array_values(array_filter(Js::list($raw['EMDTicketFareOptions'] ?? null), fn ($o) => is_array($o) && ! empty($o['Ref'])
            && empty($o['IsAmountUserInput']) && empty($o['IsSpecialServiceUserInput'])
            && Js::list($o['AppliableRefSegments'] ?? null) === []));
    }

    private static function maxQuantity(array $o): int
    {
        $max = isset($o['MaxQuantity']) ? (int) $o['MaxQuantity'] : 10;

        return empty($o['AllowMultiSelect']) ? min(1, $max) : max(0, min($max, 10));
    }

    /** @return array<string, mixed> */
    private static function format(array $o, string $currency): array
    {
        // Scripts and styles go with their contents; the rest keeps basic formatting only.
        $html = trim((string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) ($o['HtmlDescription'] ?? '')));
        $amount = is_array($o['SaleCurrencyAmount'] ?? null) ? $o['SaleCurrencyAmount'] : [];

        return [
            'ref' => (string) $o['Ref'],
            'name' => trim((string) ($o['Label'] ?? '')) ?: 'Service',
            'description' => trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $html)), ENT_QUOTES | ENT_HTML5)),
            // As the airline wrote it; only basic formatting kept.
            'descriptionHtml' => $html === '' ? null : strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li>'),
            'type' => $o['ServiceTypeCode'] ?? null,
            'serviceCode' => $o['ServiceCode'] ?? null,
            'category' => $o['CategoryCode'] ?? null,
            'currency' => $currency,
            'price' => Js::round2($amount['TotalAmount'] ?? 0),
            'base' => Js::round2($amount['BaseAmount'] ?? 0),
            'tax' => Js::round2($amount['TaxAmount'] ?? 0),
            'maxQuantity' => self::maxQuantity($o),
            'allowMultiple' => ! empty($o['AllowMultiSelect']),
        ];
    }

    /** @return list<string> */
    private function currencies(): array
    {
        try {
            return array_values(array_filter(array_map(fn ($c) => (string) (is_array($c) ? ($c['Code'] ?? '') : $c),
                Js::list($this->flights->saleConfiguration()['AllowedCurrencies'] ?? null))));
        } catch (Throwable) {
            return [];
        }
    }

    private function currency(?string $currency): string
    {
        $allowed = $this->currencies();
        if ($currency !== null && $currency !== '') {
            $currency = strtoupper($currency);
            if ($allowed && ! in_array($currency, $allowed, true)) {
                throw ApiException::invalid('This currency is not available.');
            }

            return $currency;
        }
        try {
            $default = (string) ($this->flights->saleConfiguration()['DefaultCurrency'] ?? '');
        } catch (Throwable) {
            $default = '';
        }

        return $default !== '' ? $default : ($allowed[0] ?? 'AUD');
    }
}
