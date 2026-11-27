<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * Signing in with the username (the Zenith login) or with the email. One of
 * the two; with both, the username is used.
 */
class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // Not format-checked: older accounts' usernames are their email.
            'username' => ['nullable', 'required_without:email', 'string', 'min:1', 'max:254'],
            'email' => ['nullable', 'required_without:username', 'string', 'max:254', 'email:rfc'],
            'password' => ['required', 'string', 'min:1', 'max:128'],
            'deviceLabel' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'username.required_without' => 'Enter your username or your email address.',
            'email.required_without' => 'Enter your username or your email address.',
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'username', self::optionalText(...));
        self::map($data, 'email', fn ($v) => self::optionalText(self::email($v)));
        self::map($data, 'deviceLabel', self::optionalText(...));

        return $data;
    }
}
