<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Shop\ShopCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Our secure payment page (https), opened by the app in an in-app browser.
 * It shows the order and NAB's Unified Checkout card form with 3-D Secure;
 * the link carries a one-time secret instead of the customer's session.
 */
class ShopCheckoutController extends ApiController
{
    public function __construct(private readonly ShopCheckout $checkout) {}

    /** The payment page itself. */
    public function page(Request $request, string $attempt): Response
    {
        try {
            [$a, $order] = $this->checkout->attempt($attempt, (string) $request->query('t'));
            $data = $this->checkout->page($a, $order);
        } catch (ApiException $e) {
            $data = ['state' => 'error', 'message' => $e->getMessage()];
        }

        return response()->view('shop.checkout', [
            'data' => $data,
            'attempt' => $attempt,
            'token' => (string) $request->query('t'),
            'doneUrl' => ShopCheckout::origin()."/api/v1/shop/checkout/{$attempt}/done",
        ])->withHeaders(self::pageHeaders());
    }

    /** The page reports what Unified Checkout returned; NAB is asked for the truth. */
    public function complete(Request $request, string $attempt): JsonResponse
    {
        $token = $request->input('t');
        $completeResponse = $request->input('completeResponse');
        if (! is_string($token) || ! is_string($completeResponse) || strlen($completeResponse) > 20000) {
            throw ApiException::invalid('Invalid payment result.');
        }
        $result = $this->checkout->complete($attempt, $token, $completeResponse);

        return $this->json($result + [
            'redirect' => ShopCheckout::origin()."/api/v1/shop/checkout/{$attempt}/done?status={$result['status']}",
        ]);
    }

    /**
     * Where the page ends. The app closes its payment screen as soon as it
     * reaches this address. In a browser it shows the result from our records
     * and, after a short countdown, takes the customer to their order.
     */
    public function done(Request $request, string $attempt): Response
    {
        $a = Str::isUuid($attempt) ? DB::table('shop_payment_attempts')->where('id', $attempt)->first() : null;
        $order = $a ? DB::table('shop_orders')->where('id', $a->shop_order_id)->first(['id', 'order_number', 'status']) : null;
        $status = self::doneStatus($order?->status, (string) $request->query('status'));

        return response()->view('shop.checkout-done', [
            'status' => $status,
            'orderNumber' => $order?->order_number,
            'target' => ShopCheckout::returnTarget($a, $order, $status),
            'seconds' => 5,
        ])->withHeaders(self::pageHeaders());
    }

    /** What the finished page shows: our records first, the page's word only while unpaid. */
    private static function doneStatus(?string $orderStatus, string $pageStatus): string
    {
        return match ($orderStatus) {
            'paid', 'partially_refunded', 'refunded' => 'paid',
            'payment_review' => 'review',
            'cancelled' => 'cancelled',
            default => in_array($pageStatus, ['declined', 'cancelled'], true) ? $pageStatus
                : (in_array($pageStatus, ['paid', 'pending', 'review'], true) ? 'pending' : 'unknown'),
        };
    }

    private static function pageHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store',
            // The link holds a one-time secret: never pass it on.
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }
}
