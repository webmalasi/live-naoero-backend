<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;
use App\Services\Booking\RefundRequests;
use Illuminate\Validation\Rule;

/** A refund request for a ticketed booking. */
class RefundRequestRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(array_keys(RefundRequests::REASONS))],
            'details' => ['required', 'string', 'min:20', 'max:2000'],
            'passengerRefs' => ['required', 'array', 'min:1', 'max:9'],
            'passengerRefs.*' => ['string', 'max:100'],
            'contactPhone' => ['nullable', ...self::phoneRule()],
            'contactPhoneCountry' => self::phoneCountryRule(),
            'acceptPolicy' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'reason.required' => 'Choose the reason for your request.',
            'reason.in' => 'Choose one of the listed reasons.',
            'details.required' => 'Tell us what happened.',
            'details.min' => 'Please describe what happened in a little more detail (at least 20 characters).',
            'details.max' => 'Please keep the description under 2,000 characters.',
            'passengerRefs.required' => 'Choose at least one passenger.',
            'passengerRefs.min' => 'Choose at least one passenger.',
            'acceptPolicy.required' => 'Please confirm you understand that tickets are non-refundable.',
            'acceptPolicy.accepted' => 'Please confirm you understand that tickets are non-refundable.',
        ];
    }

    protected function normalize(array $data): array
    {
        foreach (['details', 'contactPhone'] as $key) {
            if (is_string($data[$key] ?? null)) {
                $data[$key] = trim($data[$key]);
            }
        }
        if (($data['contactPhone'] ?? null) === '') {
            $data['contactPhone'] = null;
        }
        self::map($data, 'contactPhoneCountry', self::upper(...));
        self::map($data, 'contactPhone', fn ($v) => $v === null ? null : self::phone($v, $data['contactPhoneCountry'] ?? null));

        return $data;
    }
}
