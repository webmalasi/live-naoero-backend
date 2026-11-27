<?php

namespace App\Http\Requests\Api\V1\Me;

use App\Http\Requests\Api\V1\ApiRequest;
use Illuminate\Validation\Rule;

/**
 * Every field optional: only what is sent is changed; null clears. Zenith
 * ignores an empty address list, so an address can be changed but not
 * removed (null is not accepted for it).
 */
class UpdateProfileRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        $address = fn () => is_array($this->input('address'));
        $passport = fn () => is_array($this->input('passport'));
        $country = ['string', 'regex:/^[A-Z]{2}$/'];

        return [
            'title' => ['sometimes', 'string', 'regex:/^[A-Z]{1,10}$/'],
            'firstName' => ['sometimes', 'string', 'min:1', 'max:60'],
            'middleName' => ['sometimes', 'nullable', 'string', 'max:60'],
            'lastName' => ['sometimes', 'string', 'min:1', 'max:60'],
            'phone' => ['sometimes', ...self::phoneRule(mobile: true)],
            'phoneCountry' => self::phoneCountryRule(),
            'birthDate' => ['sometimes', 'nullable', self::realDate(), function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && $value > now()->utc()->format('Y-m-d')) {
                    $fail('Birth date cannot be in the future.');
                }
            }],

            'address' => ['sometimes', 'array'],
            'address.streetNumber' => ['nullable', 'string', 'max:20'],
            'address.line1' => [Rule::requiredIf($address), 'string', 'min:1', 'max:120'],
            'address.line2' => ['nullable', 'string', 'max:120'],
            'address.city' => [Rule::requiredIf($address), 'string', 'min:1', 'max:80'],
            'address.state' => ['nullable', 'string', 'max:80'],
            'address.postcode' => ['nullable', 'string', 'max:20'],
            'address.country' => [Rule::requiredIf($address), ...$country],

            'passport' => ['sometimes', 'nullable', 'array'],
            'passport.number' => [Rule::requiredIf($passport), 'string', 'regex:/^[A-Z0-9]{5,20}$/'],
            'passport.documentType' => ['string', Rule::in(['PP', 'NI', 'DOI', 'DI', 'MI', 'RT', 'CTD'])],
            'passport.issuingCountry' => [Rule::requiredIf($passport), ...$country],
            'passport.nationality' => [Rule::requiredIf($passport), ...$country],
            'passport.issueDate' => ['nullable', self::realDate()],
            'passport.expiryDate' => [Rule::requiredIf($passport), self::realDate(), function (string $attribute, mixed $value, \Closure $fail): void {
                $issued = $this->input('passport.issueDate');
                if (is_string($issued) && is_string($value) && $issued >= $value) {
                    $fail('The expiry date must be after the issue date.');
                }
            }],
            'passport.gender' => ['nullable', Rule::in(['M', 'F'])],

            'emergencyContact' => ['sometimes', 'nullable', 'array'],
            'emergencyContact.name' => ['nullable', 'string', 'max:80'],
            'emergencyContact.phone' => ['nullable', ...self::phoneRule()],
            'emergencyContact.phoneCountry' => self::phoneCountryRule(),
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'title.regex' => 'Choose a title.',
            'address.country.regex' => 'Use a 2-letter country code.',
            'passport.issuingCountry.regex' => 'Use a 2-letter country code.',
            'passport.nationality.regex' => 'Use a 2-letter country code.',
            'passport.number.regex' => 'Enter the passport number as printed (letters and digits).',
        ];
    }

    protected function normalize(array $data): array
    {
        self::map($data, 'title', self::upper(...));
        self::map($data, 'firstName', self::trimmed(...));
        self::map($data, 'middleName', self::optionalText(...));
        self::map($data, 'lastName', self::trimmed(...));
        self::map($data, 'phoneCountry', self::upper(...));
        self::map($data, 'phone', fn ($v) => self::phone($v, $data['phoneCountry'] ?? null));
        if (is_array($data['address'] ?? null)) {
            $a = &$data['address'];
            foreach (['streetNumber', 'line2', 'state', 'postcode'] as $k) {
                self::map($a, $k, self::optionalText(...));
            }
            self::map($a, 'line1', self::trimmed(...));
            self::map($a, 'city', self::trimmed(...));
            self::map($a, 'country', self::upper(...));
            unset($a);
        }
        if (is_array($data['passport'] ?? null)) {
            $p = &$data['passport'];
            self::map($p, 'number', self::upper(...));
            self::map($p, 'issuingCountry', self::upper(...));
            self::map($p, 'nationality', self::upper(...));
            if (($p['documentType'] ?? null) === null) {
                $p['documentType'] = 'PP';
            }
            unset($p);
        }
        if (is_array($data['emergencyContact'] ?? null)) {
            $e = &$data['emergencyContact'];
            self::map($e, 'name', self::optionalText(...));
            self::map($e, 'phoneCountry', self::upper(...));
            self::map($e, 'phone', fn ($v) => $v === null ? null : self::phone($v, $e['phoneCountry'] ?? null));
            unset($e);
        }

        return $data;
    }

    /** The update with only the fields that were sent. */
    public function update(): array
    {
        $update = $this->validated();
        foreach (['address', 'passport', 'emergencyContact'] as $group) {
            if (is_array($update[$group] ?? null)) {
                $update[$group] = array_intersect_key($this->input($group), array_flip(array_map(
                    fn ($k) => substr($k, strlen($group) + 1),
                    array_filter(array_keys($this->rules()), fn ($k) => str_starts_with($k, "{$group}.")),
                )));
            }
        }

        return $update;
    }
}
