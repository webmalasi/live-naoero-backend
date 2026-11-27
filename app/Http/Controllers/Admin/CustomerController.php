<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Auth\AccountDeletion;
use App\Services\Shop\GiftCardIssuer;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Users Directory: the public's accounts (customers who signed up on the
 * website or in the app, mirrored from Zenith), each with a full profile:
 * personal details and documents, flight bookings, shop orders, gift
 * cards, saved travellers, devices, sign-in history and activity.
 */
class CustomerController extends Controller
{
    private function allow(Request $request, string $permission = 'customers.view'): void
    {
        abort_unless($request->user()?->can($permission), 403, 'You do not have access to customer accounts.');
    }

    private static function since(int $days): string
    {
        return CarbonImmutable::now()->subDays($days)->utc()->format('Y-m-d H:i:s.uP');
    }

    public function index(Request $request): View|StreamedResponse
    {
        $this->allow($request);
        $query = DB::table('customers as c')
            ->select('c.*')
            ->selectSub(DB::table('customer_phones')->whereColumn('customer_id', 'c.id')->orderBy('position')->limit(1)->selectRaw('coalesce(e164, phone_number)'), 'phone')
            ->selectSub(DB::table('bookings')->whereColumn('customer_id', 'c.id')->selectRaw('count(*)'), 'bookings_count')
            ->selectSub(DB::table('shop_orders')->whereColumn('customer_id', 'c.id')->selectRaw('count(*)'), 'orders_count')
            ->selectSub(DB::table('shop_orders')->whereColumn('customer_id', 'c.id')->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->selectRaw('coalesce(sum(total - refunded_amount), 0)'), 'shop_spend')
            ->selectSub(DB::table('customer_avatars')->whereColumn('customer_id', 'c.id')->selectRaw('count(*)'), 'has_avatar')
            ->selectSub(DB::table('account_deletion_requests')->whereColumn('customer_id', 'c.id')->where('status', 'pending')->selectRaw('count(*)'), 'deletion_pending');

        $status = $request->input('status');
        if (in_array($status, ['active', 'disabled'], true)) {
            $query->where('c.status', $status);
        } elseif ($status === 'removal_requested') {
            // Asked for deletion, waiting for staff.
            $query->whereExists(fn ($q) => $q->from('account_deletion_requests')->whereColumn('customer_id', 'c.id')->where('status', 'pending'));
        }
        match ($request->input('segment')) {
            'booked' => $query->whereExists(fn ($q) => $q->from('bookings')->whereColumn('customer_id', 'c.id')),
            'shoppers' => $query->whereExists(fn ($q) => $q->from('shop_orders')->whereColumn('customer_id', 'c.id')->whereIn('status', ['paid', 'partially_refunded', 'refunded'])),
            'new' => $query->where('c.created_at', '>=', self::since(30)),
            'active7' => $query->where('c.last_login_at', '>=', self::since(7)),
            default => null,
        };
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $digits = preg_replace('/\D/', '', $search);
            $query->where(function ($q) use ($like, $search, $digits) {
                $q->whereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(c.first_name, '') || ' ' || coalesce(c.last_name, '')) like ?", [$like])
                    ->orWhere('c.zenith_customer_ref', $search)
                    ->orWhereExists(fn ($b) => $b->from('bookings')->whereColumn('customer_id', 'c.id')->whereRaw('upper(pnr) = ?', [strtoupper($search)]));
                // Phone numbers: only when the search has enough digits to mean one.
                if (strlen($digits) >= 3) {
                    $q->orWhereExists(fn ($p) => $p->from('customer_phones')->whereColumn('customer_id', 'c.id')
                        ->where(fn ($w) => $w->where('phone_number', 'like', "%{$digits}%")->orWhere('e164', 'like', "%{$digits}%")));
                }
            });
        }
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'last_login' => $query->orderByRaw('c.last_login_at desc nulls last'),
            'name' => $query->orderBy('c.last_name')->orderBy('c.first_name'),
            'spend' => $query->orderByDesc('shop_spend'),
            default => $query->orderByDesc('c.created_at'),
        };

        if ($request->input('export') === 'csv') {
            $rows = $query->limit(50000)->get();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Name', 'Username', 'Email', 'Phone', 'Status', 'Zenith ref', 'Joined (UTC)', 'Last login (UTC)', 'Flight bookings', 'Shop orders', 'Shop spend (AUD)']);
                foreach ($rows as $c) {
                    fputcsv($out, [trim("{$c->first_name} {$c->last_name}"), $c->zenith_login, $c->email, $c->phone, $c->status, $c->zenith_customer_ref, $c->created_at, $c->last_login_at, $c->bookings_count, $c->orders_count, $c->shop_spend]);
                }
                fclose($out);
            }, 'users-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('admin.customers.index', [
            'customers' => $query->paginate(25)->withQueryString(),
            'stats' => [
                'total' => DB::table('customers')->count(),
                'active' => DB::table('customers')->where('status', 'active')->count(),
                'new30' => DB::table('customers')->where('created_at', '>=', self::since(30))->count(),
                'active7' => DB::table('customers')->where('last_login_at', '>=', self::since(7))->count(),
                'booked' => DB::table('bookings')->distinct()->count('customer_id'),
                'shoppers' => DB::table('shop_orders')->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->distinct()->count('customer_id'),
                'removal' => DB::table('account_deletion_requests')->where('status', 'pending')->count(),
                'disabled' => DB::table('customers')->where('status', 'disabled')->count(),
            ],
        ]);
    }

    public function show(Request $request, string $customer): View
    {
        $this->allow($request);
        abort_unless(Str::isUuid($customer), 404);
        $c = DB::table('customers')->where('id', $customer)->first();
        abort_unless($c, 404);
        $id = $c->id;

        $bookings = DB::table('bookings')->where('customer_id', $id)->orderByDesc('created_at')->get();
        $orders = DB::table('shop_orders')->where('customer_id', $id)->orderByDesc('created_at')->get();
        $sessions = DB::table('refresh_tokens')->where('customer_id', $id)->orderBy('created_at')->get()->groupBy('family_id')->map(function ($tokens, $family) {
            $last = $tokens->last();
            $active = $last->revoked_at === null && CarbonImmutable::parse($last->expires_at)->isFuture();

            return (object) [
                'family' => $family,
                'device' => $tokens->first()->device_label ?: 'Unnamed device',
                'ip' => $last->ip,
                'started' => $tokens->first()->created_at,
                'lastUsed' => $last->last_used_at ?? $last->created_at,
                'expires' => $last->expires_at,
                'refreshes' => $tokens->count() - 1,
                'active' => $active,
                'ended' => $active ? null : ($last->revoked_reason ?? 'expired'),
            ];
        })->sortByDesc('lastUsed')->values();

        return view('admin.customers.show', [
            'c' => $c,
            'deletionRequests' => DB::table('account_deletion_requests as r')->leftJoin('users as u', 'u.id', '=', 'r.resolved_by')
                ->where('r.customer_id', $id)->orderByDesc('r.requested_at')->get(['r.*', 'u.name as resolved_by_name']),
            'disabledBy' => $c->disabled_by ? DB::table('users')->where('id', $c->disabled_by)->value('name') : null,
            'emails' => DB::table('customer_emails')->where('customer_id', $id)->orderBy('position')->get(),
            'phones' => DB::table('customer_phones')->where('customer_id', $id)->orderBy('position')->get(),
            'addresses' => DB::table('customer_addresses')->where('customer_id', $id)->orderBy('position')->get(),
            'documents' => DB::table('customer_documents')->where('customer_id', $id)->orderBy('position')->get(),
            'avatar' => DB::table('customer_avatars')->where('customer_id', $id)->first(['updated_at', 'byte_size', 'content_type']),
            'bookings' => $bookings,
            'orders' => $orders,
            'giftCards' => DB::table('gift_card_orders')->where('customer_id', $id)->orderByDesc('created_at')->get()->map(fn ($g) => (object) [...(array) $g, 'view' => GiftCardIssuer::present($g)]),
            'travellers' => DB::table('saved_travellers')->where('customer_id', $id)->orderByDesc('last_used_at')->get(),
            'sessions' => $sessions,
            'authEvents' => DB::table('auth_events')->where(fn ($q) => $q->where('customer_id', $id)->orWhereRaw('lower(login) = lower(?)', [$c->zenith_login])->when($c->email, fn ($w) => $w->orWhereRaw('lower(login) = lower(?)', [$c->email])))->orderByDesc('created_at')->limit(200)->get(),
            'appLogs' => DB::table('app_logs')->where('customer_id', $id)->orderByDesc('received_at')->limit(150)->get(),
            'serverLogs' => DB::table('server_logs')->where('customer_id', $id)->orderByDesc('logged_at')->limit(150)->get(),
            'stats' => [
                'flightSpend' => (float) $bookings->where('status', 'ticketed')->sum('total_amount'),
                'shopSpend' => (float) $orders->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum(fn ($o) => (float) $o->total - (float) $o->refunded_amount),
                'tickets' => $bookings->where('status', 'ticketed')->count(),
                'onHold' => $bookings->where('status', 'pending_payment')->filter(fn ($b) => ! $b->time_limit || CarbonImmutable::parse($b->time_limit)->isFuture())->count(),
                'activeSessions' => $sessions->where('active', true)->count(),
                'failedLogins30' => DB::table('auth_events')->where(fn ($q) => $q->whereRaw('lower(login) = lower(?)', [$c->zenith_login])->when($c->email, fn ($w) => $w->orWhereRaw('lower(login) = lower(?)', [$c->email])))->where('event', 'login')->where('success', false)->where('created_at', '>=', self::since(30))->count(),
                'devices' => DB::table('app_logs')->where('customer_id', $id)->distinct()->count('device_id'),
            ],
        ]);
    }

    /** The customer's profile photo (stored only by us; Zenith has none). */
    public function avatar(Request $request, string $customer): Response
    {
        $this->allow($request);
        abort_unless(Str::isUuid($customer), 404);
        $a = DB::table('customer_avatars')->where('customer_id', $customer)->first(['content_type', 'image']);
        abort_unless($a, 404);
        $bytes = is_resource($a->image) ? stream_get_contents($a->image) : (string) $a->image;

        return response($bytes, 200, ['Content-Type' => $a->content_type, 'Cache-Control' => 'private, max-age=300']);
    }

    /**
     * Disables or re-enables an account. Disabling keeps the account and all
     * its data, ends every session at once and stops it signing in anywhere
     * until re-enabled; a deletion request waiting is completed with it.
     */
    public function toggleStatus(Request $request, string $customer, AccountDeletion $deletion): RedirectResponse
    {
        $this->allow($request, 'customers.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);
        $c = DB::table('customers')->where('id', $customer)->first();
        abort_unless($c, 404);
        $disable = $c->status !== 'disabled';
        $userId = (int) $request->user()->id;
        if ($disable) {
            $pending = $deletion->pending($c->id);
            if ($pending) {
                $deletion->complete($pending->id, $userId, $data['reason']);
            } else {
                $deletion->disable($c->id, $userId, $data['reason']);
            }
        } else {
            $deletion->enable($c->id);
        }
        $this->audit($request, $c, $disable ? 'staff_disabled' : 'staff_enabled', ($disable ? 'Disabled' : 'Re-enabled')." the account: {$data['reason']}");

        return back()->with('success', $disable
            ? 'Account disabled and signed out of every device. Its data is kept; it can be re-enabled at any time.'
            : 'Account re-enabled: they can sign in again.');
    }

    /** Completes a deletion request: the account is disabled (never deleted). */
    public function completeDeletion(Request $request, string $customer, string $deletionRequest, AccountDeletion $deletion): RedirectResponse
    {
        $this->allow($request, 'customers.manage');
        $data = $request->validate(['note' => ['nullable', 'string', 'max:500']]);
        $c = $this->deletionCustomer($customer, $deletionRequest);
        try {
            $deletion->complete($deletionRequest, (int) $request->user()->id, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->audit($request, $c, 'deletion_completed', 'Completed the deletion request: account disabled'.(($data['note'] ?? null) ? " ({$data['note']})" : ''));

        return back()->with('success', 'Request completed: the account is disabled and signed out everywhere. Nothing was deleted.');
    }

    /** Declines a deletion request; the customer is told why. */
    public function declineDeletion(Request $request, string $customer, string $deletionRequest, AccountDeletion $deletion): RedirectResponse
    {
        $this->allow($request, 'customers.manage');
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:500']]);
        $c = $this->deletionCustomer($customer, $deletionRequest);
        try {
            $deletion->decline($deletionRequest, (int) $request->user()->id, $data['note']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        $this->audit($request, $c, 'deletion_declined', "Declined the deletion request: {$data['note']}");

        return back()->with('success', 'Request declined. The customer has been told why.');
    }

    private function deletionCustomer(string $customer, string $deletionRequest): object
    {
        $c = DB::table('customers')->where('id', $customer)->first();
        abort_unless($c && DB::table('account_deletion_requests')->where('id', $deletionRequest)->where('customer_id', $c->id)->exists(), 404);

        return $c;
    }

    public function signOut(Request $request, string $customer): RedirectResponse
    {
        $this->allow($request, 'customers.manage');
        $c = DB::table('customers')->where('id', $customer)->first();
        abort_unless($c, 404);
        $count = $this->revoke($c->id, 'signed_out_by_staff');
        $this->audit($request, $c, 'staff_signout', "Signed out of every device ({$count} session tokens ended)");

        return back()->with('success', "Signed out of every device ({$count} active tokens ended).");
    }

    private function revoke(string $customerId, string $reason): int
    {
        return DB::table('refresh_tokens')->where('customer_id', $customerId)->whereNull('revoked_at')
            ->update(['revoked_at' => Js::dbTime(), 'revoked_reason' => $reason]);
    }

    private function audit(Request $request, object $c, string $event, string $description): void
    {
        DB::table('auth_events')->insert([
            'customer_id' => $c->id, 'login' => $c->zenith_login, 'event' => $event, 'success' => true,
            'ip' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 300),
            'detail' => "By {$request->user()->name}: {$description}", 'created_at' => Js::dbTime(),
        ]);
        ActivityLog::log($event, 'Customers', "{$c->zenith_login}: {$description}", ['customer' => $c->id]);
    }
}
