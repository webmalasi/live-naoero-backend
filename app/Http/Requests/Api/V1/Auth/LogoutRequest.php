<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class LogoutRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'refreshToken' => ['sometimes', 'string', 'min:20', 'max:200'],
            // Every session, this one included.
            'allDevices' => ['sometimes', self::strictBoolean()],
            // Every session except the one making the request.
            'otherDevices' => ['sometimes', self::strictBoolean()],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'refreshToken.min' => 'refreshToken is not valid.'];
    }
}
