<?php

namespace App\Http\Requests\Api\V1\Shop;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

/** A shop order: product ids and quantities only; prices come from the server. */
class CreateShopOrderRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        $address = fn () => is_array($this->input('shippingAddress'));
        $max = (int) config('naoero.shop.max_quantity', 10);

        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['array'],
            'items.*.productId' => ['required', 'regex:/^\d{1,18}$/'],
            // The size or colour picked; needed when the product has options.
            'items.*.variantId' => ['nullable', 'regex:/^\d{1,18}$/'],
            'items.*.quantity' => ['required', 'integer', 'min:1', "max:{$max}"],
            'items.*.gift' => ['nullable', 'array'],
            'items.*.gift.recipientName' => ['nullable', 'string', 'max:80'],
            'items.*.gift.recipientEmail' => ['nullable', 'string', 'max:254', 'email:rfc'],
            'items.*.gift.message' => ['nullable', 'string', 'max:300'],

            'shippingAddress' => ['nullable', 'array'],
            'shippingAddress.name' => [Rule::requiredIf($address), 'string', 'min:2', 'max:120'],
            'shippingAddress.phone' => [Rule::requiredIf($address), ...self::phoneRule()],
            'shippingAddress.phoneCountry' => self::phoneCountryRule(),
            'shippingAddress.line1' => [Rule::requiredIf($address), 'string', 'min:2', 'max:120'],
            'shippingAddress.line2' => ['nullable', 'string', 'max:120'],
            'shippingAddress.city' => [Rule::requiredIf($address), 'string', 'min:2', 'max:80'],
            'shippingAddress.state' => ['nullable', 'string', 'max:80'],
            'shippingAddress.postcode' => [Rule::requiredIf($address), 'string', 'min:2', 'max:20'],
            'shippingAddress.country' => [Rule::requiredIf($address), 'string', 'regex:/^[A-Z]{2}$/'],

            'contactEmail' => ['nullable', 'string', 'max:254', 'email:rfc'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'items.required' => 'Your cart is empty.',
            'items.*.productId.regex' => 'An item in your cart is not valid.',
            'items.*.variantId.regex' => 'An item in your cart is not valid.',
            'items.*.quantity.max' => 'You can buy at most :max of one item.',
            'items.*.quantity.min' => 'Quantities must be at least 1.',
            'items.*.gift.recipientEmail.email' => "Enter a valid email for the gift card's recipient.",
            'items.*.gift.message.max' => 'Gift messages can be at most :max characters.',
            'shippingAddress.country.regex' => 'Choose a country.',
            'shippingAddress.name.required' => 'Enter who the parcel is for.',
            'shippingAddress.line1.required' => 'Enter the street address.',
            'shippingAddress.city.required' => 'Enter the town or city.',
            'shippingAddress.postcode.required' => 'Enter the postcode.',
            'shippingAddress.country.required' => 'Choose a country.',
            'shippingAddress.phone.required' => 'Enter a phone number for the courier.',
        ];
    }

    protected function normalize(array $data): array
    {
        if (is_array($data['items'] ?? null)) {
            foreach ($data['items'] as &$item) {
                if (! is_array($item)) {
                    continue;
                }
                foreach (['productId', 'variantId'] as $k) {
                    if (isset($item[$k]) && is_int($item[$k])) {
                        $item[$k] = (string) $item[$k];
                    }
                }
                if (is_array($item['gift'] ?? null)) {
                    foreach (['recipientName', 'recipientEmail', 'message'] as $k) {
                        self::map($item['gift'], $k, self::optionalText(...));
                    }
                    self::map($item['gift'], 'recipientEmail', fn ($v) => $v === null ? null : self::email($v));
                }
            }
            unset($item);
        }
        if (is_array($data['shippingAddress'] ?? null)) {
            $a = &$data['shippingAddress'];
            foreach (['name', 'line1', 'line2', 'city', 'state', 'postcode'] as $k) {
                self::map($a, $k, self::optionalText(...));
            }
            self::map($a, 'country', self::upper(...));
            self::map($a, 'phoneCountry', self::upper(...));
            // A number typed without its code is read as the picked (or delivery) country's.
            self::map($a, 'phone', fn ($v) => self::phone($v, $a['phoneCountry'] ?? $a['country'] ?? null));
            unset($a);
        }
        self::map($data, 'contactEmail', fn ($v) => $v === null ? null : self::email(self::optionalText($v)));

        return $data;
    }

    /** @return array{items: list<array>, shippingAddress: ?array, contactEmail: ?string} */
    public function order(): array
    {
        $address = $this->input('shippingAddress');

        return [
            'items' => array_map(fn ($i) => [
                'productId' => (string) $i['productId'],
                'variantId' => isset($i['variantId']) && $i['variantId'] !== '' ? (string) $i['variantId'] : null,
                'quantity' => (int) $i['quantity'],
                'gift' => is_array($i['gift'] ?? null) ? $i['gift'] : null,
            ], $this->input('items')),
            'shippingAddress' => is_array($address) ? array_intersect_key($address, array_flip(['name', 'phone', 'phoneCountry', 'line1', 'line2', 'city', 'state', 'postcode', 'country'])) : null,
            'contactEmail' => $this->input('contactEmail'),
        ];
    }
}
