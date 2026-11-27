<?php

namespace App\Services\Auth;

use App\Services\Support\Js;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Usernames: the Zenith customer Login, unique across all accounts and
 * fixed once created. One email can belong to several accounts.
 *
 * Zenith has no call that says whether a username is free (LoadCustomer
 * answers an unknown login and a wrong password alike, and counts the
 * attempt towards locking the account; NewCustomer only refuses a taken
 * login on a request that would otherwise create the account). So the
 * check as the customer types is against every account we know of, plus
 * the usernames Zenith has refused before; Zenith confirms it when the
 * account is created.
 */
class Usernames
{
    /** 3-50 characters: letters, digits, dots, underscores and hyphens, starting with a letter or digit. */
    public const PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{2,49}$/';

    public const RULES = '3 to 50 characters: letters, numbers, dots (.), underscores (_) and hyphens (-), starting with a letter or number. No spaces.';

    /** Names nobody may take: they would look like the airline. */
    private const RESERVED = ['admin', 'administrator', 'root', 'support', 'help', 'helpdesk', 'naoero', 'naoeroairlines', 'nauru',
        'nauruairlines', 'nauru-airlines', 'naoero-airlines', 'staff', 'system', 'zenith', 'citizenplane', 'security', 'info', 'contact',
        'noreply', 'no-reply', 'test', 'null', 'undefined'];

    public static function normalize(?string $username): string
    {
        return trim((string) $username);
    }

    /** Why the username can't be used as written; null when it can. */
    public static function problem(string $username): ?string
    {
        if (! preg_match(self::PATTERN, $username)) {
            return 'Usernames are '.lcfirst(self::RULES);
        }
        if (preg_match('/[._-]{2}/', $username) || preg_match('/[._-]$/', $username)) {
            return 'Usernames can’t end with, or repeat, dots, underscores or hyphens.';
        }
        if (in_array(strtolower($username), self::RESERVED, true)) {
            return 'This username is reserved. Please choose another one.';
        }

        return null;
    }

    /**
     * Whether the username can be chosen: as far as we know, as the customer
     * types (Zenith confirms it at sign-up).
     *
     * @return array{available: bool, valid: bool, message: string}
     */
    public function check(string $environment, string $username): array
    {
        $username = self::normalize($username);
        if ($problem = self::problem($username)) {
            return ['available' => false, 'valid' => false, 'message' => $problem];
        }
        if ($this->known($environment, $username)) {
            return ['available' => false, 'valid' => true, 'message' => 'This username is taken. Please choose another one.'];
        }

        return ['available' => true, 'valid' => true, 'message' => 'This username is available.'];
    }

    /** An account we have, or a username Zenith refused before. */
    public function known(string $environment, string $username): bool
    {
        return DB::table('customers')->where('zenith_environment', $environment)->whereRaw('lower(zenith_login) = lower(?)', [$username])->exists()
            || DB::table('zenith_taken_logins')->where('zenith_environment', $environment)->whereRaw('lower(login) = lower(?)', [$username])->exists();
    }

    /** Zenith said the username is taken (an account made elsewhere): remember it. */
    public function rememberTaken(string $environment, string $username): void
    {
        try {
            DB::table('zenith_taken_logins')->insert([
                'zenith_environment' => $environment, 'login' => $username, 'source' => 'signup', 'created_at' => Js::dbTime(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Known already.
        }
    }
}
