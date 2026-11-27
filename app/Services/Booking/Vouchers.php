<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The customer's credit vouchers (Zenith MCOs, LoadMCOTickets): what a
 * refund or a change left them, with the amount still available and when
 * it expires. Zenith asks for both the customer reference and the login, so
 * one customer can never load another's vouchers.
 */
final class Vouchers
{
    /** MCOStatusCode values (Zenith's list) as customers read them. "Issued" is the only usable one. */
    private const STATUSES = [
        'Issued' => 'Available',
        'Expired' => 'Expired',
        'Void' => 'Cancelled',
        'Exchanged' => 'Used',
        'Flown' => 'Used',
        'Unavailable' => 'Used',
        'Refund' => 'Refunded',
        'PartnerControl' => 'Held by a partner airline',
        'Suspended' => 'Suspended',
        'Closed' => 'Closed',
        'Used' => 'Used',
    ];

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly ServerLog $log,
    ) {}

    /**
     * @return array{vouchers: list<array>, totals: list<array{currency: string, amount: float}>}
     */
    public function forCustomer(object $customer, bool $usableOnly = false, ?string $currency = null): array
    {
        if (empty($customer->zenith_customer_ref) || empty($customer->zenith_login)) {
            return ['vouchers' => [], 'totals' => []];
        }
        $request = [
            'CustomerReference' => ['RefCustomer' => (string) $customer->zenith_customer_ref, 'Login' => (string) $customer->zenith_login],
            'UsableOnly' => $usableOnly,
        ];
        if ($currency !== null) {
            $request['CurrencyCode'] = $currency;
        }
        try {
            $res = $this->zenith->call('LoadMCOTickets', $request);
        } catch (ZenithBusinessException $e) {
            $this->log->warn('vouchers', 'LoadMCOTickets refused', ['code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'vouchers_unavailable', 'Your vouchers cannot be shown right now: '.$e->getMessage());
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }

        $vouchers = array_map(self::format(...), Js::list($res['MCOTickets'] ?? null));
        usort($vouchers, fn ($a, $b) => [$b['usable'], $a['expiresAt'] ?? '9999'] <=> [$a['usable'], $b['expiresAt'] ?? '9999']);
        $totals = [];
        foreach ($vouchers as $v) {
            if ($v['usable']) {
                $totals[$v['currency']] = Js::round2(($totals[$v['currency']] ?? 0) + $v['amount']);
            }
        }

        return [
            'vouchers' => $vouchers,
            'totals' => array_map(fn ($c, $a) => ['currency' => $c, 'amount' => $a], array_keys($totals), $totals),
        ];
    }

    /** The same list, never failing (used after a refund, when the refund itself succeeded). */
    public function forCustomerQuietly(object $customer): array
    {
        try {
            return $this->forCustomer($customer, usableOnly: true)['vouchers'];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string, mixed> */
    private static function format(array $m): array
    {
        $code = (string) ($m['StatusCode'] ?? '');
        $expires = BookingSummary::utc($m['ExpiryDateGMT'] ?? null);
        $expired = $expires !== null && CarbonImmutable::parse($expires)->isPast();

        return [
            'number' => (string) ($m['DocumentNumber'] ?? ''),
            'amount' => Js::round2($m['Amount'] ?? 0),
            'currency' => (string) ($m['Currency'] ?? ''),
            'status' => $code,
            'statusLabel' => $expired && $code === 'Issued' ? 'Expired' : (self::STATUSES[$code] ?? ($code !== '' ? $code : 'Unknown')),
            'usable' => $code === 'Issued' && ! $expired && (float) ($m['Amount'] ?? 0) > 0,
            'expiresAt' => $expires,
        ];
    }
}
