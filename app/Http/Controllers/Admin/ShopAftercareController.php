<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Shop\ProductReviews;
use App\Services\Shop\ShopReturns;
use App\Services\Shop\ShopSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * After the sale, in the admin panel: customers' help requests with their
 * conversation, returns and exchanges from request to refund or
 * replacement, and product reviews to moderate and answer.
 */
class ShopAftercareController extends Controller
{
    public function __construct(
        private readonly ShopSupport $support,
        private readonly ShopReturns $returns,
        private readonly ProductReviews $reviews,
    ) {}

    private function allow(Request $request, string $permission): void
    {
        abort_unless($request->user()?->can("shop.sales.{$permission}"), 403, 'You do not have access to shop sales.');
    }

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

    // ------------------------------------------------------------ help requests

    public function support(Request $request): View
    {
        $this->allow($request, 'view');
        $query = DB::table('shop_support_requests as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->leftJoin('shop_orders as o', 'o.id', '=', 'r.shop_order_id')
            ->select('r.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'o.order_number')
            ->selectSub(fn ($q) => $q->from('shop_support_messages')->whereColumn('support_request_id', 'r.id')->selectRaw('count(*)'), 'messages')
            ->orderByRaw("case r.status when 'open' then 0 when 'answered' then 1 else 2 end")->orderByDesc('r.last_message_at');
        if (($status = $request->input('status')) && isset(ShopSupport::STATUSES[$status])) {
            $query->where('r.status', $status);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(r.number) like ?', [$like])->orWhereRaw('lower(coalesce(o.order_number, \'\')) like ?', [$like])->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like]));
        }

        return view('admin.sales.support', [
            'requests' => $query->paginate(30)->withQueryString(),
            'counts' => DB::table('shop_support_requests')->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status'),
            'statuses' => ShopSupport::STATUSES,
            'topics' => ShopSupport::TOPICS,
        ]);
    }

    public function supportShow(Request $request, string $id): View
    {
        $this->allow($request, 'view');
        abort_unless(Str::isUuid($id), 404);
        $r = DB::table('shop_support_requests as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->leftJoin('shop_orders as o', 'o.id', '=', 'r.shop_order_id')
            ->where('r.id', $id)->select('r.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'o.order_number')->first();
        abort_unless($r, 404);

        return view('admin.sales.support_show', [
            'r' => $r,
            'messages' => DB::table('shop_support_messages as m')->leftJoin('users as u', 'u.id', '=', 'm.staff_user_id')->where('m.support_request_id', $id)
                ->orderBy('m.created_at')->orderBy('m.id')->get(['m.*', 'u.name as staff_name']),
            'statuses' => ShopSupport::STATUSES,
            'topics' => ShopSupport::TOPICS,
        ]);
    }

    public function supportReply(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'min:2', 'max:4000'], 'close' => ['nullable', 'boolean']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->support->staffReply($id, $data['message'], $staff, (bool) ($data['close'] ?? false));

            return 'Reply sent: the customer was notified.';
        });
    }

    public function supportStatus(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ShopSupport::STATUSES))]]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->support->staffSetStatus($id, $data['status'], $staff);

            return 'Status saved.';
        });
    }

    // ------------------------------------------------------------ returns

    public function returns(Request $request): View
    {
        $this->allow($request, 'view');
        $query = DB::table('shop_return_requests as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->join('shop_orders as o', 'o.id', '=', 'r.shop_order_id')
            ->select('r.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'o.order_number', 'o.currency')
            ->selectSub(fn ($q) => $q->from('shop_return_items')->whereColumn('return_request_id', 'r.id')->selectRaw('coalesce(sum(quantity), 0)'), 'units')
            ->orderByRaw("case r.status when 'requested' then 0 when 'approved' then 1 when 'received' then 2 else 3 end")->orderByDesc('r.created_at');
        if (($status = $request->input('status')) && isset(ShopReturns::STATUSES[$status])) {
            $query->where('r.status', $status);
        }
        if (in_array($type = $request->input('type'), ['return', 'exchange'], true)) {
            $query->where('r.type', $type);
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(r.return_number) like ?', [$like])->orWhereRaw('lower(o.order_number) like ?', [$like])->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like]));
        }

        return view('admin.sales.returns', [
            'returns' => $query->paginate(30)->withQueryString(),
            'counts' => DB::table('shop_return_requests')->groupBy('status')->selectRaw('status, count(*) as n')->pluck('n', 'status'),
            'statuses' => ShopReturns::STATUSES,
            'reasons' => ShopReturns::REASONS,
        ]);
    }

    public function returnShow(Request $request, string $id): View
    {
        $this->allow($request, 'view');
        abort_unless(Str::isUuid($id), 404);
        $r = DB::table('shop_return_requests as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->join('shop_orders as o', 'o.id', '=', 'r.shop_order_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.handled_by')->where('r.id', $id)
            ->select('r.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email', 'o.order_number', 'o.currency', 'o.total', 'o.refunded_amount', 'u.name as handled_by_name')->first();
        abort_unless($r, 404);

        return view('admin.sales.return_show', [
            'r' => $r,
            'items' => DB::table('shop_return_items as ri')->join('shop_order_items as i', 'i.id', '=', 'ri.order_item_id')->where('ri.return_request_id', $id)
                ->orderBy('ri.id')->get(['ri.*', 'i.title', 'i.variant_title', 'i.sku', 'i.unit_price']),
            'value' => ShopReturns::value($r),
            'statuses' => ShopReturns::STATUSES,
            'reasons' => ShopReturns::REASONS,
            'conditions' => ShopReturns::CONDITIONS,
            'refundable' => round(max(0, (float) $r->total - (float) $r->refunded_amount), 2),
        ]);
    }

    public function returnApprove(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['instructions' => ['nullable', 'string', 'max:1000']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->returns->approve($id, $data['instructions'] ?? null, $staff);

            return 'Approved: the customer has the return instructions.';
        });
    }

    public function returnReject(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:1000']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->returns->reject($id, $data['note'], $staff);

            return 'Return not accepted: the customer was told why.';
        });
    }

    public function returnReceive(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.condition' => ['required', Rule::in(array_keys(ShopReturns::CONDITIONS))],
            'items.*.restock' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $items = [];
            foreach ($data['items'] as $itemId => $item) {
                $items[(int) $itemId] = ['condition' => $item['condition'], 'restock' => (bool) ($item['restock'] ?? false)];
            }
            $this->returns->receive($id, $items, $data['note'] ?? null, $staff);

            return 'Return received; what can be sold again is back in stock.';
        });
    }

    public function returnRefund(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:100000']]);

        return $this->act($request, 'refund', function (int $staff) use ($id, $data) {
            $refund = $this->returns->refund($id, (float) $data['amount'], $staff);

            return "Refunded {$refund->currency} ".number_format((float) $refund->amount, 2).' to the customer\'s card.';
        });
    }

    public function returnExchange(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['carrier' => ['required', 'string', 'max:80'], 'tracking_number' => ['nullable', 'string', 'max:80']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->returns->completeExchange($id, $data['carrier'], $data['tracking_number'] ?? null, $staff);

            return 'Replacement sent: the customer was notified.';
        });
    }

    // ------------------------------------------------------------ reviews

    public function reviews(Request $request): View
    {
        $this->allow($request, 'view');
        $query = DB::table('shop_product_reviews as r')->join('shop_products as p', 'p.id', '=', 'r.shop_product_id')->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('shop_orders as o', 'o.id', '=', 'r.shop_order_id')->leftJoin('shop_product_variants as v', 'v.id', '=', 'r.variant_id')
            ->select('r.*', 'p.title as product_title', 'c.zenith_login', 'c.email as customer_main_email', 'o.order_number', 'v.title as variant_title')->orderByDesc('r.created_at');
        if (in_array($status = $request->input('status'), ['published', 'hidden'], true)) {
            $query->where('r.status', $status);
        }
        if (in_array($rating = (string) $request->input('rating'), ['1', '2', '3', '4', '5'], true)) {
            $query->where('r.rating', (int) $rating);
        }
        if ($request->input('unanswered')) {
            $query->whereNull('r.staff_reply');
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(fn ($q) => $q->whereRaw('lower(p.title) like ?', [$like])->orWhereRaw('lower(coalesce(r.body, \'\')) like ?', [$like])->orWhereRaw('lower(c.zenith_login) like ?', [$like])->orWhereRaw("lower(coalesce(c.email, '')) like ?", [$like]));
        }

        return view('admin.sales.reviews', [
            'reviews' => $query->paginate(30)->withQueryString(),
            'average' => DB::table('shop_product_reviews')->where('status', 'published')->avg('rating'),
            'total' => DB::table('shop_product_reviews')->count(),
            'hidden' => DB::table('shop_product_reviews')->where('status', 'hidden')->count(),
        ]);
    }

    public function reviewModerate(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['published', 'hidden'])], 'reason' => ['nullable', 'string', 'max:300']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->reviews->moderate($id, $data['status'], $data['reason'] ?? null, $staff);

            return $data['status'] === 'hidden' ? 'Review hidden from the shop.' : 'Review published.';
        });
    }

    public function reviewReply(Request $request, string $id): RedirectResponse
    {
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:2000']]);

        return $this->act($request, 'manage', function (int $staff) use ($id, $data) {
            $this->reviews->reply($id, $data['reply'] ?? null, $staff);

            return ($data['reply'] ?? null) ? 'Reply published: the customer was notified.' : 'Reply removed.';
        });
    }
}
