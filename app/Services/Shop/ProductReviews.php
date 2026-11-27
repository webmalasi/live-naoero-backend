<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Product reviews: only customers who bought the product can review it
 * (a paid order with it, and merchandise once it has shipped), once per
 * product; they can edit or delete their review. Staff can hide a review
 * or reply to it. A product's rating and review count come from its
 * published reviews only.
 */
class ProductReviews
{
    public const SORTS = ['newest' => ['created_at', 'desc'], 'highest' => ['rating', 'desc'], 'lowest' => ['rating', 'asc']];

    public const PAGE_SIZE = 20;

    public function __construct(private readonly CustomerNotices $notices) {}

    /**
     * The order that lets the customer review the product, or why they can't.
     *
     * @return array{order: ?object, item: ?object, reason: ?string}
     */
    public static function eligibility(string $customerId, int $productId): array
    {
        $lines = DB::table('shop_order_items as i')->join('shop_orders as o', 'o.id', '=', 'i.shop_order_id')
            ->where('o.customer_id', $customerId)->where('i.shop_product_id', $productId)
            ->whereIn('o.status', ['paid', 'partially_refunded'])
            ->orderByDesc('o.paid_at')->get(['i.*', 'o.shipping_status', 'o.id as order_id']);
        if ($lines->isEmpty()) {
            return ['order' => null, 'item' => null, 'reason' => 'You can review products you have bought.'];
        }
        foreach ($lines as $line) {
            if ($line->type === 'gift_card' || in_array($line->shipping_status, ['shipped', 'in_transit', 'out_for_delivery', 'delivered', 'delivery_failed'], true)) {
                return ['order' => DB::table('shop_orders')->find($line->order_id), 'item' => $line, 'reason' => null];
            }
        }

        return ['order' => null, 'item' => null, 'reason' => 'You can review it once your order has shipped.'];
    }

    public function create(object $customer, string $productId, array $input): array
    {
        $product = ctype_digit($productId) ? DB::table('shop_products')->where('id', (int) $productId)->first() : null;
        if (! $product) {
            throw new ApiException(404, 'product_not_found', 'This product is no longer available.');
        }
        if (DB::table('shop_product_reviews')->where('shop_product_id', $product->id)->where('customer_id', $customer->id)->exists()) {
            throw new ApiException(409, 'already_reviewed', 'You have already reviewed this product. You can edit your review.');
        }
        $eligible = self::eligibility($customer->id, (int) $product->id);
        if (! $eligible['order']) {
            throw new ApiException(403, 'review_not_allowed', $eligible['reason']);
        }
        $id = (string) Str::uuid();
        $who = DB::table('customers')->where('id', $customer->id)->first(['first_name', 'last_name']);
        $name = trim(($who->first_name ?? '').' '.mb_substr((string) ($who->last_name ?? ''), 0, 1).(($who->last_name ?? '') !== '' ? '.' : '')) ?: 'Naoero traveller';
        DB::transaction(function () use ($id, $product, $customer, $eligible, $input, $name) {
            DB::table('shop_product_reviews')->insert([
                'id' => $id, 'shop_product_id' => $product->id, 'variant_id' => $eligible['item']->variant_id ?? null, 'customer_id' => $customer->id,
                'shop_order_id' => $eligible['order']->id, 'rating' => (int) $input['rating'], 'title' => $input['title'] ?? null, 'body' => $input['body'] ?? null,
                'reviewer_name' => $name, 'status' => 'published', 'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            self::recompute((int) $product->id);
        });

        return $this->present(DB::table('shop_product_reviews')->find($id), mine: true);
    }

    public function update(object $customer, string $id, array $input): array
    {
        $review = $this->owned($customer, $id);
        DB::transaction(function () use ($review, $input) {
            DB::table('shop_product_reviews')->where('id', $review->id)->update([
                'rating' => (int) $input['rating'], 'title' => $input['title'] ?? null, 'body' => $input['body'] ?? null, 'updated_at' => Js::dbTime(),
            ]);
            self::recompute((int) $review->shop_product_id);
        });

        return $this->present(DB::table('shop_product_reviews')->find($id), mine: true);
    }

    public function delete(object $customer, string $id): void
    {
        $review = $this->owned($customer, $id);
        DB::transaction(function () use ($review) {
            DB::table('shop_product_reviews')->where('id', $review->id)->delete();
            self::recompute((int) $review->shop_product_id);
        });
    }

    /** @return list<array<string, mixed>> */
    public function mine(object $customer): array
    {
        return DB::table('shop_product_reviews')->where('customer_id', $customer->id)->orderByDesc('created_at')->limit(200)->get()
            ->map(fn ($r) => $this->present($r, mine: true))->all();
    }

    /**
     * A product's published reviews with its rating summary.
     *
     * @return array<string, mixed>
     */
    public function forProduct(string $productId, string $sort, ?int $rating, int $page, ?object $customer = null): array
    {
        $product = ctype_digit($productId) ? DB::table('shop_products')->where('id', (int) $productId)->where('is_active', true)->first() : null;
        if (! $product) {
            throw new ApiException(404, 'product_not_found', 'This product is no longer available.');
        }
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['newest'];
        $query = DB::table('shop_product_reviews')->where('shop_product_id', $product->id)->where('status', 'published')
            ->when($rating, fn ($q) => $q->where('rating', $rating));
        $total = (clone $query)->count();
        $rows = $query->orderBy($column, $direction)->orderByDesc('created_at')->forPage(max(1, $page), self::PAGE_SIZE)->get();
        $mine = $customer ? DB::table('shop_product_reviews')->where('shop_product_id', $product->id)->where('customer_id', $customer->id)->first() : null;
        $eligible = $customer && ! $mine ? self::eligibility($customer->id, (int) $product->id) : null;

        return [
            'productId' => (string) $product->id,
            'summary' => self::summary((int) $product->id),
            'reviews' => $rows->map(fn ($r) => $this->present($r))->all(),
            'pagination' => ['page' => max(1, $page), 'perPage' => self::PAGE_SIZE, 'total' => $total, 'hasMore' => max(1, $page) * self::PAGE_SIZE < $total],
            // Signed in only: the customer's own review, or whether they can write one.
            'myReview' => $mine ? $this->present($mine, mine: true) : null,
            'canReview' => $customer ? ($mine === null && $eligible['order'] !== null) : null,
            'cannotReviewReason' => $customer && ! $mine ? $eligible['reason'] : null,
        ];
    }

    /** @return array{average: ?float, count: int, distribution: array<string, int>} */
    public static function summary(int $productId): array
    {
        $by = DB::table('shop_product_reviews')->where('shop_product_id', $productId)->where('status', 'published')
            ->groupBy('rating')->selectRaw('rating, count(*) as n')->pluck('n', 'rating');
        $count = (int) $by->sum();
        $sum = 0;
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $stars) {
            $distribution[(string) $stars] = (int) ($by[$stars] ?? 0);
            $sum += $stars * $distribution[(string) $stars];
        }

        return ['average' => $count ? Js::round2(round($sum / $count, 1)) : null, 'count' => $count, 'distribution' => $distribution];
    }

    /** The product's rating and review count, from its published reviews. */
    public static function recompute(int $productId): void
    {
        $summary = self::summary($productId);
        DB::table('shop_products')->where('id', $productId)->update(['rating' => $summary['average'] ?? 0, 'reviews_count' => $summary['count']]);
    }

    // ------------------------------------------------------------ staff

    public function moderate(string $id, string $status, ?string $reason, int $staffUserId): void
    {
        if (! in_array($status, ['published', 'hidden'], true)) {
            throw new RuntimeException('Choose publish or hide.');
        }
        $review = DB::table('shop_product_reviews')->where('id', $id)->first() ?? throw new RuntimeException('Review not found.');
        DB::transaction(function () use ($review, $status, $reason, $staffUserId) {
            DB::table('shop_product_reviews')->where('id', $review->id)->update([
                'status' => $status, 'hidden_reason' => $status === 'hidden' ? $reason : null, 'moderated_by' => $staffUserId, 'updated_at' => Js::dbTime(),
            ]);
            self::recompute((int) $review->shop_product_id);
        });
        $this->audit(($status === 'hidden' ? 'Hid' : 'Published')." review {$review->id}".($reason ? ": {$reason}" : ''));
    }

    public function reply(string $id, ?string $reply, int $staffUserId): void
    {
        $review = DB::table('shop_product_reviews')->where('id', $id)->first() ?? throw new RuntimeException('Review not found.');
        $reply = trim((string) $reply) ?: null;
        DB::table('shop_product_reviews')->where('id', $id)->update(['staff_reply' => $reply, 'replied_at' => $reply ? Js::dbTime() : null, 'moderated_by' => $staffUserId, 'updated_at' => Js::dbTime()]);
        if ($reply) {
            $title = DB::table('shop_products')->where('id', $review->shop_product_id)->value('title');
            $this->notices->send($review->customer_id, 'shop_review_reply', "We replied to your review of {$title}", mb_strimwidth($reply, 0, 300, '…'),
                ['productId' => (string) $review->shop_product_id, 'reviewId' => $review->id], dedupeKey: 'review_reply:'.$review->id.':'.md5($reply));
        }
        $this->audit("Replied to review {$review->id}");
    }

    private function owned(object $customer, string $id): object
    {
        return (Str::isUuid($id) ? DB::table('shop_product_reviews')->where('id', $id)->where('customer_id', $customer->id)->first() : null)
            ?? throw new ApiException(404, 'review_not_found', "We couldn't find that review on your account.");
    }

    /** @return array<string, mixed> */
    public function present(object $r, bool $mine = false): array
    {
        $variant = $r->variant_id ? DB::table('shop_product_variants')->where('id', $r->variant_id)->value('title') : null;
        $out = [
            'id' => $r->id,
            'productId' => (string) $r->shop_product_id,
            'rating' => (int) $r->rating,
            'title' => $r->title,
            'body' => $r->body,
            'reviewerName' => $r->reviewer_name,
            'variantTitle' => $variant,
            'verifiedPurchase' => true,
            'reply' => $r->staff_reply ? ['body' => $r->staff_reply, 'at' => Js::iso($r->replied_at)] : null,
            'createdAt' => Js::iso($r->created_at),
            'updatedAt' => Js::iso($r->updated_at),
        ];
        if ($mine) {
            $out['status'] = $r->status;
            $out['productTitle'] = DB::table('shop_products')->where('id', $r->shop_product_id)->value('title');
        }

        return $out;
    }

    private function audit(string $description): void
    {
        try {
            ActivityLog::log('updated', 'Shop Reviews', $description);
        } catch (Throwable) {
            // The audit log never blocks the step.
        }
    }
}
