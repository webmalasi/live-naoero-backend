<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Flights\FlightConfigService;
use App\Services\Logging\ServerLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use App\Services\Zenith\ZenithBusinessException;
use App\Services\Zenith\ZenithClient;
use App\Services\Zenith\ZenithUnavailableException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Cancelling a booking yourself (Zenith PrepareCancel -> Cancel). Zenith's
 * flights (segments, in the inventory) and tickets have separate
 * lifecycles, hence these ways:
 *
 *   booking      an unpaid booking: all its flights are released
 *   flights      some flights of an unpaid booking
 *   refund       a paid booking: the tickets are refunded, as the fare rules
 *                allow, to a credit voucher (MCO) on the customer's account,
 *                and the flights cancelled. Zenith never refunds to a card.
 *                Offered only when the key may refund to MCO.
 *   keep_ticket  a paid booking: the flights are cancelled (the seats go
 *                back on sale) but the tickets stay open, worth their fare,
 *                for a later flight change or refund. Nothing is paid back
 *                now. Offered only when the airline allows it
 *                (naoero.allow_keep_ticket_cancel).
 *
 * Each attempt is saved in booking_cancellations with the quote and
 * Zenith's answer. A booking is cancelled by one request at a time; a refund
 * is made only for the amount the customer was shown.
 */
final class Cancellations
{
    public const MODES = ['booking', 'flights', 'refund', 'keep_ticket'];

    private const REFUND_NOTE = 'Refunds go to a credit voucher on your account, not back to your card. You can use it for a future booking; it shows under Vouchers.';

    public function __construct(
        private readonly ZenithClient $zenith,
        private readonly BookingService $bookings,
        private readonly Vouchers $vouchers,
        private readonly FlightConfigService $flights,
        private readonly ServerLog $log,
    ) {}

    /**
     * Whether refunds to a credit voucher can be made: the key may refund
     * (RefundConfig.AllowedToRefund) and MCO is one of its refund forms of
     * payment. naoero.refund_to_voucher "on"/"off" overrides it.
     */
    public function refundToVoucherEnabled(): bool
    {
        $setting = strtolower((string) config('naoero.refund_to_voucher', 'auto'));
        if (in_array($setting, ['on', 'off'], true)) {
            return $setting === 'on';
        }
        try {
            $refund = $this->flights->saleConfiguration()['RefundConfig'] ?? [];
        } catch (Throwable) {
            return false;
        }
        $codes = array_map('strtoupper', array_map('strval', [...Js::list($refund['RefundFopCodes'] ?? null), ...Js::list($refund['RefundFopCodesInAccount'] ?? null)]));

        return ! empty($refund['AllowedToRefund']) && in_array('MCO', $codes, true);
    }

    private static function keepTicketEnabled(): bool
    {
        return (bool) config('naoero.allow_keep_ticket_cancel');
    }

    private const KEEP_TICKET_NOTE = 'Cancel flights only: your seats are released, but your tickets keep their value. Use it to change to another flight from Manage booking, or ask for a refund later. Nothing is paid back now.';

    // ------------------------------------------------------------ options

    /**
     * What can be cancelled on the booking, and what a refund would give
     * back (Zenith's own figures, after the fare rules' penalties).
     *
     * @return array<string, mixed>
     */
    public function options(object $customer, string $pnr): array
    {
        [$b, $summary] = $this->live($customer, $pnr);
        $reason = $this->blocker($b, $summary);
        $kept = $summary['keptTickets'] ?? null;
        $out = [
            'booking' => $summary,
            'canCancel' => false,
            'canRefund' => false,
            'modes' => [],
            'flights' => [],
            'refund' => null,
            // Flights already cancelled, tickets still open (worth their fare).
            'keptTickets' => $kept,
            'ticketValue' => self::hasTickets($b) ? self::ticketValue($b) : null,
            'unavailableReason' => $reason,
            'note' => null,
        ];
        if ($reason !== null) {
            return $out;
        }

        if (self::hasTickets($b)) {
            $info = $b['MiscInfo']['CancelInfo'] ?? [];
            $modes = [];
            $why = [];
            $notes = [];

            // Cancel and refund to a credit voucher.
            $quote = null;
            if (empty($info['CanRefund'])) {
                $why[] = 'These tickets cannot be refunded online.';
            } elseif (! $this->refundToVoucherEnabled()) {
                $why[] = 'Refunds to a credit voucher are not available online yet.';
            } else {
                try {
                    $quote = $this->refundQuote($customer, $pnr, $b);
                    $modes[] = 'refund';
                    $notes[] = self::REFUND_NOTE;
                } catch (ApiException $e) {
                    if ($e->status !== 409) {
                        throw $e;
                    }
                    $why[] = $e->getMessage();
                }
            }

            // Cancel flights only, keep the ticket value.
            $flights = [];
            if ($kept === null && self::keepTicketEnabled() && ! empty($info['CanCancel'])) {
                try {
                    $flights = self::flightsFor($b, $this->cancellableSegments($customer, $pnr));
                } catch (ApiException $e) {
                    if ($e->status !== 409) {
                        throw $e;
                    }
                }
                if ($flights) {
                    $modes[] = 'keep_ticket';
                    $notes[] = self::KEEP_TICKET_NOTE;
                }
            }

            if (! $modes) {
                $out['unavailableReason'] = trim(($kept ? 'Your flights are already cancelled and your tickets keep their value: you can change to another flight. ' : '')
                    .implode(' ', $why ?: ['This booking cannot be cancelled online.']).' You can send a refund request instead.');

                return $out;
            }

            return [
                ...$out,
                'canCancel' => in_array('keep_ticket', $modes, true),
                'canRefund' => in_array('refund', $modes, true),
                'modes' => $modes,
                'flights' => $flights ?: ($quote['flights'] ?? []),
                'refund' => $quote,
                'note' => implode(' ', $notes),
            ];
        }

        // Unpaid: the whole booking, or some of its flights.
        $refs = $this->cancellableSegments($customer, $pnr);
        $flights = self::flightsFor($b, $refs);
        if (! $flights) {
            $out['unavailableReason'] = 'There are no flights left to cancel on this booking.';

            return $out;
        }

        return [
            ...$out,
            'canCancel' => true,
            'modes' => count($flights) > 1 ? ['booking', 'flights'] : ['booking'],
            'flights' => $flights,
            'note' => 'Nothing has been paid on this booking, so nothing is charged or refunded. Cancelled flights are released and cannot be restored.',
        ];
    }

    // ------------------------------------------------------------ cancel

    /**
     * Cancels as asked. For a refund, $expectedRefund is the amount the
     * customer was shown: if the airline now quotes another amount, nothing
     * is done and the new quote is returned (409 refund_changed).
     *
     * @param  list<string>  $segmentRefs  for mode "flights"
     * @return array{booking: array, cancellation: array, vouchers: list<array>, message: string}
     */
    public function cancel(object $customer, string $pnr, string $mode, array $segmentRefs = [], ?float $expectedRefund = null, ?string $ip = null): array
    {
        if (! in_array($mode, self::MODES, true)) {
            throw ApiException::invalid('Choose how to cancel.');
        }
        $lock = Cache::lock("naoero:cancel:{$customer->zenith_environment}:{$pnr}", 120);
        if (! $lock->get()) {
            throw new ApiException(409, 'cancel_in_progress', 'This booking is already being cancelled. Please wait a moment and refresh.');
        }
        try {
            return $this->cancelLocked($customer, $pnr, $mode, $segmentRefs, $expectedRefund, $ip);
        } finally {
            $lock->release();
        }
    }

    private function cancelLocked(object $customer, string $pnr, string $mode, array $segmentRefs, ?float $expectedRefund, ?string $ip): array
    {
        $owned = $this->bookings->ownedBooking($customer, $pnr);
        [$b, $summary] = $this->live($customer, $pnr);
        if (($reason = $this->blocker($b, $summary)) !== null) {
            throw new ApiException(409, 'not_cancellable', $reason);
        }
        $ticketed = self::hasTickets($b);
        $paidMode = in_array($mode, ['refund', 'keep_ticket'], true);
        if ($paidMode && ! $ticketed) {
            throw new ApiException(409, 'nothing_to_refund', 'Nothing has been paid on this booking. Cancel the booking instead.');
        }
        if (! $paidMode && $ticketed) {
            throw new ApiException(409, 'refund_required', self::keepTicketEnabled()
                ? 'This booking is paid: cancel it with a refund, or cancel the flights only and keep the ticket value.'
                : 'This booking is paid: cancel it with a refund instead.');
        }
        if ($mode === 'keep_ticket') {
            if (! self::keepTicketEnabled()) {
                throw new ApiException(403, 'keep_ticket_not_offered', 'Cancelling flights while keeping the tickets is not offered online. Please contact Naoero Airlines.');
            }
            if (! empty($summary['keptTickets'])) {
                throw new ApiException(409, 'not_cancellable', 'Your flights are already cancelled and your tickets keep their value.');
            }
            if (empty($b['MiscInfo']['CancelInfo']['CanCancel'])) {
                throw new ApiException(409, 'not_cancellable', 'The flights on this booking cannot be cancelled online.');
            }
        }

        $quote = null;
        if ($mode === 'refund') {
            if (empty($b['MiscInfo']['CancelInfo']['CanRefund'])) {
                throw new ApiException(409, 'not_refundable', 'These tickets cannot be refunded online. You can send a refund request instead.');
            }
            if (! $this->refundToVoucherEnabled()) {
                throw new ApiException(409, 'refund_not_enabled', 'Refunds to a credit voucher are not available online yet. You can send a refund request instead.');
            }
            $quote = $this->refundQuote($customer, $pnr, $b);
            if ($expectedRefund === null) {
                throw ApiException::invalid('Confirm the refund amount shown.');
            }
            if (abs($quote['refundAmount'] - $expectedRefund) > 0.005) {
                throw new ApiException(409, 'refund_changed', 'The refund amount has changed to '.number_format($quote['refundAmount'], 2)." {$quote['currency']}. Please check it and confirm again.", extra: ['refund' => $quote]);
            }
            $refs = $quote['segmentRefs'];
            $settings = ['RefundSettings' => [
                'RefundAllTaxes' => false,
                'IgnoreReward' => false,
                // Zenith creates the voucher on the customer who owns the booking.
                'RefundFops' => $quote['refundAmount'] > 0 ? [['Code' => 'MCO', 'Amount' => $quote['refundAmount'], 'MCOInfo' => new stdClass]] : [],
            ]];
        } else {
            $cancellable = $this->cancellableSegments($customer, $pnr);
            // "flights" needs a choice; "keep_ticket" may name some (one leg), else all.
            if ($mode === 'flights' || ($mode === 'keep_ticket' && $segmentRefs !== [])) {
                $refs = array_values(array_unique(array_map('strval', $segmentRefs)));
                if (! $refs) {
                    throw ApiException::invalid('Choose the flights to cancel.');
                }
                if (array_diff($refs, $cancellable)) {
                    throw new ApiException(409, 'flight_not_cancellable', 'One of these flights can no longer be cancelled. Please refresh.');
                }
            } else {
                $refs = $cancellable;
            }
            if (! $refs) {
                throw new ApiException(409, 'not_cancellable', 'There are no flights left to cancel on this booking.');
            }
            // All of them: an empty list tells Zenith to cancel every flight.
            $settings = ['CancelSegmentSettings' => ['SegmentReferencesToCancel' => array_diff($cancellable, $refs) === [] ? [] : $refs]];
        }

        $id = (string) Str::uuid();
        DB::table('booking_cancellations')->insert([
            'id' => $id,
            'customer_id' => $customer->id,
            'booking_id' => $owned->id,
            'zenith_environment' => $customer->zenith_environment,
            'pnr' => $pnr,
            'mode' => $mode,
            'segment_refs' => Js::encode(array_values($refs)),
            'currency' => $quote['currency'] ?? ($summary['currency'] ?: null),
            'refund_amount' => $quote['refundAmount'] ?? null,
            'penalty_amount' => $quote['penaltyAmount'] ?? null,
            'refund_method' => $mode === 'refund' ? 'voucher' : null,
            'status' => 'pending',
            'quote' => $quote === null ? null : Js::encode($quote),
            'ip' => $ip,
            'created_at' => Js::dbTime(),
        ]);
        $this->log->info('booking', 'cancellation started', ['pnr' => $pnr, 'mode' => $mode, 'segments' => count($refs), 'refund' => $quote['refundAmount'] ?? null]);

        try {
            $res = $this->zenith->call('Cancel', [...$this->id($customer, $pnr), 'CancelSettings' => $settings]);
        } catch (ZenithBusinessException $e) {
            $this->finish($id, 'failed', $e->zenithCode ?: 'refused', $e->getMessage());
            $this->log->warn('booking', 'cancellation refused', ['pnr' => $pnr, 'mode' => $mode, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'cancel_refused', trim('The airline did not cancel this booking: '.$e->getMessage()));
        } catch (ZenithUnavailableException $e) {
            // No answer: the cancellation may or may not have been made. Look.
            return $this->afterNoAnswer($customer, $pnr, $id, $mode, $refs, $e);
        }

        return $this->done($customer, $pnr, $id, $mode, $quote, is_array($res['Booking'] ?? null) ? $res['Booking'] : null, $res['FareInfo'] ?? null);
    }

    /** Zenith did not answer Cancel: the booking tells whether it was made. */
    private function afterNoAnswer(object $customer, string $pnr, string $id, string $mode, array $refs, Throwable $e): array
    {
        try {
            $b = $this->bookings->loadZenithBooking($pnr, $customer);
        } catch (Throwable) {
            $this->finish($id, 'unknown', 'no_answer', $e->getMessage());
            $this->log->error('booking', 'cancellation outcome unknown', ['pnr' => $pnr, 'mode' => $mode]);

            throw new ApiException(503, 'cancel_unconfirmed', 'The airline did not confirm the cancellation. Please refresh the booking in a few minutes before trying again.');
        }
        // Made when none of the flights to cancel is still held (a refund cancels them too).
        $held = array_map(fn ($s) => (string) $s['Ref'], array_filter(Js::list($b['Segments'] ?? null),
            fn ($s) => ! in_array($s['BookingClass']['StatusCode'] ?? '', ['HX', 'XX', 'UN', 'UC', 'NO'], true)));
        if ($refs === []) {
            $this->finish($id, 'unknown', 'no_answer', $e->getMessage());

            throw new ApiException(503, 'cancel_unconfirmed', 'The airline did not confirm the cancellation. Please refresh the booking in a few minutes before trying again.');
        }
        if (array_intersect($refs, $held) !== []) {
            $this->bookings->saveBooking($customer, $b);
            $this->finish($id, 'failed', 'no_answer', $e->getMessage());

            throw new ApiException(503, 'unavailable', 'The airline is not answering, and nothing was cancelled. Please try again shortly.');
        }

        return $this->done($customer, $pnr, $id, $mode, null, $b, null);
    }

    private function done(object $customer, string $pnr, string $id, string $mode, ?array $quote, ?array $booking, mixed $fareInfo): array
    {
        $booking ??= $this->bookings->loadZenithBooking($pnr, $customer);
        $summary = $this->bookings->saveBooking($customer, $booking);
        $vouchers = $mode === 'refund' ? $this->vouchers->forCustomerQuietly($customer) : [];
        DB::table('booking_cancellations')->where('id', $id)->update([
            'status' => 'done',
            'result' => is_array($fareInfo) ? Js::encode($fareInfo) : null,
            'vouchers' => $mode === 'refund' ? Js::encode($vouchers) : null,
            'completed_at' => Js::dbTime(),
        ]);
        $this->log->info('booking', 'cancellation made', ['pnr' => $pnr, 'mode' => $mode, 'status' => $summary['status']]);

        $amount = $quote['refundAmount'] ?? (float) DB::table('booking_cancellations')->where('id', $id)->value('refund_amount');
        $currency = $quote['currency'] ?? $summary['currency'];
        $message = match ($mode) {
            'refund' => $amount > 0
                ? 'Your booking is cancelled. '.number_format($amount, 2)." {$currency} has been added to a credit voucher on your account."
                : 'Your booking is cancelled. The fare rules leave nothing to refund.',
            'flights' => 'The flights you chose are cancelled.',
            'keep_ticket' => ($summary['keptTickets'] ?? null)
                ? 'Your flights are cancelled. Your tickets ('.number_format($summary['keptTickets']['amount'], 2)." {$summary['keptTickets']['currency']}) stay valid: change to another flight from Manage booking, or ask for a refund."
                : 'The flights you chose are cancelled. Your tickets stay valid for a flight change or a refund.',
            default => 'Your booking is cancelled.',
        };
        $this->notify($customer, $pnr, match ($mode) {
            'refund' => 'booking_refunded',
            'keep_ticket' => 'flights_cancelled',
            default => 'booking_cancelled',
        }, $message);

        return [
            'booking' => $summary,
            'cancellation' => self::format(DB::table('booking_cancellations')->where('id', $id)->first()),
            'vouchers' => $vouchers,
            'message' => $message,
        ];
    }

    /** The cancellations made on a booking, newest first. */
    public function history(object $customer, string $pnr): array
    {
        return DB::table('booking_cancellations')->where('customer_id', $customer->id)->where('pnr', $pnr)
            ->orderByDesc('created_at')->get()->map(fn ($r) => self::format($r))->all();
    }

    // ------------------------------------------------------------ helpers

    /** @return array{0: array, 1: array} the live booking, saved */
    private function live(object $customer, string $pnr): array
    {
        $this->bookings->ownedBooking($customer, $pnr);
        $b = $this->bookings->loadZenithBooking($pnr, $customer);

        return [$b, $this->bookings->saveBooking($customer, $b)];
    }

    /** Why the booking can't be cancelled online at all, or null. */
    private function blocker(array $b, array $summary): ?string
    {
        return match (true) {
            BookingSummary::isServiceBooking($b) => 'Services cannot be cancelled online. Please contact Naoero Airlines.',
            // Flights cancelled with the tickets kept: the tickets can still be refunded.
            $summary['status'] === 'cancelled' && empty($summary['keptTickets']) => 'This booking is already cancelled.',
            $summary['status'] === 'expired' => 'This booking has expired: its flights are already released.',
            ! empty($b['MiscInfo']['IsPendingExchange']) => 'A flight change is waiting for payment. Pay for it or cancel it first.',
            ! empty($b['MiscInfo']['HasPendingRefund']) => 'A refund is already being processed on this booking.',
            empty($b['MiscInfo']['CancelInfo']['CanCancel']) && empty($b['MiscInfo']['CancelInfo']['CanRefund']) => 'This booking cannot be cancelled online. Please contact Naoero Airlines.',
            default => null,
        };
    }

    /** What the open tickets are worth (their fares), for "keep the ticket value". */
    private static function ticketValue(array $b): ?array
    {
        return BookingSummary::openTicketValue($b);
    }

    private static function hasTickets(array $b): bool
    {
        return array_filter(Js::list($b['TicketInfo']['ETTickets'] ?? null), fn ($t) => ! empty($t['DocumentNumber'])) !== [];
    }

    private function id(object $customer, string $pnr): array
    {
        return ['UniqueID' => ['TypeCode' => 'PnrCode', 'ID' => $pnr], 'Verification' => $this->bookings->verification($customer, $pnr)];
    }

    /** @return list<string> the flights Zenith would cancel */
    private function cancellableSegments(object $customer, string $pnr): array
    {
        $res = $this->prepare($customer, $pnr, ['CancelSegmentSettings' => new stdClass]);

        return array_values(array_map('strval', Js::list($res['SegmentReferencesToCancel'] ?? null)));
    }

    private function prepare(object $customer, string $pnr, array $settings): array
    {
        try {
            return $this->zenith->call('PrepareCancel', [...$this->id($customer, $pnr), 'CancelSettings' => $settings]);
        } catch (ZenithBusinessException $e) {
            if (preg_match('/quota/i', $e->zenithCode)) {
                throw new ApiException(429, 'busy', 'The airline system is busy. Please try again in a minute.');
            }
            $this->log->warn('booking', 'PrepareCancel refused', ['pnr' => $pnr, 'code' => $e->zenithCode, 'message' => $e->getMessage()]);

            throw new ApiException(409, 'not_cancellable', trim('This booking cannot be cancelled online: '.$e->getMessage()));
        } catch (ZenithUnavailableException) {
            throw new ApiException(503, 'unavailable', 'This is temporarily unavailable. Please try again shortly.');
        }
    }

    /**
     * What a refund gives back, from PrepareCancel: the total to the voucher,
     * each ticket's share, and the penalties the fare rules keep.
     *
     * @return array<string, mixed>
     */
    private function refundQuote(object $customer, string $pnr, array $b): array
    {
        $res = $this->prepare($customer, $pnr, ['RefundSettings' => ['RefundAllTaxes' => false, 'IgnoreReward' => false]]);
        $fare = is_array($res['FareInfo'] ?? null) ? $res['FareInfo'] : [];
        $currency = BookingSummary::currency($b);
        $names = BookingService::passengerNames($b);
        $owners = [];
        foreach (Js::list($b['TicketInfo']['ETTickets'] ?? null) as $t) {
            $owners[(string) ($t['DocumentNumber'] ?? '')] = $names[(string) ($t['RefPassenger'] ?? '')] ?? null;
        }

        $tickets = [];
        foreach (Js::list($fare['ElectronicDocumentsToCancel'] ?? null) as $d) {
            $number = (string) ($d['DocumentNumber'] ?? '');
            $tickets[$number] ??= ['ticketNumber' => $number, 'passengerName' => $owners[$number] ?? null, 'amount' => 0.0, 'taxes' => 0.0];
            $tickets[$number]['amount'] = Js::round2($tickets[$number]['amount'] + (float) ($d['SaleCurrencyAmountToRefund']['TotalAmount'] ?? 0));
            $tickets[$number]['taxes'] = Js::round2($tickets[$number]['taxes'] + (float) ($d['SaleCurrencyAmountToRefund']['TaxAmount'] ?? 0));
        }
        $penalties = [];
        foreach (Js::list($fare['EMDTicketFares'] ?? null) as $e) {
            $penalties[] = [
                'label' => trim((string) ($e['Label'] ?? '')) ?: 'Cancellation fee',
                'passengerName' => $names[(string) ($e['RefPassenger'] ?? '')] ?? null,
                'amount' => Js::round2($e['SaleCurrencyAmount']['TotalAmount'] ?? 0),
            ];
        }
        $refund = Js::round2($fare['SaleCurrencyAmountToRefund']['TotalAmount'] ?? array_sum(array_column($tickets, 'amount')));
        $refs = array_values(array_map('strval', Js::list($res['SegmentReferencesToCancel'] ?? null)));

        return [
            'currency' => $currency,
            'refundAmount' => max(0, $refund),
            'penaltyAmount' => Js::round2(array_sum(array_column($penalties, 'amount'))),
            'paidAmount' => Js::round2($b['FareInfo']['SaleCurrencyAmountTotal']['TotalAmount'] ?? 0),
            'method' => 'voucher',
            'tickets' => array_values($tickets),
            'penalties' => $penalties,
            'segmentRefs' => $refs,
            'flights' => self::flightsFor($b, $refs),
        ];
    }

    /** @return list<array> the booking's flights for these segment refs, with their ref */
    private static function flightsFor(array $b, array $refs): array
    {
        $out = [];
        foreach (Js::list($b['Segments'] ?? null) as $s) {
            if (in_array((string) ($s['Ref'] ?? ''), $refs, true) && isset($s['OriginCode'], $s['DestinationCode'])) {
                $out[] = ['segmentRef' => (string) $s['Ref'], ...BookingSummary::flight($s)];
            }
        }

        return $out;
    }

    private function finish(string $id, string $status, string $code, string $message): void
    {
        DB::table('booking_cancellations')->where('id', $id)->update([
            'status' => $status, 'error_code' => mb_substr($code, 0, 60), 'error_message' => mb_substr($message, 0, 2000), 'completed_at' => Js::dbTime(),
        ]);
    }

    /** A notice in the app and on the website, like the airline's flight notices. */
    private function notify(object $customer, string $pnr, string $type, string $body): void
    {
        app(CustomerNotices::class)->send($customer->id, $type, match ($type) {
            'booking_refunded' => 'Your booking was cancelled and refunded',
            'flights_cancelled' => 'Your flights were cancelled; your tickets are kept',
            default => 'Your booking was cancelled',
        }, $body, ['event' => 'Cancel', 'changes' => []], pnr: $pnr);
    }

    /** @return array<string, mixed> */
    private static function format(object $r): array
    {
        return [
            'id' => $r->id,
            'mode' => $r->mode,
            'status' => $r->status,
            'segmentRefs' => Js::list(Js::decode($r->segment_refs)),
            'currency' => $r->currency,
            'refundAmount' => $r->refund_amount === null ? null : Js::round2($r->refund_amount),
            'penaltyAmount' => $r->penalty_amount === null ? null : Js::round2($r->penalty_amount),
            'refundMethod' => $r->refund_method,
            'error' => $r->error_message,
            'createdAt' => Js::iso(CarbonImmutable::parse($r->created_at)),
            'completedAt' => $r->completed_at ? Js::iso(CarbonImmutable::parse($r->completed_at)) : null,
        ];
    }
}
