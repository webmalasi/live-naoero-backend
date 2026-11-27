<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Booking\RefundRequests;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Refund Requests: customers ask for a refund on a non-refundable ticket and
 * staff decide. Viewing needs bookings.view; deciding needs bookings.manage.
 * Refunds themselves are made with the airline (Zenith back office / NAB),
 * not from here.
 */
class RefundRequestController extends Controller
{
    private function allow(Request $request, string $permission = 'bookings.view'): void
    {
        abort_unless($request->user()?->can($permission), 403, 'You do not have access to refund requests.');
    }

    public function index(Request $request): View
    {
        $this->allow($request);
        $query = DB::table('refund_requests')->orderByRaw("case when status in ('submitted', 'in_review') then 0 else 1 end")->orderByDesc('created_at');
        if (array_key_exists((string) $request->input('status'), RefundRequests::STATUSES)) {
            $query->where('status', $request->input('status'));
        } elseif ($request->input('status') === 'open') {
            $query->whereIn('status', RefundRequests::OPEN);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('upper(pnr) = ?', [strtoupper($search)])
                ->orWhereRaw('upper(reference) = ?', [strtoupper($search)])
                ->orWhereRaw('lower(coalesce(customer_email, \'\')) like ?', [$like])
                ->orWhereRaw('lower(coalesce(customer_name, \'\')) like ?', [$like])
                ->orWhereRaw('lower(coalesce(passenger_names, \'\')) like ?', [$like]));
        }
        $counts = DB::table('refund_requests')->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('admin.refund_requests.index', [
            'requests' => $query->paginate(25)->withQueryString(),
            'statuses' => RefundRequests::STATUSES,
            'reasons' => RefundRequests::REASONS,
            'counts' => $counts->all(),
        ]);
    }

    public function show(Request $request, string $refundRequest): View
    {
        $this->allow($request);
        abort_unless(Str::isUuid($refundRequest), 404);
        $r = DB::table('refund_requests')->where('id', $refundRequest)->first();
        abort_unless($r, 404);
        $booking = $r->booking_id ? DB::table('bookings')->where('id', $r->booking_id)->first() : null;

        return view('admin.refund_requests.show', [
            'r' => $r,
            'booking' => $booking,
            'summary' => $booking ? (json_decode($booking->summary, true) ?: []) : [],
            'statuses' => RefundRequests::STATUSES,
            'reasons' => RefundRequests::REASONS,
            'handler' => $r->handled_by ? DB::table('users')->where('id', $r->handled_by)->value('name') : null,
            'others' => DB::table('refund_requests')->where('customer_id', $r->customer_id)->where('id', '!=', $r->id)->orderByDesc('created_at')->limit(10)->get(),
        ]);
    }

    /** Status, the reply the customer sees, and an internal note. */
    public function update(Request $request, string $refundRequest): RedirectResponse
    {
        $this->allow($request, 'bookings.manage');
        abort_unless(Str::isUuid($refundRequest), 404);
        $r = DB::table('refund_requests')->where('id', $refundRequest)->first();
        abort_unless($r, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(RefundRequests::STATUSES))],
            'customer_message' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($request->input('status'), ['approved', 'declined'], true))],
            'staff_note' => ['nullable', 'string', 'max:4000'],
        ], [
            'customer_message.required' => 'Write a reply for the customer when approving or declining.',
        ]);

        $decided = in_array($data['status'], ['approved', 'declined', 'closed'], true);
        DB::table('refund_requests')->where('id', $r->id)->update([
            'status' => $data['status'],
            'customer_message' => $data['customer_message'] ?? null,
            'staff_note' => $data['staff_note'] ?? null,
            'handled_by' => $request->user()->id,
            'decided_at' => $decided ? ($r->decided_at ?? CarbonImmutable::now()->utc()->format('Y-m-d H:i:s.uP')) : null,
            'updated_at' => CarbonImmutable::now()->utc()->format('Y-m-d H:i:s.uP'),
        ]);
        ActivityLog::log('update', 'refund_requests', "Refund request {$r->reference} ({$r->pnr}): {$r->status} → {$data['status']}", [
            'reference' => $r->reference,
            'from' => $r->status,
            'to' => $data['status'],
        ]);

        return redirect()->route('admin.refund-requests.show', $r->id)->with('success', 'Refund request updated.');
    }
}
