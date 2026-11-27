<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Http\Requests\Api\V1\ApiRequest;

/**
 * A new account, as on the airline's own "Create a new profile" form:
 * login (username) and password, title, names, email, birth date, a
 * mobile, and the GDPR choices. Older apps send no username: their email
 * is then the login, and birth date and terms stay optional for them.
 */
class SignupRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            // The login; unique, can't change. Format checked by Usernames.
            'username' => ['sometimes', 'nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'regex:/^[A-Z]{1,10}$/'],
            'firstName' => ['required', 'string', 'min:1', 'max:60'],
            'lastName' => ['required', 'string', 'min:1', 'max:60'],
            'email' => self::emailRules(),
            'birthDate' => ['nullable', 'required_with:username', self::realDate(), function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value > now()->utc()->format('Y-m-d')) {
                    $fail('Birth date cannot be in the future.');
                } elseif (is_string($value) && $value < '1900-01-01') {
                    $fail('Please check your birth date.');
                }
            }],
            // A mobile: SMS go to it, and Zenith stores it as the customer's mobile.
            'phone' => ['required', ...self::phoneRule(mobile: true)],
            'phoneCountry' => self::phoneCountryRule(),
            'password' => ['required', 'string', 'min:1', 'max:128'],
            // GDPR: the conditions of carriage, fare rules, baggage policy, privacy policy and dangerous goods rules.
            'acceptTerms' => ['nullable', 'required_with:username', self::strictBoolean(), function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === false) {
                    $fail('Please accept the conditions of carriage and the privacy policy.');
                }
            }],
            // Marketing emails and texts (optional, can be withdrawn).
            'marketingOptIn' => ['sometimes', 'nullable', self::strictBoolean()],
            'deviceLabel' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'title.regex' => 'Choose a title.',
            'birthDate.required_with' => 'Please enter your birth date.',
            'acceptTerms.required_with' => 'Please accept the conditions of carriage and the privacy policy.',
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'username', self::optionalText(...));
        self::map($data, 'title', self::upper(...));
        self::map($data, 'firstName', self::trimmed(...));
        self::map($data, 'lastName', self::trimmed(...));
        self::map($data, 'email', self::email(...));
        self::map($data, 'birthDate', self::optionalText(...));
        self::map($data, 'phoneCountry', self::upper(...));
        self::map($data, 'phone', fn ($v) => self::phone($v, $data['phoneCountry'] ?? null));
        self::map($data, 'deviceLabel', self::optionalText(...));

        return $data;
    }
}
