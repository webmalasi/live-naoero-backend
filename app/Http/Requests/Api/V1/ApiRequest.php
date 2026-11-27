<?php

namespace App\Http\Requests\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Phones;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Throwable;

/**
 * Base for the app API's JSON requests. The body must be a JSON object;
 * the first problem found is returned as a 400 "invalid_request" with a
 * message written for users (every field's in `problems`), the same way for
 * every endpoint.
 */
abstract class ApiRequest extends FormRequest
{
    /** When true, keys without a rule are refused. */
    protected bool $strict = false;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->getContent();
        $data = json_decode($raw, true);
        if (trim($raw) === '' || json_last_error() !== JSON_ERROR_NONE) {
            throw ApiException::invalid('Request body must be valid JSON.');
        }
        if (! is_array($data) || (array_is_list($data) && $data !== [])) {
            throw ApiException::invalid('Request body must be a JSON object.');
        }
        if ($this->strict) {
            $allowed = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys($this->rules())));
            foreach (array_keys($data) as $key) {
                if (! in_array((string) $key, $allowed, true)) {
                    self::logRejected($this, [(string) $key => 'Unknown field.']);

                    throw ApiException::invalid("Unknown field {$key}.");
                }
            }
        }
        $this->replace($this->normalize($data));
    }

    /** Trims, lower/upper-cases and strips input before the rules run. */
    protected function normalize(array $data): array
    {
        return $data;
    }

    /**
     * A 400 with the first message, and every field's message in
     * `problems`. Logged with the field names and messages only (never the
     * values sent), so a client that keeps failing can be diagnosed.
     */
    protected function failedValidation(Validator $validator): never
    {
        $problems = [];
        foreach ($validator->errors()->messages() as $field => $messages) {
            $problems[] = ['passengerRef' => null, 'field' => (string) $field, 'message' => (string) ($messages[0] ?? '')];
        }
        self::logRejected($this, array_column($problems, 'message', 'field'));

        throw new ApiException(400, 'invalid_request', $validator->errors()->first(), problems: $problems);
    }

    /** @param  array<string, string>  $fields  field => message */
    private static function logRejected(Request $request, array $fields): void
    {
        try {
            app(ServerLog::class)->warn('validation', $request->method().' '.$request->path().' rejected', [
                'fields' => array_slice($fields, 0, 20, true),
                'sent' => array_slice(array_keys($request->all()), 0, 30),
            ]);
        } catch (Throwable) {
            // Logging never changes the answer.
        }
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute is required.',
            'present' => ':attribute is required.',
            'string' => ':attribute must be text.',
            'boolean' => ':attribute must be true or false.',
            'array' => ':attribute has the wrong format.',
            'min.string' => ':attribute is required.',
            'max.string' => ':attribute is too long (at most :max characters).',
            'min.array' => ':attribute needs at least :min item(s).',
            'max.array' => ':attribute can have at most :max items.',
            'in' => 'Choose a valid :attribute.',
            'uuid' => 'Invalid :attribute.',
            'email' => 'Enter a valid email address.',
            'date_format' => 'Use the format YYYY-MM-DD.',
        ];
    }

    /** Field names are shown as the app sends them (camelCase). */
    public function attributes(): array
    {
        $names = [];
        foreach (array_keys($this->rules()) as $key) {
            $names[$key] = $key;
        }

        return $names;
    }

    // ------------------------------------------------------------ shared normalisers

    protected static function trimmed(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    /** Trimmed text, '' becomes null. */
    protected static function optionalText(mixed $value): mixed
    {
        $value = self::trimmed($value);

        return $value === '' ? null : $value;
    }

    protected static function upper(mixed $value): mixed
    {
        return is_string($value) ? strtoupper(trim($value)) : $value;
    }

    protected static function email(mixed $value): mixed
    {
        return is_string($value) ? strtolower(trim($value)) : $value;
    }

    /**
     * Phone numbers become E.164 ("+919899765000") when valid; $country is
     * the customer's chosen country, for numbers typed without their code.
     */
    protected static function phone(mixed $value, ?string $country = null): mixed
    {
        return Phones::normalize($value, $country);
    }

    /** Applies $fn to $data[$key] when the key is present. */
    protected static function map(array &$data, string $key, callable $fn): void
    {
        if (array_key_exists($key, $data)) {
            $data[$key] = $fn($data[$key]);
        }
    }

    // ------------------------------------------------------------ shared rules

    protected static function realDate(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $fail('Use the format YYYY-MM-DD.');

                return;
            }
            [$y, $m, $d] = array_map('intval', explode('-', $value));
            if (! checkdate($m, $d, $y)) {
                $fail('Not a real date.');
            }
        };
    }

    /** true or false only (not 1, "true"...). */
    protected static function strictBoolean(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_bool($value)) {
                $fail("{$attribute} must be true or false.");
            }
        };
    }

    /** A valid number for its country; $mobile: it must take text messages. */
    protected static function phoneRule(bool $mobile = false): array
    {
        return ['string', 'max:40', Phones::rule($mobile)];
    }

    /** The ISO country chosen in the phone picker (optional). */
    protected static function phoneCountryRule(): array
    {
        return ['nullable', 'string', 'regex:/^[A-Z]{2}$/'];
    }

    protected static function emailRules(): array
    {
        return ['required', 'string', 'max:254', 'email:rfc'];
    }
}
