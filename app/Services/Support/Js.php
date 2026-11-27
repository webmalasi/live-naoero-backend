<?php

namespace App\Services\Support;

use ArrayObject;
use Carbon\CarbonImmutable;
use Collator;
use DateTimeInterface;
use stdClass;

/**
 * Small helpers that keep the API's output identical to what the app has
 * always received (ISO timestamps with milliseconds, JavaScript rounding,
 * locale-aware sorting) and that preserve JSON objects through PHP.
 */
final class Js
{
    private static ?Collator $collator = null;

    /** "2026-10-19T08:30:00.000Z", or null. */
    public static function iso(DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = $value instanceof DateTimeInterface ? CarbonImmutable::instance($value) : CarbonImmutable::parse($value);

        return $date->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /** A timestamp for a database column (UTC, microseconds, offset). */
    public static function dbTime(?DateTimeInterface $value = null): string
    {
        return CarbonImmutable::instance($value ?? now())->utc()->format('Y-m-d H:i:s.uP');
    }

    /** Today's date in UTC, YYYY-MM-DD. */
    public static function today(): string
    {
        return now()->utc()->format('Y-m-d');
    }

    /** Math.round(n * 100) / 100 */
    public static function round2(mixed $n): float|int
    {
        $value = is_numeric($n) ? (float) $n : 0.0;
        $rounded = floor($value * 100 + 0.5) / 100;

        return $rounded == (int) $rounded && abs($rounded) < PHP_INT_MAX ? (int) $rounded : $rounded;
    }

    /** String.prototype.localeCompare */
    public static function compare(string $a, string $b): int
    {
        if (class_exists(Collator::class)) {
            self::$collator ??= new Collator('en');
            $result = self::$collator->compare($a, $b);
            if ($result !== false) {
                return $result;
            }
        }

        return strcasecmp($a, $b) ?: strcmp($a, $b);
    }

    /**
     * Decodes JSON into arrays, except that empty objects become an empty
     * ArrayObject: reading a key from it is safe, and it encodes back as
     * "{}" (not "[]"), so data passed back to Zenith or stored is unchanged.
     */
    public static function decode(?string $json): mixed
    {
        if ($json === null || $json === '') {
            return null;
        }

        return self::objectsToArrays(json_decode($json, false, 512, JSON_BIGINT_AS_STRING));
    }

    public static function objectsToArrays(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);
            if ($vars === []) {
                return new ArrayObject;
            }

            return array_map(self::objectsToArrays(...), $vars);
        }
        if ($value instanceof ArrayObject) {
            return $value->count() === 0 ? $value : array_map(self::objectsToArrays(...), $value->getArrayCopy());
        }
        if (is_array($value)) {
            return array_map(self::objectsToArrays(...), $value);
        }

        return $value;
    }

    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** An empty map encodes as {} rather than []. */
    public static function map(array $value): array|ArrayObject
    {
        return $value === [] ? new ArrayObject : $value;
    }

    /** A plain array copy (for logging): empty objects become []. */
    public static function plain(mixed $value): mixed
    {
        if ($value instanceof ArrayObject) {
            return [];
        }
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? array_map(self::plain(...), $value) : $value;
    }

    /** A trimmed non-empty string, or null. */
    public static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** The array at $value, or [] when it is missing, null or an empty object. */
    public static function list(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
