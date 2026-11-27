<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Middleware\AuthenticateCustomer;
use App\Services\Shop\ProductReviews;
use App\Services\Shop\ShipmentTracking;
use App\Services\Shop\ShopDocuments;
use App\Services\Shop\ShopOrders;
use App\Services\Shop\ShopReturns;
use App\Services\Shop\ShopSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * After a shop order is placed: tracking the parcel, the invoice and
 * receipt, help from the shop team, returns and exchanges, and product
 * reviews. The same for the app and the website.
 */
class ShopAftercareController extends ApiController
{
    public function __construct(
        private readonly ShopOrders $orders,
        private readonly ShopSupport $support,
        private readonly ShopReturns $returns,
        private readonly ProductReviews $reviews,
    ) {}

    /** @return array<string, mixed> */
    private static function validated(Request $request, array $rules, array $messages = []): array
    {
        $validator = Validator::make($request->json()->all(), $rules, [
            'required' => ':attribute is required.',
            'max' => ':attribute can be at most :max characters.',
            'in' => ':attribute is not one of the allowed values.',
            'integer' => ':attribute must be a whole number.',
            ...$messages,
        ]);
        if ($validator->fails()) {
            throw ApiException::invalid($validator->errors()->first());
        }

        return $validator->validated();
    }

    private static function channel(Request $request): string
    {
        return strtolower(trim((string) $request->header('X-Client-Platform'))) === 'website' ? 'website' : 'app';
    }

    // ------------------------------------------------------------ tracking and documents

    /** Where the parcel is: the delivery steps and every update. */
    public function tracking(Request $request, string $id): JsonResponse
    {
        $order = $this->orders->find($this->customer($request), $id);
        $delivery = ShipmentTracking::present($order) ?? throw new ApiException(404, 'no_delivery', 'This order has nothing to deliver: gift cards are sent by email.');

        return $this->json(['orderId' => $order->id, 'orderNumber' => $order->order_number, 'delivery' => $delivery]);
    }

    /** The invoice and receipt links (they expire after an hour). */
    public function documents(Request $request, string $id): JsonResponse
    {
        $order = $this->orders->find($this->customer($request), $id);
        $links = ShopDocuments::links($order) ?? throw new ApiException(409, 'not_paid', 'The invoice and receipt are ready once the order is paid.');

        return $this->json(['documents' => $links]);
    }

    /** The printable invoice or receipt, opened from a signed link. */
    public function document(Request $request, string $id, string $kind): Response
    {
        $data = ShopDocuments::data($id);
        abort_unless($data, 404);

        return response()->view("shop.documents.{$kind}", [...$data, 'kind' => $kind])
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    /** How to reach the shop team, the topics, and the return reasons. */
    public function help(): JsonResponse
    {
        return $this->json([
            ...ShopOrders::help(),
            'returnReasons' => collect(ShopReturns::REASONS)->map(fn ($l, $c) => ['code' => $c, 'label' => $l])->values()->all(),
            'returnWindowDays' => (int) config('naoero.shop.return_window_days', 30),
        ]);
    }

    // ------------------------------------------------------------ help requests

    public function supportRequests(Request $request): JsonResponse
    {
        return $this->json(['requests' => $this->support->list($this->customer($request))]);
    }

    public function createSupportRequest(Request $request): JsonResponse
    {
        $data = self::validated($request, [
            'orderId' => ['nullable', 'string', 'max:40'],
            'topic' => ['required', Rule::in(array_keys(ShopSupport::TOPICS))],
            'message' => ['required', 'string', 'min:5', 'max:4000'],
        ], ['message.min' => 'Tell us a little more (at least 5 characters).']);

        return $this->json(['request' => $this->support->create($this->customer($request), $data['orderId'] ?? null, $data['topic'], trim($data['message']), self::channel($request))], 201);
    }

    public function supportRequest(Request $request, string $id): JsonResponse
    {
        return $this->json(['request' => $this->support->show($this->customer($request), $id)]);
    }

    public function replySupportRequest(Request $request, string $id): JsonResponse
    {
        $data = self::validated($request, ['message' => ['required', 'string', 'min:1', 'max:4000']]);

        return $this->json(['request' => $this->support->reply($this->customer($request), $id, trim($data['message']))]);
    }

    public function closeSupportRequest(Request $request, string $id): JsonResponse
    {
        return $this->json(['request' => $this->support->close($this->customer($request), $id)]);
    }

    // ------------------------------------------------------------ returns

    public function createReturn(Request $request, string $id): JsonResponse
    {
        $data = self::validated($request, [
            'type' => ['required', Rule::in(array_keys(ShopReturns::TYPES))],
            'reason' => ['required', Rule::in(array_keys(ShopReturns::REASONS))],
            'details' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.orderItemId' => ['required', 'regex:/^\d{1,18}$/'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.exchangeVariantId' => ['nullable', 'regex:/^\d{1,18}$/'],
        ], ['items.required' => 'Choose the items to return.', 'regex' => 'An item is not valid.']);

        return $this->json(['return' => $this->returns->create($this->customer($request), $id, $data, self::channel($request))], 201);
    }

    public function myReturns(Request $request): JsonResponse
    {
        return $this->json(['returns' => $this->returns->list($this->customer($request))]);
    }

    public function myReturn(Request $request, string $id): JsonResponse
    {
        return $this->json(['return' => $this->returns->show($this->customer($request), $id)]);
    }

    public function cancelReturn(Request $request, string $id): JsonResponse
    {
        return $this->json(['return' => $this->returns->cancel($this->customer($request), $id)]);
    }

    // ------------------------------------------------------------ reviews

    /** Published reviews and the rating summary; signed in, also the customer's own review. */
    public function productReviews(Request $request, string $id): JsonResponse
    {
        $sort = (string) $request->query('sort', 'newest');
        if (! isset(ProductReviews::SORTS[$sort])) {
            throw ApiException::invalid('sort must be newest, highest or lowest.');
        }
        $rating = $request->query('rating');
        if ($rating !== null && ! in_array((string) $rating, ['1', '2', '3', '4', '5'], true)) {
            throw ApiException::invalid('rating must be 1 to 5.');
        }

        return $this->json($this->reviews->forProduct($id, $sort, $rating === null ? null : (int) $rating, max(1, (int) $request->query('page', 1)), $this->optionalCustomer($request)));
    }

    private static function reviewRules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function createReview(Request $request, string $id): JsonResponse
    {
        $data = self::validated($request, self::reviewRules(), ['rating.min' => 'rating must be 1 to 5 stars.', 'rating.max' => 'rating must be 1 to 5 stars.']);

        return $this->json(['review' => $this->reviews->create($this->customer($request), $id, $data)], 201);
    }

    public function myReviews(Request $request): JsonResponse
    {
        return $this->json(['reviews' => $this->reviews->mine($this->customer($request))]);
    }

    public function updateReview(Request $request, string $id): JsonResponse
    {
        $data = self::validated($request, self::reviewRules(), ['rating.min' => 'rating must be 1 to 5 stars.', 'rating.max' => 'rating must be 1 to 5 stars.']);

        return $this->json(['review' => $this->reviews->update($this->customer($request), $id, $data)]);
    }

    public function deleteReview(Request $request, string $id): JsonResponse
    {
        $this->reviews->delete($this->customer($request), $id);

        return $this->json(['ok' => true]);
    }

    /** The signed-in customer on a public address, when a valid token is sent. */
    private function optionalCustomer(Request $request): ?object
    {
        if (! $request->bearerToken()) {
            return null;
        }
        try {
            return app(AuthenticateCustomer::class)->resolve($request);
        } catch (ApiException) {
            return null;
        }
    }
}
