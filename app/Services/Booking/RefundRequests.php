<?php

namespace App\Services\Booking;

use App\Exceptions\ApiException;
use App\Services\Notifications\BookingNotices;
use App\Services\Support\Js;
use App\Services\Support\Phones;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Refund requests for flight bookings. Naoero Airlines tickets are
 * non-refundable, and the airline does not allow refund requests through
 * Zenith, so customers send a request here and staff decide case by case in
 * the admin panel. Nothing is refunded automatically.
 */
final class RefundRequests
{
    /** Why the customer is asking (code => label). */
    public const REASONS = [
        'medical' => 'Illness or a medical reason',
        'bereavement' => 'Death of a family member',
        'airline_change' => 'The airline changed or cancelled my flight',
        'travel_documents' => 'Visa or entry refused',
        'duplicate' => 'I booked the same trip twice by mistake',
        'other' => 'Another reason',
    ];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'in_review' => 'In review',
        'approved' => 'Approved',
        'declined' => 'Declined',
        'closed' => 'Closed',
    ];

    /** Still waiting for a decision: only one per booking at a time. */
    public const OPEN = ['submitted', 'in_review'];

    public const POLICY = 'Naoero Airlines tickets are non-refundable. You can still ask for a refund in exceptional circumstances: our team reviews each request and replies within 10 business days. A request is not a guarantee of a refund, and any refund approved may be reduced by fees set by the fare rules.';

    /** The form's settings, the booking's passengers and its requests. */
    public function forBooking(object $customer, string $pnr): array
    {
        $booking = $this->booking($customer, $pnr);
        $requests = DB::table('refund_requests')->where('customer_id', $customer->id)->where('pnr', $pnr)
            ->orderByDesc('created_at')->get()->map(fn ($r) => self::format($r))->all();
        $open = collect($requests)->first(fn ($r) => in_array($r['status'], self::OPEN, true));

        return [
            'policy' => self::POLICY,
            'reasons' => array_map(fn ($code, $label) => ['code' => $code, 'label' => $label], array_keys(self::REASONS), self::REASONS),
            'passengers' => self::passengers($booking),
            'canRequest' => $booking->status === 'ticketed' && ! $open,
            'unavailableReason' => $booking->status !== 'ticketed'
                ? 'Refund requests can be made once a booking is paid and ticketed.'
                : ($open ? "Your request {$open['reference']} is being reviewed." : null),
            'requests' => $requests,
        ];
    }

    /**
     * @param  array{reason: string, details: string, passengerRefs: list<string>, contactPhone?: ?string}  $input
     */
    public function submit(object $customer, string $pnr, array $input, string $channel): array
    {
        $booking = $this->booking($customer, $pnr);
        if ($booking->status !== 'ticketed') {
            throw new ApiException(409, 'not_ticketed', 'Refund requests can be made once a booking is paid and ticketed.');
        }
        $open = DB::table('refund_requests')->where('customer_id', $customer->id)->where('pnr', $pnr)
            ->whereIn('status', self::OPEN)->value('reference');
        if ($open) {
            throw new ApiException(409, 'refund_request_open', "Your request {$open} for this booking is still being reviewed. We will reply to you there.");
        }

        $passengers = collect(self::passengers($booking))->keyBy('ref');
        $refs = array_values(array_unique($input['passengerRefs']));
        foreach ($refs as $ref) {
            if (! $passengers->has($ref)) {
                throw ApiException::invalid('Choose passengers from this booking.');
            }
        }

        $now = Js::dbTime();
        $id = (string) Str::uuid();
        $reference = self::newReference();
        $who = DB::table('customers')->where('id', $customer->id)->first(['first_name', 'last_name', 'zenith_login', 'email']);
        DB::table('refund_requests')->insert([
            'id' => $id,
            'reference' => $reference,
            'pnr' => $pnr,
            'status' => 'submitted',
            'customer_name' => trim(($who->first_name ?? '').' '.($who->last_name ?? '')) ?: null,
            'customer_email' => $who->email ?? $who->zenith_login ?? null,
            'passenger_names' => implode(', ', array_map(fn ($r) => $passengers[$r]['name'], $refs)),
            'reason' => $input['reason'],
            'details' => $input['details'],
            'contact_phone' => $input['contactPhone'] ?? null,
            'contact_phone_country' => Phones::parse($input['contactPhone'] ?? null, $input['contactPhoneCountry'] ?? null)['country'] ?? null,
            'channel' => $channel,
            'booking_total' => $booking->total_amount,
            'currency' => $booking->currency,
            'passenger_refs' => Js::encode($refs),
            'customer_id' => $customer->id,
            'booking_id' => $booking->id,
            'policy_accepted_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        app(BookingNotices::class)->refundRequested($customer, $pnr, $reference);

        return self::format(DB::table('refund_requests')->where('id', $id)->first());
    }

    /** What the customer sees (never the internal note). */
    public static function format(object $r): array
    {
        return [
            'reference' => $r->reference,
            'pnr' => $r->pnr,
            'status' => $r->status,
            'statusLabel' => self::STATUSES[$r->status] ?? ucfirst($r->status),
            'reason' => $r->reason,
            'reasonLabel' => self::REASONS[$r->reason] ?? $r->reason,
            'details' => $r->details,
            'passengers' => $r->passenger_names ? explode(', ', $r->passenger_names) : [],
            'contactPhone' => $r->contact_phone,
            'customerMessage' => $r->customer_message,
            'createdAt' => Js::iso($r->created_at),
            'updatedAt' => Js::iso($r->updated_at),
            'decidedAt' => $r->decided_at ? Js::iso($r->decided_at) : null,
        ];
    }

    /** The customer's own booking, as stored (status kept current by the booking pages). */
    private function booking(object $customer, string $pnr): object
    {
        $booking = DB::table('bookings')->where('customer_id', $customer->id)->where('pnr', $pnr)->whereNull('hidden_at')
            ->first(['id', 'pnr', 'status', 'summary', 'total_amount', 'currency']);
        if (! $booking) {
            throw new ApiException(404, 'booking_not_found', 'We could not find that booking in your account.');
        }

        return $booking;
    }

    /** @return list<array{ref: string, name: string, type: ?string}> */
    private static function passengers(object $booking): array
    {
        $summary = json_decode((string) $booking->summary, true) ?: [];

        return array_values(array_map(fn ($p) => [
            'ref' => (string) $p['ref'],
            'name' => (string) ($p['name'] ?? ''),
            'type' => $p['type'] ?? null,
        ], $summary['passengers'] ?? []));
    }

    private static function newReference(): string
    {
        do {
            // No look-alike characters (0/O, 1/I/L) in the part customers type.
            $code = strtr(strtoupper(Str::random(6)), ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);
            $reference = 'RR-'.now()->format('ymd').'-'.$code;
        } while (DB::table('refund_requests')->where('reference', $reference)->exists());

        return $reference;
    }
}
