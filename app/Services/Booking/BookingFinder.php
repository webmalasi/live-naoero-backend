<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bookings made elsewhere — on the website, by a travel agent, at the
 * airport — brought into the customer's list:
 *
 *  - sync(): Zenith SearchBooking by the customer reference finds every
 *    booking on the customer's airline account; new ones are loaded and
 *    saved (source "account"), changed ones refreshed. Throttled.
 *  - find(): a booking reference plus a passenger surname (and optionally
 *    first name), the way airline websites let travellers manage a booking
 *    (source "reference"). Zenith verifies the name with public keys only, so
 *    the name is always checked here too, against the booking's passengers.
 *  - remove(): takes an added booking off the list (it stays at the airline).
 *
 * A booking already linked to another customer is never moved or shown.
 */
class BookingFinder
{
    /** The airline account is searched at most this often automatically. */
    public const SYNC_EVERY_MINUTES = 5;

    /** …and at most this often on an explicit refresh. */
    public const FORCE_MIN_SECONDS = 30;

    /** New bookings loaded per sync (the rest come with the next one). */
    public const IMPORT_LIMIT = 15;

    /** Known bookings refreshed per sync when the airline's status differs. */
    public const RELOAD_LIMIT = 10;

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly BookingService $bookings,
        private readonly ServerLog $log,
    ) {}

    /** Upper-case A-Z only, accents folded: "Ñúñez-O'Brien" -> "NUNEZOBRIEN". */
    public static function normalize(?string $name): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii((string) $name)));
    }

    /** Our booking status for a SearchBooking summary, when it can be told. */
    private static function expectedStatus(array $s): ?string
    {
        return match ($s['PnrStatusCode'] ?? null) {
            'Issued' => 'ticketed',
            'Cancelled', 'Refunded' => 'cancelled',
            'Option' => ! empty($s['TimeLimit']) && CarbonImmutable::parse($s['TimeLimit'], 'UTC')->isPast() ? 'expired' : 'pending_payment',
            default => null,
        };
    }

    // ------------------------------------------------------------ account sync

    /**
     * Searches the customer's airline account for bookings.
     *
     * @return array{status: string, syncedAt: ?string, found: int, imported: int, updated: int}
     */
    public function sync(object $customer, bool $force = false): array
    {
        $last = DB::table('customers')->where('id', $customer->id)->value('bookings_synced_at');
        $lastAt = $last ? CarbonImmutable::parse($last) : null;
        $wait = $force ? now()->subSeconds(self::FORCE_MIN_SECONDS) : now()->subMinutes(self::SYNC_EVERY_MINUTES);
        if ($lastAt && $lastAt->greaterThan($wait)) {
            return ['status' => 'recent', 'syncedAt' => Js::iso($lastAt), 'found' => 0, 'imported' => 0, 'updated' => 0];
        }
        if (empty($customer->zenith_customer_ref)) {
            return ['status' => 'unavailable', 'syncedAt' => Js::iso($lastAt), 'found' => 0, 'imported' => 0, 'updated' => 0];
        }

        try {
            $res = $this->zenith->call('SearchBooking', ['CustomerRef' => $customer->zenith_customer_ref]);
        } catch (Throwable $e) {
            // The list still shows what we already have.
            $this->log->warn('booking', 'SearchBooking failed', ['error' => $e->getMessage()]);

            return ['status' => 'unavailable', 'syncedAt' => Js::iso($lastAt), 'found' => 0, 'imported' => 0, 'updated' => 0];
        }
        $summaries = Js::list($res['BookingSummaries'] ?? null);
        // Newest trips first, so the ones that matter come in first.
        usort($summaries, fn ($a, $b) => strcmp((string) ($b['NextActiveDepartureDate'] ?? $b['NextInactiveDepartureDate'] ?? ''), (string) ($a['NextActiveDepartureDate'] ?? $a['NextInactiveDepartureDate'] ?? '')));
        $pnrs = array_values(array_filter(array_map(fn ($s) => strtoupper((string) ($s['PnrCode'] ?? '')), $summaries)));
        $known = DB::table('bookings')->where('zenith_environment', $customer->zenith_environment)->whereIn('pnr', $pnrs ?: [''])
            ->get(['pnr', 'customer_id', 'status', 'hidden_at'])->keyBy('pnr');

        $imported = 0;
        $updated = 0;
        foreach ($summaries as $s) {
            $pnr = strtoupper((string) ($s['PnrCode'] ?? ''));
            if (! preg_match('/^[A-Z0-9]{5,8}$/', $pnr)) {
                continue;
            }
            $row = $known[$pnr] ?? null;
            if ($row && $row->customer_id !== $customer->id) {
                $this->log->warn('booking', 'account booking already linked to another customer', ['pnr' => $pnr]);

                continue;
            }
            if ($row && $row->hidden_at) {
                continue; // the customer removed it
            }
            if (! $row && $imported >= self::IMPORT_LIMIT) {
                continue;
            }
            if ($row && ($updated >= self::RELOAD_LIMIT || self::expectedStatus($s) === null || self::expectedStatus($s) === $row->status)) {
                continue;
            }
            try {
                $booking = $this->bookings->loadZenithBooking($pnr, $customer, ['RefCustomer' => $customer->zenith_customer_ref]);
                $this->bookings->saveBooking($customer, $booking, $row ? [] : ['source' => 'account', 'linked_at' => Js::dbTime()]);
                $row ? $updated++ : $imported++;
            } catch (Throwable $e) {
                $this->log->warn('booking', 'could not load account booking', ['pnr' => $pnr, 'error' => $e->getMessage()]);
            }
        }
        $now = CarbonImmutable::now();
        DB::table('customers')->where('id', $customer->id)->update(['bookings_synced_at' => Js::dbTime($now)]);
        if ($imported || $updated) {
            $this->log->info('booking', 'account bookings synced', ['found' => count($summaries), 'imported' => $imported, 'updated' => $updated]);
        }

        return ['status' => 'synced', 'syncedAt' => Js::iso($now), 'found' => count($summaries), 'imported' => $imported, 'updated' => $updated];
    }

    // ------------------------------------------------------------ by reference

    private static function notFound(): ApiException
    {
        // The same answer for an unknown reference and a wrong name: nothing to guess from.
        return new ApiException(404, 'booking_not_found', "We couldn't find a booking with that reference or ticket number and surname. Check both exactly as they appear on your e-ticket or booking confirmation.");
    }

    /**
     * Adds a booking made elsewhere with its reference, or one of its
     * e-ticket numbers, and a passenger's surname (and optionally first
     * name). With a ticket number the name must be that ticket's passenger.
     *
     * @return array<string, mixed> the booking summary, with its source
     */
    public function find(object $customer, ?string $pnr, string $lastName, ?string $firstName = null, ?string $ticketNumber = null): array
    {
        $pnr = strtoupper(trim((string) $pnr));
        $ticketNumber = $ticketNumber !== null ? preg_replace('/\D/', '', $ticketNumber) : null;
        $surname = self::normalize($lastName);
        $first = self::normalize($firstName);
        if ($surname === '') {
            throw ApiException::invalid('Enter the surname of a passenger on the booking.');
        }

        try {
            $res = $this->zenith->call('LoadBooking', [
                'UniqueID' => $ticketNumber ? ['TypeCode' => 'ETTicketNumber', 'ID' => $ticketNumber] : ['TypeCode' => 'PnrCode', 'ID' => $pnr],
                'Verification' => ['PassengerName' => strtoupper(Str::ascii(trim($lastName)))],
                'IncludeSegmentStops' => true,
            ]);
        } catch (ZenithBusinessException $e) {
            if (preg_match('/quota/i', $e->zenithCode)) {
                throw new ApiException(429, 'busy', 'The airline system is busy. Please try again in a minute.');
            }
            throw self::notFound();
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'Bookings are temporarily unavailable. Please try again shortly.');
        }
        $booking = is_array($res['Booking'] ?? null) ? $res['Booking'] : null;
        $holder = null;
        if ($booking && $ticketNumber) {
            $pnr = strtoupper((string) ($booking['PnrInformation']['PnrCode'] ?? ''));
            foreach (Js::list($booking['TicketInfo']['ETTickets'] ?? null) as $t) {
                if (preg_replace('/\D/', '', (string) ($t['DocumentNumber'] ?? '')) === $ticketNumber) {
                    $holder = (string) ($t['RefPassenger'] ?? '');
                }
            }
            if ($holder === null) {
                throw self::notFound();
            }
        }
        if (! $booking || $pnr === '' || strtoupper((string) ($booking['PnrInformation']['PnrCode'] ?? '')) !== $pnr) {
            throw self::notFound();
        }

        // Our own check: some airline keys do not verify the name.
        $match = null;
        foreach (Js::list($booking['Passengers'] ?? null) as $p) {
            if (self::normalize($p['NameElement']['Surname'] ?? null) === $surname
                && ($holder === null || $holder === '' || (string) $p['Ref'] === $holder)
                && ($first === '' || str_starts_with(self::normalize($p['NameElement']['Firstname'] ?? null), $first))) {
                $match = $p;
                break;
            }
        }
        if (! $match) {
            throw self::notFound();
        }

        $existing = DB::table('bookings')->where('zenith_environment', $customer->zenith_environment)->where('pnr', $pnr)
            ->first(['customer_id', 'source']);
        if ($existing && $existing->customer_id !== $customer->id) {
            throw new ApiException(409, 'booking_linked_elsewhere', 'This booking is already linked to another Naoero Airlines account. Please contact Naoero Airlines if you need access to it.');
        }

        $onAccount = (string) ($booking['PnrInformation']['RefCustomer'] ?? '') !== ''
            && (string) $booking['PnrInformation']['RefCustomer'] === (string) $customer->zenith_customer_ref;
        $link = ['hidden_at' => null];
        if (! $existing) {
            $link += [
                'source' => $onAccount ? 'account' : 'reference',
                // Later calls on a booking found by name are verified by name.
                'verify_surname' => $onAccount ? null : mb_substr(mb_strtoupper((string) $match['NameElement']['Surname']), 0, 60),
                'linked_at' => Js::dbTime(),
            ];
        }
        $summary = $this->bookings->saveBooking($customer, $booking, $link);
        $this->log->info('booking', $ticketNumber ? 'booking added by ticket number' : 'booking added by reference', ['pnr' => $pnr, 'source' => $summary['source'], 'already' => (bool) $existing]);

        return $summary;
    }

    /** Takes a booking made elsewhere off the customer's list. */
    public function remove(object $customer, string $pnr): void
    {
        $row = DB::table('bookings')->where('customer_id', $customer->id)->where('zenith_environment', $customer->zenith_environment)
            ->where('pnr', strtoupper($pnr))->whereNull('hidden_at')->first(['id', 'source']);
        if (! $row) {
            throw new ApiException(404, 'booking_not_found', "We couldn't find that booking on your account.");
        }
        if ($row->source === 'app') {
            throw new ApiException(409, 'cannot_remove', 'Bookings made with Naoero Airlines stay in your list.');
        }
        DB::table('bookings')->where('id', $row->id)->update(['hidden_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
        $this->log->info('booking', 'booking removed from list', ['pnr' => strtoupper($pnr)]);
    }
}
