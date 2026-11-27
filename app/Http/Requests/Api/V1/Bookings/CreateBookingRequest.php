<?php

namespace App\Http\Requests\Api\V1\Bookings;

use App\Http\Requests\Api\V1\ApiRequest;

class CreateBookingRequest extends ApiRequest
{
    protected bool $strict = true;

    public function rules(): array
    {
        return [
            'draftId' => ['required', 'string', 'uuid'],
            'passengers' => ['required', 'array', 'min:1', 'max:9'],
            'passengers.*' => ['array', function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_array($value) && array_diff(array_keys($value), ['ref', 'values'])) {
                    $fail('Each passenger may only have ref and values.');
                }
            }],
            'passengers.*.ref' => ['required', 'string', 'min:1', 'max:100'],
            'passengers.*.values' => ['present', self::values()],
            'booking' => ['sometimes', self::values()],
            'saveTravellers' => ['sometimes', 'array', 'max:9'],
            'saveTravellers.*' => ['string', 'min:1', 'max:100'],
            // Meals, assistance, seats and paid extras chosen with the booking.
            ...ExtrasRules::selection('extras'),
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), ...ExtrasRules::messages('extras')];
    }

    /** Form values: up to 60 fields, text (200 characters max) or null. */
    private static function values(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_array($value) || (array_is_list($value) && $value !== [])) {
                $fail("{$attribute} must be an object.");

                return;
            }
            if (count($value) > 60) {
                $fail('Too many fields.');

                return;
            }
            foreach ($value as $key => $v) {
                if (mb_strlen((string) $key) > 80 || ($v !== null && (! is_string($v) || mb_strlen($v) > 200))) {
                    $fail("{$attribute}.{$key} must be text of at most 200 characters.");

                    return;
                }
            }
        };
    }

    protected function normalize(array $data): array
    {
        if (is_array($data['passengers'] ?? null)) {
            foreach ($data['passengers'] as &$p) {
                if (is_array($p)) {
                    self::map($p, 'ref', self::trimmed(...));
                }
            }
            unset($p);
        }
        if (is_array($data['saveTravellers'] ?? null)) {
            $data['saveTravellers'] = array_map(self::trimmed(...), $data['saveTravellers']);
        }

        return $data;
    }

    /** @return array{passengers: list<array{ref: string, values: array}>, booking?: array, saveTravellers?: list<string>, extras?: array} */
    public function booking(): array
    {
        $input = [
            'passengers' => array_map(fn ($p) => ['ref' => $p['ref'], 'values' => $p['values'] ?? []], $this->input('passengers')),
        ];
        if (is_array($this->input('booking'))) {
            $input['booking'] = $this->input('booking');
        }
        if (is_array($this->input('saveTravellers'))) {
            $input['saveTravellers'] = $this->input('saveTravellers');
        }
        if (is_array($this->input('extras'))) {
            $extras = ExtrasRules::normalize($this->input('extras'));
            if ($extras['services'] || $extras['seats'] || $extras['ancillaries']) {
                $input['extras'] = $extras;
            }
        }

        return $input;
    }
}
