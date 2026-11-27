<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in customer's inbox, the same for the app and the website:
 * flight changes from the airline, every step of their bookings and of
 * their shop orders. Clients poll unread-count for a badge and open the
 * list to read them; each notice says what it opens (`action`).
 */
class NotificationController extends ApiController
{
    private const MAX = 100;

    /**
     * Newest first. ?category=flights|bookings|shop|account, ?unread=1,
     * ?limit (1-100, default 50), ?before (a notice's createdAt, for the
     * next page).
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->customer($request);
        $limit = max(1, min(self::MAX, (int) $request->query('limit', 50)));
        $category = $request->query('category');
        if ($category !== null && ! isset(CustomerNotices::CATEGORIES[$category])) {
            throw ApiException::invalid('category must be one of: '.implode(', ', array_keys(CustomerNotices::CATEGORIES)).'.');
        }
        $before = null;
        if ($request->filled('before')) {
            try {
                $before = CarbonImmutable::parse((string) $request->query('before'));
            } catch (\Throwable) {
                throw ApiException::invalid('before must be a date-time, as a notice\'s createdAt.');
            }
        }
        $rows = DB::table('customer_notifications')
            ->where('customer_id', $customer->id)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->when($before, fn ($q) => $q->where('created_at', '<', Js::dbTime($before)))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit + 1)
            ->get(['id', 'type', 'category', 'pnr', 'shop_order_id', 'title', 'body', 'data', 'read_at', 'created_at']);
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);

        return $this->json([
            'notifications' => $rows->map(fn ($r) => self::present($r))->values()->all(),
            'hasMore' => $more,
            'nextBefore' => $more ? Js::iso($rows->last()->created_at) : null,
            ...$this->counts($customer->id),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->json($this->counts($this->customer($request)->id));
    }

    public function read(Request $request, string $id): JsonResponse
    {
        $customerId = $this->customer($request)->id;
        $this->owned($customerId, $id);
        DB::table('customer_notifications')->where('id', $id)->whereNull('read_at')->update(['read_at' => Js::dbTime()]);

        return $this->json(['ok' => true, ...$this->counts($customerId)]);
    }

    /** Marks everything read, or one category (?category=shop). */
    public function readAll(Request $request): JsonResponse
    {
        $customerId = $this->customer($request)->id;
        $category = $request->query('category') ?? $request->json('category');
        DB::table('customer_notifications')->where('customer_id', $customerId)->whereNull('read_at')
            ->when(is_string($category) && isset(CustomerNotices::CATEGORIES[$category]), fn ($q) => $q->where('category', $category))
            ->update(['read_at' => Js::dbTime()]);

        return $this->json(['ok' => true, ...$this->counts($customerId)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $customerId = $this->customer($request)->id;
        $this->owned($customerId, $id);
        DB::table('customer_notifications')->where('id', $id)->delete();

        return $this->json(['ok' => true, ...$this->counts($customerId)]);
    }

    /** Clears the read notices (unread ones stay). */
    public function clearRead(Request $request): JsonResponse
    {
        $customerId = $this->customer($request)->id;
        $deleted = DB::table('customer_notifications')->where('customer_id', $customerId)->whereNotNull('read_at')->delete();

        return $this->json(['ok' => true, 'deleted' => $deleted, ...$this->counts($customerId)]);
    }

    private function owned(string $customerId, string $id): void
    {
        $exists = preg_match('/^[0-9a-f-]{36}$/i', $id) && DB::table('customer_notifications')->where('id', $id)->where('customer_id', $customerId)->exists();
        if (! $exists) {
            throw new ApiException(404, 'not_found', 'This notification was not found.');
        }
    }

    /** @return array{unreadCount: int, unreadByCategory: array<string, int>} */
    private function counts(string $customerId): array
    {
        $by = DB::table('customer_notifications')->where('customer_id', $customerId)->whereNull('read_at')
            ->groupBy('category')->selectRaw('category, count(*) as n')->pluck('n', 'category');
        $byCategory = [];
        foreach (array_keys(CustomerNotices::CATEGORIES) as $c) {
            $byCategory[$c] = (int) ($by[$c] ?? 0);
        }

        return ['unreadCount' => array_sum($byCategory), 'unreadByCategory' => $byCategory];
    }

    private static function present(object $r): array
    {
        $data = Js::decode($r->data);
        $data = $data instanceof \ArrayObject ? $data->getArrayCopy() : (is_array($data) ? $data : []);

        return [
            'id' => $r->id,
            'type' => $r->type,
            'category' => $r->category ?? CustomerNotices::category($r->type),
            'pnr' => $r->pnr,
            'orderId' => $r->shop_order_id ?? null,
            'title' => $r->title,
            'body' => $r->body,
            // Flight notices: each change, kind (retimed / moved / added / cancelled) with the flight before and after.
            'changes' => is_array($data['changes'] ?? null) ? $data['changes'] : [],
            // What tapping it opens.
            'action' => CustomerNotices::action($r, $data),
            'data' => array_diff_key($data, ['changes' => true]) ?: (object) [],
            'read' => $r->read_at !== null,
            'readAt' => Js::iso($r->read_at),
            'createdAt' => Js::iso($r->created_at),
        ];
    }
}
