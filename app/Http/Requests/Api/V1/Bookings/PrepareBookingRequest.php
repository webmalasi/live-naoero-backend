<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;

class PrepareBookingRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            'offerRef' => ['required', 'string', 'min:1', 'max:400'],
            'itineraryRef' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'offerRef', self::trimmed(...));
        self::map($data, 'itineraryRef', self::trimmed(...));

        return $data;
    }
}
