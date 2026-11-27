<?php

namespace App\Services\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Phone numbers, one way everywhere: parsed and validated per country with
 * Google's libphonenumber, stored in E.164 ("+919899765000", what Zenith
 * asks for and what SMS gateways need) with the country it belongs to.
 *
 * Zenith's country list has no dialling codes (its own front office uses a
 * built-in widget), so the codes come from libphonenumber and the country
 * names from Zenith.
 */
final class Phones
{
    /** Shown first in pickers: the airline's home markets. */
    public const FIRST = ['AU', 'NR'];

    private static function util(): PhoneNumberUtil
    {
        return PhoneNumberUtil::getInstance();
    }

    /**
     * A number as entered ("+91 98997 65000", "0061 400 000 001",
     * "61400000001"), with an optional country for numbers written without
     * their code. Null when it is not a valid number.
     *
     * @return array{e164: string, country: ?string, dialCode: string, national: string, international: string, mobile: bool, landline: bool}|null
     */
    public static function parse(?string $input, ?string $country = null): ?array
    {
        $raw = trim((string) $input);
        if ($raw === '' || strlen($raw) > 40) {
            return null;
        }
        $digits = preg_replace('/[^\d+]/', '', $raw);
        if (str_starts_with($digits, '00')) {
            $digits = '+'.substr($digits, 2);
        }
        $region = $country !== null && preg_match('/^[A-Za-z]{2}$/', $country) ? strtoupper($country) : null;
        $tries = [];
        if (str_starts_with($digits, '+')) {
            $tries[] = [$digits, $region];
            // "+61 0412 345 678": the national trunk 0 written after the code.
            if (preg_match('/^\+(\d{1,3})0(\d{4,})$/', $digits)) {
                foreach ([1, 2, 3] as $len) {
                    $tries[] = ['+'.substr($digits, 1, $len).ltrim(substr($digits, 1 + $len), '0'), null];
                }
            }
        } else {
            if ($region) {
                $tries[] = [$digits, $region];
            }
            // Written with the country code but no "+" (accepted before).
            $tries[] = ['+'.$digits, null];
        }
        foreach ($tries as [$candidate, $default]) {
            try {
                $number = self::util()->parse($candidate, $default);
            } catch (NumberParseException) {
                continue;
            }
            if (self::util()->isValidNumber($number)) {
                return self::describe($number, $region);
            }
        }

        return null;
    }

    /** $preferred: the country the customer chose, when the number is valid there too (+1, +44, +7...). */
    private static function describe(PhoneNumber $number, ?string $preferred = null): array
    {
        $u = self::util();
        $type = $u->getNumberType($number);
        $country = $preferred && $u->isValidNumberForRegion($number, $preferred) ? $preferred : $u->getRegionCodeForNumber($number);

        return [
            'e164' => $u->format($number, PhoneNumberFormat::E164),
            'country' => $country,
            'dialCode' => '+'.$number->getCountryCode(),
            'national' => $u->format($number, PhoneNumberFormat::NATIONAL),
            'international' => $u->format($number, PhoneNumberFormat::INTERNATIONAL),
            'mobile' => in_array($type, [PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE], true),
            'landline' => $type === PhoneNumberType::FIXED_LINE,
        ];
    }

    /** E.164 for a valid number, else the input unchanged (validation then reports it). */
    public static function normalize(mixed $value, ?string $country = null): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        if (trim($value) === '') {
            return $value;
        }

        return self::parse($value, $country)['e164'] ?? $value;
    }

    /** The country of a stored number ("+1" numbers resolve to their own region). */
    public static function country(?string $e164): ?string
    {
        return self::parse($e164)['country'] ?? null;
    }

    /**
     * Why a number can't be used, or null when it can. $mobile: it must be
     * able to receive text messages (not a landline).
     */
    public static function problem(mixed $value, bool $mobile = false, ?string $label = null): ?string
    {
        $what = $label ?? 'phone number';
        if (! is_string($value) || trim($value) === '') {
            return "Enter a {$what}.";
        }
        $p = self::parse($value);
        if (! $p) {
            return "Enter a valid {$what} with its country code, for example +61 412 345 678.";
        }
        if ($mobile && $p['landline']) {
            return 'This is a landline number. Enter a mobile number so we can send you text messages.';
        }

        return null;
    }

    /** A Laravel validation rule (closure) for a phone field. */
    public static function rule(bool $mobile = false, ?string $label = null): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($mobile, $label): void {
            if ($value === null || $value === '') {
                return; // required-ness is decided by the other rules
            }
            if ($why = self::problem($value, $mobile, $label)) {
                $fail($why);
            }
        };
    }

    /** "🇦🇺" for "AU". */
    public static function flag(?string $country): string
    {
        if (! is_string($country) || ! preg_match('/^[A-Z]{2}$/', $country)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($country[0]) - 65).mb_chr(0x1F1E6 + ord($country[1]) - 65);
    }

    /** "🇮🇳 +91 98997 65000" for the admin panel; the input as is when not a valid number. */
    public static function display(?string $value): string
    {
        $p = self::parse($value);
        if (! $p) {
            return trim((string) $value);
        }

        return trim(self::flag($p['country']).' '.$p['international']);
    }

    /**
     * Every country with its dialling code, for pickers: Zenith's country
     * names (code => name) with libphonenumber's codes and an example mobile
     * number. The home markets come first, then by name.
     *
     * @param  array<string, string>  $names
     * @return list<array{code: string, name: string, dialCode: string, flag: string, example: ?string}>
     */
    public static function countries(array $names): array
    {
        $u = self::util();
        $out = [];
        foreach ($names as $code => $name) {
            $code = strtoupper((string) $code);
            if (! preg_match('/^[A-Z]{2}$/', $code)) {
                continue;
            }
            $dial = $u->getCountryCodeForRegion($code);
            if ($dial <= 0) {
                continue; // no telephone numbering of its own
            }
            $example = $u->getExampleNumberForType($code, PhoneNumberType::MOBILE);
            $out[] = [
                'code' => $code,
                'name' => (string) $name,
                'dialCode' => '+'.$dial,
                'flag' => self::flag($code),
                'example' => $example ? $u->format($example, PhoneNumberFormat::NATIONAL) : null,
            ];
        }
        usort($out, function ($a, $b) {
            $ra = array_search($a['code'], self::FIRST, true);
            $rb = array_search($b['code'], self::FIRST, true);
            $ra = $ra === false ? 99 : $ra;
            $rb = $rb === false ? 99 : $rb;

            return $ra <=> $rb ?: strcoll($a['name'], $b['name']);
        });

        return $out;
    }
}
