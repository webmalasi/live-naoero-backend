<?php

namespace App\Services\Auth;

use App\Services\Zenith\CachedValue;

/**
 * The airline's preproduction system has no password policy configured
 * (CustomerPasswordPolicies returns none) and accepts even "1", so this is
 * the floor enforced for new passwords: 8+ characters with an uppercase
 * letter, a lowercase letter, a number and a symbol. Any stricter airline
 * policy is added on top. Existing passwords still log in: Zenith alone
 * decides whether a password is right.
 */
class PasswordPolicy
{
    public const MAX_LENGTH = 64;

    public const BASE = [
        'MinLen' => 8,
        'MinUpperLetterCount' => 1,
        'MinLowerLetterCount' => 1,
        'MinLetterCount' => 1,
        'MinDigitCount' => 1,
        'MinSymbolCount' => 1,
        'SymbolChars' => '!@#$%^&*()-_=+[]{};:\'",.<>/?\\|`~',
    ];

    public function __construct(private readonly ZenithCustomers $customers) {}

    /** Our minimum plus any stricter rules the airline configures (cached 1 hour). */
    public function effective(): array
    {
        return CachedValue::get('auth:password-policy', 3600, fn () => self::merge($this->customers->passwordPolicies()));
    }

    public static function merge(array $airline): array
    {
        $merged = self::BASE;
        foreach ($airline as $p) {
            foreach (['MinLen', 'MinUpperLetterCount', 'MinLowerLetterCount', 'MinLetterCount', 'MinDigitCount', 'MinSymbolCount'] as $key) {
                $merged[$key] = max($merged[$key], (int) ($p[$key] ?? 0));
            }
            if (! empty($p['SymbolChars'])) {
                $merged['SymbolChars'] = $p['SymbolChars'];
            }
        }

        return $merged;
    }

    /** The policy as the app receives it. */
    public static function describe(array $policy): array
    {
        return [
            'minLength' => $policy['MinLen'],
            'maxLength' => self::MAX_LENGTH,
            'minLetters' => $policy['MinLetterCount'],
            'minUppercase' => $policy['MinUpperLetterCount'],
            'minLowercase' => $policy['MinLowerLetterCount'],
            'minDigits' => $policy['MinDigitCount'],
            'minSymbols' => $policy['MinSymbolCount'],
            'symbols' => $policy['SymbolChars'],
        ];
    }

    /** Human-readable problems with $password, empty when it passes. */
    public static function problems(string $password, array $policy): array
    {
        $problems = [];
        $count = fn (string $re) => preg_match_all($re, $password);
        $symbols = 0;
        foreach (mb_str_split($password) as $char) {
            if (str_contains($policy['SymbolChars'], $char)) {
                $symbols++;
            }
        }
        $length = mb_strlen($password);

        if ($length < $policy['MinLen']) {
            $problems[] = "at least {$policy['MinLen']} characters";
        }
        if ($length > self::MAX_LENGTH) {
            $problems[] = 'at most '.self::MAX_LENGTH.' characters';
        }
        if ($count('/[A-Za-z]/') < $policy['MinLetterCount']) {
            $problems[] = 'a letter';
        }
        if ($count('/[A-Z]/') < $policy['MinUpperLetterCount']) {
            $problems[] = 'an uppercase letter';
        }
        if ($count('/[a-z]/') < $policy['MinLowerLetterCount']) {
            $problems[] = 'a lowercase letter';
        }
        if ($count('/\d/') < $policy['MinDigitCount']) {
            $problems[] = 'a number';
        }
        if ($symbols < $policy['MinSymbolCount']) {
            $problems[] = 'a symbol';
        }
        if (preg_match('/^\s|\s$/u', $password)) {
            $problems[] = 'no spaces at the start or end';
        }

        return $problems;
    }

    public static function sentence(array $problems): string
    {
        return 'Your password needs '.preg_replace('/, ([^,]*)$/', ' and $1', implode(', ', $problems)).'.';
    }
}
