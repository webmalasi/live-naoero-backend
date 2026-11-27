<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * A password reset link, sent by Zenith to the account's email. As on the
 * airline's own site: the email first; when several accounts use it, the
 * answer asks for the login (Zenith's CustomerLogin), or allAccounts sends
 * one email per account. A username alone works for an account we know.
 */
class ForgotPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'required_without:username', 'string', 'max:254', 'email:rfc'],
            'username' => ['nullable', 'required_without:email', 'string', 'min:1', 'max:254'],
            // After "several accounts use this email": no login given, so one email per account.
            'allAccounts' => ['nullable', 'boolean'],
            // The client shows the airline's notice itself: "several accounts" comes back as 200 needsLogin, not 409.
            'askForLogin' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'email.required_without' => 'Enter your email address or your username.',
            'username.required_without' => 'Enter your email address or your username.',
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'email', fn ($v) => self::optionalText(self::email($v)));
        self::map($data, 'username', self::optionalText(...));

        return $data;
    }
}
