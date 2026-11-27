<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Booking\BookingExtras;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Flight Sales & Bookings: every booking customers made through Zenith
 * (PNR, flights, passengers, tickets), its payment attempts and status.
 */
class FlightBookingController extends Controller
{
    public const STATUSES = [
        'pending_payment' => 'On hold (unpaid)',
        'ticketed' => 'Ticketed',
        'expired' => 'Expired',
        'cancelled' => 'Cancelled',
    ];

    private function allow(Request $request): void
    {
        abort_unless($request->user()?->can('bookings.view'), 403, 'You do not have access to flight bookings.');
    }

    private static function now(): string
    {
        return CarbonImmutable::now()->utc()->format('Y-m-d H:i:s.uP');
    }

    public function index(Request $request): View|StreamedResponse
    {
        $this->allow($request);
        $query = DB::table('bookings as b')->join('customers as c', 'c.id', '=', 'b.customer_id')
            ->select('b.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email')
            ->selectSub(DB::table('booking_payments')->whereColumn('booking_id', 'b.id')->selectRaw('count(*)'), 'payment_attempts')
            ->orderByDesc('b.created_at');

        // Unpaid bookings past their time limit were released by the airline.
        match ($request->input('status')) {
            'pending_payment' => $query->where('b.status', 'pending_payment')->where(fn ($q) => $q->whereNull('b.time_limit')->orWhere('b.time_limit', '>', self::now())),
            'expired' => $query->where(fn ($q) => $q->where('b.status', 'expired')->orWhere(fn ($p) => $p->where('b.status', 'pending_payment')->where('b.time_limit', '<=', self::now()))),
            'ticketed', 'cancelled' => $query->where('b.status', $request->input('status')),
            default => null,
        };
        if ($from = $request->input('from')) {
            $query->where('b.created_at', '>=', CarbonImmutable::parse($from)->startOfDay()->utc()->format('Y-m-d H:i:s.uP'));
        }
        if ($to = $request->input('to')) {
            $query->where('b.created_at', '<=', CarbonImmutable::parse($to)->endOfDay()->utc()->format('Y-m-d H:i:s.uP'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('upper(b.pnr) = ?', [strtoupper($search)])
                ->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like])
                ->orWhereRaw("lower(coalesce(c.first_name, '') || ' ' || coalesce(c.last_name, '')) like ?", [$like])
                ->orWhereRaw('lower(cast(b.summary as text)) like ?', [$like])
                // A phone number, typed any way ("+91 98997 65000", "9899765000").
                ->when(strlen(preg_replace('/\D/', '', $search)) >= 6, fn ($w) => $w->orWhere('b.contact_phone', 'like', '%'.preg_replace('/\D/', '', $search).'%')));
        }

        if ($request->input('export') === 'csv') {
            $rows = $query->limit(20000)->get();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['PNR', 'Booked (UTC)', 'Status', 'Customer', 'Email', 'Flights', 'Passengers', 'Total', 'Due', 'Currency', 'Pay by (UTC)', 'Tickets']);
                foreach ($rows as $b) {
                    $s = json_decode($b->summary, true) ?: [];
                    fputcsv($out, [
                        $b->pnr, $b->created_at, self::status($b), trim("{$b->first_name} {$b->last_name}"), $b->customer_main_email ?? $b->zenith_login,
                        collect($s['flights'] ?? [])->map(fn ($f) => "{$f['flightNumber']} {$f['origin']}-{$f['destination']} {$f['departure']}")->implode('; '),
                        collect($s['passengers'] ?? [])->pluck('name')->implode('; '),
                        $b->total_amount, $b->amount_due, $b->currency, $b->time_limit, implode(' ', json_decode($b->ticket_numbers, true) ?: []),
                    ]);
                }
                fclose($out);
            }, 'flight-bookings-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $since = CarbonImmutable::now()->subDays(30)->utc()->format('Y-m-d H:i:s.uP');
        $payments = DB::table('booking_payments')->where('created_at', '>=', $since)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('admin.flight_bookings.index', [
            'bookings' => $query->paginate(25)->withQueryString(),
            'statuses' => self::STATUSES,
            'stats' => [
                'total' => DB::table('bookings')->count(),
                'ticketed' => DB::table('bookings')->where('status', 'ticketed')->count(),
                'revenue' => (float) DB::table('bookings')->where('status', 'ticketed')->sum('total_amount'),
                'onHold' => DB::table('bookings')->where('status', 'pending_payment')->where(fn ($q) => $q->whereNull('time_limit')->orWhere('time_limit', '>', self::now()))->count(),
                'onHoldValue' => (float) DB::table('bookings')->where('status', 'pending_payment')->where('time_limit', '>', self::now())->sum('amount_due'),
                'expired' => DB::table('bookings')->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($p) => $p->where('status', 'pending_payment')->where('time_limit', '<=', self::now())))->count(),
                'last30' => DB::table('bookings')->where('created_at', '>=', $since)->count(),
                'payments30' => $payments->all(),
            ],
        ]);
    }

    /** The status as staff should read it: unpaid past the time limit is expired. */
    public static function status(object $b): string
    {
        return $b->status === 'pending_payment' && $b->time_limit && CarbonImmutable::parse($b->time_limit)->isPast() ? 'expired' : $b->status;
    }

    public function show(Request $request, string $booking): View
    {
        $this->allow($request);
        abort_unless(Str::isUuid($booking), 404);
        $b = DB::table('bookings as b')->join('customers as c', 'c.id', '=', 'b.customer_id')
            ->where('b.id', $booking)->select('b.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'c.id as customer_id', 'c.phone_e164 as customer_phone')->first();
        abort_unless($b, 404);

        return view('admin.flight_bookings.show', [
            'b' => $b,
            'summary' => json_decode($b->summary, true) ?: [],
            // Read from the stored Zenith booking, so older bookings show them too.
            'extras' => BookingExtras::current(json_decode((string) $b->zenith_booking, true) ?: []),
            'status' => self::status($b),
            'payments' => DB::table('booking_payments')->where('booking_id', $b->id)->orderByDesc('created_at')->get(),
            // Cancellations and refunds the customer made in the app or on the website.
            'cancellations' => DB::table('booking_cancellations')->where('booking_id', $b->id)->orderByDesc('created_at')->get(),
            'events' => DB::table('auth_events')->where('customer_id', $b->customer_id)->whereIn('event', ['booking_create', 'booking_modify', 'booking_find', 'booking_remove', 'payment_start', 'payment_result'])
                ->where('detail', 'like', '%'.$b->pnr.'%')->orderByDesc('created_at')->limit(50)->get(),
        ]);
    }
}
