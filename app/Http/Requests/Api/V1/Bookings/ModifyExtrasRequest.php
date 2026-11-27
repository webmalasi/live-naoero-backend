<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;

/** Extras to add to and remove from a booking. */
class ModifyExtrasRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            ...ExtrasRules::selection('add'),
            'remove' => ['sometimes', 'array'],
            'remove.services' => ['sometimes', 'array', 'max:200'],
            'remove.services.*' => ['array'],
            'remove.services.*.code' => ['required', 'string', 'regex:/^[A-Z0-9-]{2,8}$/'],
            'remove.services.*.passengerRef' => ['nullable', 'string', 'max:100'],
            'remove.services.*.segmentRef' => ['nullable', 'string', 'max:100'],
            'remove.seats' => ['sometimes', 'array', 'max:60'],
            'remove.seats.*' => ['array'],
            'remove.seats.*.passengerRef' => ['required', 'string', 'max:100'],
            'remove.seats.*.segmentRef' => ['required', 'string', 'max:100'],
            'remove.ancillaries' => ['sometimes', 'array', 'max:100'],
            'remove.ancillaries.*' => ['string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), ...ExtrasRules::messages('add')];
    }

    /** @return array{add: array, remove: array} */
    public function changes(): array
    {
        $remove = is_array($this->input('remove')) ? $this->input('remove') : [];
        $str = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'add' => ExtrasRules::normalize($this->input('add')),
            'remove' => [
                'services' => array_map(fn ($s) => ['code' => strtoupper((string) $s['code']), 'passengerRef' => $str($s['passengerRef'] ?? null), 'segmentRef' => $str($s['segmentRef'] ?? null)], $remove['services'] ?? []),
                'seats' => array_map(fn ($s) => ['passengerRef' => (string) $s['passengerRef'], 'segmentRef' => (string) $s['segmentRef']], $remove['seats'] ?? []),
                'ancillaries' => array_values($remove['ancillaries'] ?? []),
            ],
        ];
    }
}
