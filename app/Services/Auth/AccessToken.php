<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use RuntimeException;

/**
 * Short-lived access tokens: HS256 JWTs carrying the customer (sub) and the
 * device session (sid). Same issuer, audience and secret as before, so
 * tokens issued earlier stay valid.
 */
final class AccessToken
{
    public const TTL_SECONDS = 15 * 60;

    private const ISSUER = 'naoero-proxy';

    private const AUDIENCE = 'naoero-app';

    private static function secret(): string
    {
        $secret = trim((string) config('naoero.jwt_secret'));
        if (strlen($secret) < 32) {
            throw new RuntimeException('AUTH_JWT_SECRET must be set to at least 32 characters.');
        }

        return $secret;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string|false
    {
        return base64_decode(strtr($data, '-_', '+/'), true);
    }

    public static function sign(string $customerId, string $sessionId, ?int $now = null): string
    {
        $now ??= time();
        $header = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = self::b64(json_encode([
            'sid' => $sessionId,
            'sub' => $customerId,
            'iss' => self::ISSUER,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + self::TTL_SECONDS,
        ]));
        $signature = self::b64(hash_hmac('sha256', "{$header}.{$payload}", self::secret(), true));

        return "{$header}.{$payload}.{$signature}";
    }

    /**
     * @return array{customerId: string, sessionId: string}
     *
     * @throws ApiException unauthenticated
     */
    public static function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw ApiException::unauthenticated();
        }
        [$header, $payload, $signature] = $parts;
        $expected = self::b64(hash_hmac('sha256', "{$header}.{$payload}", self::secret(), true));
        if (! hash_equals($expected, $signature)) {
            throw ApiException::unauthenticated();
        }

        $h = json_decode((string) self::unb64($header), true);
        $claims = json_decode((string) self::unb64($payload), true);
        if (! is_array($h) || ($h['alg'] ?? null) !== 'HS256' || ! is_array($claims)) {
            throw ApiException::unauthenticated();
        }
        $now = time();
        $audience = $claims['aud'] ?? null;
        $audienceOk = is_array($audience) ? in_array(self::AUDIENCE, $audience, true) : $audience === self::AUDIENCE;
        if (($claims['iss'] ?? null) !== self::ISSUER || ! $audienceOk) {
            throw ApiException::unauthenticated();
        }
        if (! is_numeric($claims['exp'] ?? null) || $claims['exp'] <= $now) {
            throw ApiException::unauthenticated();
        }
        if (isset($claims['nbf']) && (! is_numeric($claims['nbf']) || $claims['nbf'] > $now)) {
            throw ApiException::unauthenticated();
        }
        if (! is_string($claims['sub'] ?? null) || ! is_string($claims['sid'] ?? null)) {
            throw ApiException::unauthenticated();
        }

        return ['customerId' => $claims['sub'], 'sessionId' => $claims['sid']];
    }
}
