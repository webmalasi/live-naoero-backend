<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

class RefreshRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'refreshToken' => ['required', 'string', 'min:20', 'max:200'],
            'deviceLabel' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'refreshToken.min' => 'refreshToken is not valid.'];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'deviceLabel', self::optionalText(...));

        return $data;
    }
}
