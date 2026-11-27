<?php

namespace App\Http\Requests\Api\V1\Shop;

use App\Http\Requests\Api\V1\ApiRequest;

/** A cart to check before checkout: products, variants and quantities. */
class CheckShopCartRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['array'],
            'items.*.productId' => ['required', 'regex:/^\d{1,18}$/'],
            'items.*.variantId' => ['nullable', 'regex:/^\d{1,18}$/'],
            // Up to 99 so a cart with too many of one item hears how many it can have.
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'items.required' => 'Your cart is empty.',
            'items.*.productId.regex' => 'An item in your cart is not valid.',
            'items.*.variantId.regex' => 'An item in your cart is not valid.',
            'items.*.quantity.min' => 'Quantities must be at least 1.',
        ];
    }

    protected function normalize(array $data): array
    {
        if (is_array($data['items'] ?? null)) {
            foreach ($data['items'] as &$item) {
                foreach (['productId', 'variantId'] as $k) {
                    if (is_array($item) && isset($item[$k]) && is_int($item[$k])) {
                        $item[$k] = (string) $item[$k];
                    }
                }
            }
            unset($item);
        }

        return $data;
    }

    /** @return list<array{productId: string, variantId: ?string, quantity: int}> */
    public function items(): array
    {
        return array_map(fn ($i) => [
            'productId' => (string) $i['productId'],
            'variantId' => isset($i['variantId']) && $i['variantId'] !== '' ? (string) $i['variantId'] : null,
            'quantity' => (int) $i['quantity'],
        ], $this->input('items'));
    }
}
