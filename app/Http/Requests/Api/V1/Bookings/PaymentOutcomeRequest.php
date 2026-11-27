<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;

class PaymentOutcomeRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return ['outcome' => ['required', 'string', 'in:accept,decline,cancel,closed']];
    }
}
