<?php

namespace App\Http\Requests\Api\V1\Bookings;

/**
 * The shape of an extras selection (special services, seats, paid extras).
 * Whether each item is actually offered is checked against Zenith's offer by
 * BookingExtras, not here.
 */
final class ExtrasRules
{
    /** @return array<string, array> rules for "{$prefix}.services", ".seats", ".ancillaries" */
    public static function selection(string $prefix): array
    {
        $ref = ['nullable', 'string', 'max:100'];

        return [
            $prefix => ['sometimes', 'array'],
            "{$prefix}.services" => ['sometimes', 'array', 'max:200'],
            "{$prefix}.services.*" => ['array'],
            "{$prefix}.services.*.code" => ['required', 'string', 'regex:/^[A-Z0-9-]{2,8}$/'],
            "{$prefix}.services.*.passengerRef" => $ref,
            "{$prefix}.services.*.segmentRef" => $ref,
            "{$prefix}.services.*.text" => ['nullable', 'string', 'max:100'],
            "{$prefix}.seats" => ['sometimes', 'array', 'max:60'],
            "{$prefix}.seats.*" => ['array'],
            "{$prefix}.seats.*.passengerRef" => ['required', 'string', 'max:100'],
            "{$prefix}.seats.*.segmentRef" => ['required', 'string', 'max:100'],
            "{$prefix}.seats.*.seat" => ['required', 'string', 'regex:/^\d{1,3}[A-Za-z]$/'],
            "{$prefix}.ancillaries" => ['sometimes', 'array', 'max:100'],
            "{$prefix}.ancillaries.*" => ['array'],
            "{$prefix}.ancillaries.*.ref" => ['required', 'string', 'max:100'],
            "{$prefix}.ancillaries.*.passengerRef" => $ref,
            "{$prefix}.ancillaries.*.segmentRef" => $ref,
            "{$prefix}.ancillaries.*.quantity" => ['sometimes', 'integer', 'min:1', 'max:9'],
        ];
    }

    public static function messages(string $prefix): array
    {
        return [
            "{$prefix}.seats.*.seat.regex" => 'Seats look like 12D.',
            "{$prefix}.services.*.code.regex" => 'Invalid service code.',
            "{$prefix}.ancillaries.*.quantity.max" => 'At most :max of one extra.',
        ];
    }

    /** @return array{services: list<array>, seats: list<array>, ancillaries: list<array>} */
    public static function normalize(mixed $sel): array
    {
        $sel = is_array($sel) ? $sel : [];
        $str = fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null;

        return [
            'services' => array_values(array_map(fn ($s) => [
                'code' => strtoupper((string) ($s['code'] ?? '')),
                'passengerRef' => $str($s['passengerRef'] ?? null),
                'segmentRef' => $str($s['segmentRef'] ?? null),
                'text' => $str($s['text'] ?? null),
            ], is_array($sel['services'] ?? null) ? $sel['services'] : [])),
            'seats' => array_values(array_map(fn ($s) => [
                'passengerRef' => (string) $s['passengerRef'],
                'segmentRef' => (string) $s['segmentRef'],
                'seat' => strtoupper((string) $s['seat']),
            ], is_array($sel['seats'] ?? null) ? $sel['seats'] : [])),
            'ancillaries' => array_values(array_map(fn ($a) => [
                'ref' => (string) $a['ref'],
                'passengerRef' => $str($a['passengerRef'] ?? null),
                'segmentRef' => $str($a['segmentRef'] ?? null),
                'quantity' => (int) ($a['quantity'] ?? 1),
            ], is_array($sel['ancillaries'] ?? null) ? $sel['ancillaries'] : [])),
        ];
    }
}
