<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shop\GiftCardIssuer;
use App\Services\Shop\ShipmentTracking;
use App\Services\Shop\ShopDocuments;
use App\Services\Shop\ShopEvents;
use App\Services\Shop\ShopOperations;
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
 * Shop Sales & Orders in the admin panel: gift card and merchandise orders
 * from the website and the app alike, paid through NAB; every payment
 * attempt with its outcome, refunds, shipping, gift cards and the full
 * activity timeline. Each order shows the platform it was placed on.
 */
class ShopSalesController extends Controller
{
    public function __construct(private readonly ShopOperations $operations) {}

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can("shop.sales.{$permission}"), 403, 'You do not have access to shop sales.');
    }

    /** Payment outcome tag for an order (what the dashboard filters on). */
    public const ORDER_STATUSES = [
        'pending_payment' => 'Awaiting payment',
        'paid' => 'Paid',
        'payment_review' => 'Under review',
        'partially_refunded' => 'Partly refunded',
        'refunded' => 'Refunded',
        'cancelled' => 'Cancelled',
    ];

    // ------------------------------------------------------------ dashboard

    public function index(Request $request): View|StreamedResponse
    {
        $this->allow($request, 'view');
        $query = $this->filteredOrders($request);

        if ($request->input('export') === 'csv') {
            $this->allow($request, 'export');

            return $this->exportOrders($query);
        }

        $orders = $query->paginate(25)->withQueryString();
        $ids = collect($orders->items())->pluck('id');
        $items = DB::table('shop_order_items')->whereIn('shop_order_id', $ids)->orderBy('id')->get()->groupBy('shop_order_id');
        $lastAttempts = DB::table('shop_payment_attempts')->whereIn('shop_order_id', $ids)->orderBy('created_at')->get()->groupBy('shop_order_id')->map->last();

        return view('admin.sales.index', [
            'orders' => $orders,
            'items' => $items,
            'lastAttempts' => $lastAttempts,
            'stats' => $this->stats(),
            'chart' => $this->dailyChart(30),
            'statuses' => self::ORDER_STATUSES,
            'filters' => $request->only(['status', 'shipping', 'type', 'channel', 'search', 'from', 'to']),
        ]);
    }

    private function filteredOrders(Request $request)
    {
        $query = DB::table('shop_orders as o')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->select('o.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email')
            ->orderByDesc('o.created_at');

        if (($status = $request->input('status')) && isset(self::ORDER_STATUSES[$status])) {
            $query->where('o.status', $status);
        }
        if ($shipping = $request->input('shipping')) {
            $shipping === 'to_ship'
                ? $query->whereIn('o.status', ['paid', 'partially_refunded'])->where('o.shipping_status', 'processing')
                : $query->where('o.shipping_status', $shipping);
        }
        if (in_array($request->input('channel'), ['app', 'website'], true)) {
            $query->where('o.channel', $request->input('channel'));
        }
        if (in_array($request->input('type'), ['gift_card', 'merch'], true)) {
            $query->whereExists(fn ($q) => $q->from('shop_order_items as i')->whereColumn('i.shop_order_id', 'o.id')->where('i.type', $request->input('type')));
        }
        if ($from = $request->input('from')) {
            $query->where('o.created_at', '>=', CarbonImmutable::parse($from)->startOfDay()->utc()->format('Y-m-d H:i:s.uP'));
        }
        if ($to = $request->input('to')) {
            $query->where('o.created_at', '<=', CarbonImmutable::parse($to)->endOfDay()->utc()->format('Y-m-d H:i:s.uP'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($like, $search) {
                $q->whereRaw('lower(o.order_number) like ?', [$like])
                    ->orWhereRaw('lower(o.contact_email) like ?', [$like])
                    ->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like])
                    ->orWhereRaw("lower(coalesce(c.first_name, '') || ' ' || coalesce(c.last_name, '')) like ?", [$like])
                    ->orWhere('o.nab_transaction_id', $search)
                    ->orWhereExists(fn ($g) => $g->from('gift_card_orders as g')->whereColumn('g.shop_order_id', 'o.id')->whereRaw('upper(g.voucher_code) = ?', [strtoupper($search)]));
            });
        }

        return $query;
    }

    /** Headline numbers for the dashboard. */
    private function stats(): array
    {
        $since = CarbonImmutable::now()->subDays(30)->utc()->format('Y-m-d H:i:s.uP');
        $today = CarbonImmutable::now()->startOfDay()->utc()->format('Y-m-d H:i:s.uP');
        $paid = ['paid', 'partially_refunded', 'refunded'];

        $gross = (float) DB::table('shop_orders')->whereIn('status', $paid)->sum('total');
        $refunded = (float) DB::table('shop_orders')->sum('refunded_amount');
        $paidCount = DB::table('shop_orders')->whereIn('status', $paid)->count();
        $attempts30 = DB::table('shop_payment_attempts')->where('created_at', '>=', $since)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $decided = ($attempts30['paid'] ?? 0) + ($attempts30['declined'] ?? 0) + ($attempts30['failed'] ?? 0) + ($attempts30['review'] ?? 0);

        return [
            'gross' => $gross,
            'refunded' => $refunded,
            'net' => $gross - $refunded,
            'today' => (float) DB::table('shop_orders')->whereIn('status', $paid)->where('paid_at', '>=', $today)->sum('total'),
            'last30' => (float) DB::table('shop_orders')->whereIn('status', $paid)->where('paid_at', '>=', $since)->sum('total'),
            'paidCount' => $paidCount,
            'averageOrder' => $paidCount ? $gross / $paidCount : 0,
            'awaitingPayment' => DB::table('shop_orders')->where('status', 'pending_payment')->count(),
            'underReview' => DB::table('shop_orders')->where('status', 'payment_review')->count(),
            'toShip' => DB::table('shop_orders')->whereIn('status', ['paid', 'partially_refunded'])->where('shipping_status', 'processing')->count(),
            'declined30' => (int) ($attempts30['declined'] ?? 0),
            'approvalRate' => $decided ? round(100 * ($attempts30['paid'] ?? 0) / $decided, 1) : null,
            'attempts30' => $attempts30->all(),
            'giftCardsIssued' => DB::table('gift_card_orders')->whereNotNull('shop_order_id')->count(),
            'giftCardsValue' => (float) DB::table('gift_card_orders')->whereNotNull('shop_order_id')->whereIn('status', ['issued', 'confirmed'])->sum('balance_remaining'),
            'refundCount' => DB::table('shop_refunds')->where('status', 'succeeded')->count(),
            'byChannel' => DB::table('shop_orders')->whereIn('status', $paid)->select('channel', DB::raw('count(*) as orders'), DB::raw('sum(total - refunded_amount) as revenue'))
                ->groupBy('channel')->get()->keyBy('channel'),
            'failedRefunds' => DB::table('shop_refunds')->where('status', 'failed')->count(),
            'topProducts' => DB::table('shop_order_items as i')->join('shop_orders as o', 'o.id', '=', 'i.shop_order_id')
                ->whereIn('o.status', $paid)->select('i.title', DB::raw('sum(i.quantity) as units'), DB::raw('sum(i.line_total) as revenue'))
                ->groupBy('i.title')->orderByDesc('revenue')->limit(5)->get(),
        ];
    }

    /** Paid revenue and orders per day, for the chart. */
    private function dailyChart(int $days): array
    {
        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $rows = DB::table('shop_orders')->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->where('paid_at', '>=', $start->utc()->format('Y-m-d H:i:s.uP'))->get(['paid_at', 'total']);
        $declines = DB::table('shop_payment_attempts')->where('status', 'declined')
            ->where('updated_at', '>=', $start->utc()->format('Y-m-d H:i:s.uP'))->get(['updated_at']);
        $labels = $revenue = $orders = $declined = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->addDays($i)->format('Y-m-d');
            $labels[] = $start->addDays($i)->format('j M');
            $revenue[$day] = 0;
            $orders[$day] = 0;
            $declined[$day] = 0;
        }
        foreach ($rows as $r) {
            $day = CarbonImmutable::parse($r->paid_at)->setTimezone(config('app.timezone'))->format('Y-m-d');
            if (isset($revenue[$day])) {
                $revenue[$day] += (float) $r->total;
                $orders[$day]++;
            }
        }
        foreach ($declines as $d) {
            $day = CarbonImmutable::parse($d->updated_at)->setTimezone(config('app.timezone'))->format('Y-m-d');
            if (isset($declined[$day])) {
                $declined[$day]++;
            }
        }

        return ['labels' => $labels, 'revenue' => array_values($revenue), 'orders' => array_values($orders), 'declined' => array_values($declined)];
    }

    private function exportOrders($query): StreamedResponse
    {
        $rows = $query->limit(10000)->get();
        $items = DB::table('shop_order_items')->whereIn('shop_order_id', $rows->pluck('id'))->get()->groupBy('shop_order_id');

        return response()->streamDownload(function () use ($rows, $items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Order', 'Placed (UTC)', 'Platform', 'Status', 'Shipping', 'Customer', 'Email', 'Items', 'Items total', 'Delivery', 'Total', 'Refunded', 'Currency', 'Paid (UTC)', 'NAB transaction', 'Card', 'Carrier', 'Tracking']);
            foreach ($rows as $o) {
                $payment = $o->payment_details ? json_decode($o->payment_details, true) : [];
                fputcsv($out, [
                    $o->order_number, $o->created_at, $o->channel, $o->status, $o->shipping_status, trim("{$o->first_name} {$o->last_name}"), $o->contact_email,
                    ($items[$o->id] ?? collect())->map(fn ($i) => "{$i->quantity} x {$i->title}")->implode('; '),
                    $o->items_total, $o->delivery_fee, $o->total, $o->refunded_amount, $o->currency, $o->paid_at, $o->nab_transaction_id,
                    trim(($payment['brand'] ?? '').' '.($payment['last4'] ?? '')), $o->shipping_carrier, $o->tracking_number,
                ]);
            }
            fclose($out);
        }, 'shop-orders-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    // ------------------------------------------------------------ one order

    public function show(Request $request, string $order): View
    {
        $this->allow($request, 'view');
        abort_unless(Str::isUuid($order), 404);
        $o = DB::table('shop_orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.id', $order)->select('o.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'c.zenith_customer_ref', 'c.created_at as customer_since')->first();
        abort_unless($o, 404);
        $phone = DB::table('customers')->where('id', $o->customer_id)->value('phone_e164')
            ?? DB::table('customer_phones')->where('customer_id', $o->customer_id)->orderBy('position')->value('phone_number');

        return view('admin.sales.show', [
            'order' => $o,
            'phone' => $phone,
            'items' => DB::table('shop_order_items')->where('shop_order_id', $o->id)->orderBy('id')->get(),
            'attempts' => DB::table('shop_payment_attempts')->where('shop_order_id', $o->id)->orderByDesc('created_at')->get(),
            'refunds' => DB::table('shop_refunds as r')->leftJoin('users as u', 'u.id', '=', 'r.staff_user_id')->where('r.shop_order_id', $o->id)->orderByDesc('r.created_at')->get(['r.*', 'u.name as staff_name']),
            'giftCards' => DB::table('gift_card_orders')->where('shop_order_id', $o->id)->orderBy('id')->get()->map(fn ($c) => (object) ([...(array) $c, 'view' => GiftCardIssuer::present($c)])),
            'timeline' => ShopEvents::timeline($o->id),
            'customerOrders' => DB::table('shop_orders')->where('customer_id', $o->customer_id)->count(),
            'customerSpend' => (float) DB::table('shop_orders')->where('customer_id', $o->customer_id)->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum(DB::raw('total - refunded_amount')),
            'refundable' => ShopOperations::refundable($o),
            'statuses' => self::ORDER_STATUSES,
            'delivery' => ShipmentTracking::present($o),
            'nextDelivery' => array_intersect_key(ShipmentTracking::STATUSES, array_flip(ShipmentTracking::nextStatuses($o->shipping_status))),
            'returns' => DB::table('shop_return_requests')->where('shop_order_id', $o->id)->orderByDesc('created_at')->get(),
            'supportRequests' => DB::table('shop_support_requests')->where('shop_order_id', $o->id)->orderByDesc('last_message_at')->get(),
            'cancellation' => ShopOperations::cancellation($o),
        ]);
    }

    /** Records a delivery step: packed, shipped, in transit, out for delivery, delivered, failed. */
    public function shipment(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(ShipmentTracking::STATUSES))],
            'message' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:120'],
            'carrier' => ['nullable', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:300'],
            'estimated_delivery' => ['nullable', 'date'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->act($request, 'manage', function (int $staff) use ($order, $data) {
            $status = $data['status'];
            unset($data['status']);
            app(ShipmentTracking::class)->update($order, $status, $data, $staff);

            return 'Delivery updated: '.ShipmentTracking::STATUSES[$status].'. The customer was notified.';
        });
    }

    /** The invoice or receipt, as the customer sees it. */
    public function document(Request $request, string $order, string $kind): Response
    {
        $this->allow($request, 'view');
        abort_unless(Str::isUuid($order) && in_array($kind, ['invoice', 'receipt'], true), 404);
        $data = ShopDocuments::data($order);
        abort_unless($data, 404, 'This order has no invoice yet: it is not paid.');

        return response()->view("shop.documents.{$kind}", [...$data, 'kind' => $kind]);
    }

    // ------------------------------------------------------------ ledgers

    /** Every payment attempt, with its outcome. */
    public function payments(Request $request): View|StreamedResponse
    {
        $this->allow($request, 'view');
        $query = DB::table('shop_payment_attempts as a')
            ->join('shop_orders as o', 'o.id', '=', 'a.shop_order_id')
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->select('a.*', 'o.order_number', 'o.currency', 'o.total as order_total', 'o.status as order_status', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email')
            ->orderByDesc('a.created_at');
        $outcome = $request->input('outcome');
        if ($outcome === 'abandoned') {
            $query->whereIn('a.status', ['started', 'cancelled']);
        } elseif (in_array($outcome, ['paid', 'declined', 'review', 'failed'], true)) {
            $query->where('a.status', $outcome);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(o.order_number) like ?', [$like])->orWhere('a.nab_transaction_id', $search)->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like]));
        }

        if ($request->input('export') === 'csv') {
            $this->allow($request, 'export');
            $rows = $query->limit(20000)->get();

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Attempt', 'Started (UTC)', 'Updated (UTC)', 'Order', 'Customer', 'Amount', 'Currency', 'Outcome', 'Reason', 'Card', 'NAB transaction']);
                foreach ($rows as $a) {
                    fputcsv($out, [$a->id, $a->created_at, $a->updated_at, $a->order_number, $a->customer_main_email ?? $a->zenith_login, $a->amount ?? $a->order_total, $a->currency, self::attemptOutcome($a), $a->reason, trim("{$a->card_brand} {$a->card_last4}"), $a->nab_transaction_id]);
                }
                fclose($out);
            }, 'shop-payments-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        $since = CarbonImmutable::now()->subDays(30)->utc()->format('Y-m-d H:i:s.uP');

        return view('admin.sales.payments', [
            'attempts' => $query->paginate(30)->withQueryString(),
            'counts' => DB::table('shop_payment_attempts')->where('created_at', '>=', $since)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status'),
            'refunds' => DB::table('shop_refunds as r')->join('shop_orders as o', 'o.id', '=', 'r.shop_order_id')->leftJoin('users as u', 'u.id', '=', 'r.staff_user_id')
                ->orderByDesc('r.created_at')->limit(10)->get(['r.*', 'o.order_number', 'u.name as staff_name']),
        ]);
    }

    /** success / declined / review / failed / abandoned / pending */
    public static function attemptOutcome(object $a): string
    {
        return match ($a->status) {
            'paid' => 'success',
            'declined', 'review', 'failed' => $a->status,
            'cancelled' => 'abandoned',
            default => ($a->order_status ?? null) === 'pending_payment' ? 'pending' : 'abandoned',
        };
    }

    /** Every event on every order, newest first. */
    public function activity(Request $request): View
    {
        $this->allow($request, 'view');
        $query = DB::table('shop_order_events as e')
            ->join('shop_orders as o', 'o.id', '=', 'e.shop_order_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.staff_user_id')
            ->select('e.*', 'o.order_number', 'u.name as staff_name')
            ->orderByDesc('e.created_at')->orderByDesc('e.id');
        if (($outcome = $request->input('outcome')) && isset(ShopEvents::OUTCOMES[$outcome])) {
            $query->where('e.outcome', $outcome);
        }
        if (($type = $request->input('type')) && isset(ShopEvents::TYPES[$type])) {
            $query->where('e.type', $type);
        }
        if (in_array($source = $request->input('source'), ['customer', 'system', 'nab', 'staff'], true)) {
            $query->where('e.source', $source);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(o.order_number) like ?', [$like])->orWhereRaw('lower(e.message) like ?', [$like]));
        }

        return view('admin.sales.activity', [
            'events' => $query->paginate(50)->withQueryString(),
            'types' => ShopEvents::TYPES,
            'outcomes' => ShopEvents::OUTCOMES,
        ]);
    }

    // ------------------------------------------------------------ actions

    private function act(Request $request, string $permission, callable $action): RedirectResponse
    {
        $this->allow($request, $permission);
        try {
            $message = $action($request->user()->id);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', $message);
    }

    public function refund(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'cancel_gift_cards' => ['nullable', 'boolean'],
            'restock' => ['nullable', 'array'],
            'restock.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);

        return $this->act($request, 'refund', function (int $staff) use ($order, $data) {
            $restock = array_map('intval', array_filter($data['restock'] ?? [], fn ($v) => (int) $v > 0));
            $refund = $this->operations->refund($order, (float) $data['amount'], $data['reason'], (bool) ($data['cancel_gift_cards'] ?? false), $staff, $restock);
            if ($refund->status !== 'succeeded') {
                throw new RuntimeException("NAB refused the refund: {$refund->failure_reason}");
            }

            return "Refunded {$refund->currency} ".number_format((float) $refund->amount, 2).' to the customer\'s card.';
        });
    }

    public function ship(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:300'],
        ]);

        return $this->act($request, 'manage', function (int $staff) use ($order, $data) {
            $this->operations->markShipped($order, $data['carrier'], $data['tracking_number'] ?? null, $data['tracking_url'] ?? null, $staff);

            return 'Marked as shipped.';
        });
    }

    public function deliver(Request $request, string $order): RedirectResponse
    {
        return $this->act($request, 'manage', function (int $staff) use ($order) {
            $this->operations->markDelivered($order, $staff);

            return 'Marked as delivered.';
        });
    }

    public function note(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:2', 'max:1000']]);

        return $this->act($request, 'manage', function (int $staff) use ($order, $data) {
            $this->operations->addNote($order, $data['note'], $staff);

            return 'Note added.';
        });
    }

    public function cancel(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        return $this->act($request, 'manage', function (int $staff) use ($order, $data) {
            $this->operations->cancelUnpaid($order, $data['reason'], $staff);

            return 'Order cancelled and its stock released.';
        });
    }

    public function reconcile(Request $request, string $order): RedirectResponse
    {
        return $this->act($request, 'manage', fn (int $staff) => $this->operations->reconcileNow($order, $staff).'.');
    }

    public function resendGiftCard(Request $request, int $card): RedirectResponse
    {
        return $this->act($request, 'manage', fn (int $staff) => $this->operations->resendGiftCard($card, $staff)
            ? 'Gift card email queued.' : throw new RuntimeException('The email could not be queued; see the timeline.'));
    }

    public function cancelGiftCard(Request $request, int $card): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        return $this->act($request, 'manage', function (int $staff) use ($card, $data) {
            $this->operations->cancelGiftCard($card, $data['reason'], $staff);

            return 'Gift card cancelled.';
        });
    }
}
