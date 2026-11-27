<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Exceptions\TokenReuseException;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Device sessions. Access tokens are short-lived JWTs and are not stored;
 * refresh tokens are random, kept only as SHA-256 hashes, and rotated on
 * every use. All tokens descended from one login share a family; presenting
 * an already-rotated token means it leaked, and ends the whole family.
 *
 * A session stays alive while used at least weekly, and ends 30 days after
 * login no matter what: Zenith sends no "password changed" event, so a
 * session on another device can't be ended by a reset.
 */
class CustomerSessions
{
    public const REFRESH_TOKEN_IDLE_DAYS = 7;

    public const SESSION_MAX_DAYS = 30;

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** @return array{id: string, token: string, expiresAt: CarbonImmutable} */
    private function insertRefreshToken(string $customerId, string $familyId, RequestMeta $meta, ?CarbonImmutable $sessionStartedAt = null): array
    {
        $now = CarbonImmutable::now();
        $sessionStartedAt ??= $now;
        $idle = $now->addDays(self::REFRESH_TOKEN_IDLE_DAYS);
        $max = $sessionStartedAt->addDays(self::SESSION_MAX_DAYS);
        $expiresAt = $idle->lessThan($max) ? $idle : $max;
        $token = self::newToken();
        $id = (string) Str::uuid();
        DB::table('refresh_tokens')->insert([
            'id' => $id,
            'customer_id' => $customerId,
            'family_id' => $familyId,
            'token_hash' => self::hash($token),
            'device_label' => $meta->deviceLabel,
            'ip' => $meta->ip,
            'created_at' => Js::dbTime($now),
            'expires_at' => Js::dbTime($expiresAt),
        ]);

        return ['id' => $id, 'token' => $token, 'expiresAt' => $expiresAt];
    }

    /** @return array<string, mixed> */
    private static function tokens(string $customerId, string $familyId, array $refresh): array
    {
        return [
            'sessionId' => $familyId,
            'accessToken' => AccessToken::sign($customerId, $familyId),
            'accessTokenExpiresIn' => AccessToken::TTL_SECONDS,
            'refreshToken' => $refresh['token'],
            'refreshTokenExpiresAt' => Js::iso($refresh['expiresAt']),
        ];
    }

    /** Starts a new device session: a fresh token family. */
    public function start(string $customerId, RequestMeta $meta): array
    {
        $familyId = (string) Str::uuid();

        return self::tokens($customerId, $familyId, $this->insertRefreshToken($customerId, $familyId, $meta));
    }

    /**
     * Exchanges a refresh token for a new pair (rotation). Must run inside a
     * transaction.
     *
     * @return array{customerId: string, tokens: array<string, mixed>}
     */
    public function rotate(string $refreshToken, RequestMeta $meta): array
    {
        $current = DB::table('refresh_tokens as t')
            ->join('customers as c', 'c.id', '=', 't.customer_id')
            ->where('t.token_hash', self::hash($refreshToken))
            ->select('t.id', 't.customer_id', 't.family_id', 't.expires_at', 't.revoked_at', 't.revoked_reason', 'c.status')
            ->lockForUpdate()
            ->first();
        if (! $current) {
            throw ApiException::sessionExpired();
        }
        if ($current->revoked_at) {
            // Only a rotated token signals theft; one ended by logout is just stale.
            if ($current->revoked_reason === 'rotated') {
                throw new TokenReuseException($current->family_id, $current->customer_id);
            }
            throw ApiException::sessionExpired();
        }
        if (CarbonImmutable::parse($current->expires_at)->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ApiException::sessionExpired();
        }
        if ($current->status !== 'active') {
            throw ApiException::accountDisabled();
        }

        $started = DB::table('refresh_tokens')->where('family_id', $current->family_id)->min('created_at');
        $sessionStartedAt = $started ? CarbonImmutable::parse($started) : CarbonImmutable::now();
        if (CarbonImmutable::now()->greaterThanOrEqualTo($sessionStartedAt->addDays(self::SESSION_MAX_DAYS))) {
            throw ApiException::sessionExpired();
        }

        $next = $this->insertRefreshToken($current->customer_id, $current->family_id, $meta, $sessionStartedAt);
        DB::table('refresh_tokens')->where('id', $current->id)->update([
            'revoked_at' => Js::dbTime(),
            'revoked_reason' => 'rotated',
            'replaced_by' => $next['id'],
            'last_used_at' => Js::dbTime(),
        ]);

        return ['customerId' => $current->customer_id, 'tokens' => self::tokens($current->customer_id, $current->family_id, $next)];
    }

    public function revokeFamily(string $familyId, string $reason): void
    {
        DB::table('refresh_tokens')->where('family_id', $familyId)->whereNull('revoked_at')
            ->update(['revoked_at' => Js::dbTime(), 'revoked_reason' => $reason]);
    }

    /** Ends every session of the customer except $keepFamilyId (if given). */
    public function revokeOthers(string $customerId, ?string $keepFamilyId, string $reason): int
    {
        return DB::table('refresh_tokens')
            ->where('customer_id', $customerId)
            ->whereNull('revoked_at')
            ->when($keepFamilyId, fn ($q) => $q->where('family_id', '<>', $keepFamilyId))
            ->update(['revoked_at' => Js::dbTime(), 'revoked_reason' => $reason]);
    }

    /** Active sessions of the customer other than $exceptFamilyId. */
    public function countOthers(string $customerId, string $exceptFamilyId): int
    {
        return (int) DB::table('refresh_tokens')
            ->where('customer_id', $customerId)
            ->where('family_id', '<>', $exceptFamilyId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Js::dbTime())
            ->distinct()
            ->count('family_id');
    }

    /** Whether the session (token family) still has a live refresh token. */
    public function isActive(string $familyId): bool
    {
        return DB::table('refresh_tokens')
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', Js::dbTime())
            ->exists();
    }

    /** @return object{family_id: string, customer_id: string}|null */
    public function familyOf(string $refreshToken): ?object
    {
        return DB::table('refresh_tokens')->where('token_hash', self::hash($refreshToken))->select('family_id', 'customer_id')->first();
    }
}
