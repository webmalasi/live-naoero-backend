<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class ChangePasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'currentPassword' => ['required', 'string', 'min:1', 'max:128'],
            'newPassword' => ['required', 'string', 'min:1', 'max:128'],
        ];
    }
}
