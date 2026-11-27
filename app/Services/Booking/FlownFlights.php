<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\CachedValue;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Which flights of a booking were actually flown, from the airline's flown
 * coupons (FlownCoupons). Zenith answers for every passenger of the airline
 * over at most 7 days, so only the coupons of this booking's own tickets are
 * kept: nothing about other travellers ever leaves the server.
 */
final class FlownFlights
{
    /** Zenith's limit on one FlownCoupons call. */
    private const WINDOW_DAYS = 7;

    /** At most this many calls for one booking (long trips span several windows). */
    private const MAX_WINDOWS = 6;

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly BookingService $bookings,
        private readonly ServerLog $log,
    ) {}

    /** @return array{pnr: string, coupons: list<array>, flownCount: int, checkedUntil: ?string} */
    public function forBooking(object $customer, string $pnr): array
    {
        $this->bookings->ownedBooking($customer, $pnr);
        $stored = Js::decode((string) DB::table('bookings')->where('customer_id', $customer->id)
            ->where('zenith_environment', $customer->zenith_environment)->where('pnr', $pnr)->value('zenith_booking'));
        $b = is_array($stored) && $stored !== [] ? $stored : $this->bookings->loadZenithBooking($pnr, $customer);

        $names = BookingService::passengerNames($b);
        $coupons = [];
        foreach (Js::list($b['TicketInfo']['ETTickets'] ?? null) as $t) {
            $number = (string) ($t['DocumentNumber'] ?? '');
            foreach (Js::list($t['Coupons'] ?? null) as $c) {
                $sold = is_array($c['SegmentSold'] ?? null) ? $c['SegmentSold'] : null;
                if ($number === '' || $sold === null || ! isset($sold['OriginCode'], $sold['DestinationCode'])) {
                    continue;
                }
                $coupons["{$number}|".(int) ($c['CouponOrder'] ?? 0)] = [
                    'ticketNumber' => $number,
                    'couponOrder' => (int) ($c['CouponOrder'] ?? 0),
                    'passengerName' => $names[(string) ($t['RefPassenger'] ?? '')] ?? null,
                    'flight' => BookingSummary::flight([...$sold, 'BookingClass' => ['StatusCode' => null]]),
                    'flown' => false,
                    'fareBasis' => null,
                    'cabin' => null,
                ];
            }
        }

        // Only departures already past can have been flown.
        $today = CarbonImmutable::now()->startOfDay();
        $dates = array_values(array_unique(array_filter(array_map(fn ($c) => substr((string) $c['flight']['departure'], 0, 10), $coupons),
            fn ($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && CarbonImmutable::parse($d)->lessThanOrEqualTo($today))));
        sort($dates);
        $windows = self::windows($dates);
        $checkedUntil = $windows ? end($windows)[1] : null;
        // Only this booking's matches are kept (and cached), never the airline-wide answer.
        $matches = $windows === [] ? [] : CachedValue::get('flown:'.$customer->zenith_environment.':'.$pnr.':'.md5(implode(',', array_keys($coupons)).'|'.Js::encode($windows)), 30 * 60, function () use ($windows, $coupons) {
            $found = [];
            foreach ($windows as [$from, $to]) {
                foreach (Js::list($this->call($from, $to)['ETCouponFlowns'] ?? null) as $f) {
                    $key = ((string) ($f['ETDocumentNumber'] ?? '')).'|'.(int) ($f['CouponOrder'] ?? 0);
                    if (isset($coupons[$key])) {
                        $found[$key] = ['fareBasis' => $f['FareBasisCode'] ?? null, 'cabin' => $f['CabinClassCode'] ?? null];
                    }
                }
            }

            return $found;
        });
        foreach ($matches as $key => $m) {
            $coupons[$key] = [...$coupons[$key], 'flown' => true, ...$m];
        }
        $this->log->info('booking', 'flown coupons checked', ['pnr' => $pnr, 'windows' => count($windows), 'flown' => count(array_filter($coupons, fn ($c) => $c['flown']))]);

        return [
            'pnr' => $pnr,
            'coupons' => array_values($coupons),
            'flownCount' => count(array_filter($coupons, fn ($c) => $c['flown'])),
            'checkedUntil' => $checkedUntil,
        ];
    }

    /**
     * Departure dates grouped into windows of at most 7 days.
     *
     * @param  list<string>  $dates  sorted Y-m-d
     * @return list<array{0: string, 1: string}>
     */
    public static function windows(array $dates): array
    {
        $out = [];
        foreach ($dates as $d) {
            $last = end($out);
            if ($last && CarbonImmutable::parse($last[0])->addDays(self::WINDOW_DAYS - 1)->format('Y-m-d') >= $d) {
                $out[array_key_last($out)][1] = $d;

                continue;
            }
            $out[] = [$d, $d];
        }

        // The most recent trips first, when a booking spans more than allowed.
        return array_slice($out, -self::MAX_WINDOWS);
    }

    private function call(string $from, string $to): array
    {
        try {
            return $this->zenith->call('FlownCoupons', ['StartFlightDate' => "{$from}T00:00:00", 'EndFlightDate' => "{$to}T23:59:59", 'AwardableCouponsOnly' => false]);
        } catch (ZenithBusinessException $e) {
            $this->log->warn('booking', 'FlownCoupons refused', ['from' => $from, 'to' => $to, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'flown_unavailable', trim('Flown flights cannot be shown right now: '.$e->getMessage()));
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }
    }
}
