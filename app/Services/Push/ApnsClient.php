<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends to iPhones straight through Apple (APNs, HTTP/2), signed with the
 * airline's .p8 key: a token-based ES256 JWT, reused for 50 minutes as
 * Apple asks (a new one at most every 20 minutes, none older than an hour).
 */
class ApnsClient
{
    public const HOSTS = [
        'production' => 'https://api.push.apple.com',
        'sandbox' => 'https://api.sandbox.push.apple.com',
    ];

    public function __construct(private readonly PushCredentials $credentials) {}

    /**
     * @param  array<string, mixed>  $payload  the full APNs body (aps plus our keys)
     */
    public function send(string $token, string $environment, array $payload, ?string $collapseId = null): PushResult
    {
        $config = $this->credentials->config('apns');
        if (! PushCredentials::complete('apns', $config)) {
            return PushResult::failed(null, 'APNs is not set up.');
        }
        if (! preg_match('/^[0-9a-f]{64,200}$/i', $token)) {
            return PushResult::invalidToken(null, 'Not an APNs device token.');
        }
        try {
            $jwt = $this->jwt($config);
        } catch (Throwable $e) {
            return PushResult::failed(null, 'The APNs key could not sign: '.$e->getMessage());
        }

        $host = self::HOSTS[$environment] ?? self::HOSTS['production'];
        try {
            $response = Http::withOptions(['version' => 2.0])
                ->timeout(15)
                ->withHeaders(array_filter([
                    'authorization' => "bearer {$jwt}",
                    'apns-topic' => $config['bundle_id'],
                    'apns-push-type' => 'alert',
                    'apns-priority' => '10',
                    'apns-expiration' => (string) (time() + 3 * 86400),
                    'apns-collapse-id' => $collapseId ? substr($collapseId, 0, 64) : null,
                ]))
                ->post("{$host}/3/device/{$token}", $payload);
        } catch (Throwable $e) {
            return PushResult::failed(null, 'Apple could not be reached: '.$e->getMessage(), retryable: true);
        }

        $status = $response->status();
        if ($status === 200) {
            return PushResult::sent($status, $response->header('apns-id') ?: null);
        }
        $reason = (string) ($response->json('reason') ?? 'HTTP '.$status);
        if ($status === 410 || in_array($reason, ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
            return PushResult::invalidToken($status, $reason);
        }
        if ($status === 403 && in_array($reason, ['ExpiredProviderToken', 'InvalidProviderToken'], true)) {
            Cache::forget(self::cacheKey($config));
        }

        return PushResult::failed($status, $reason, retryable: $status === 429 || $status >= 500 || $reason === 'ExpiredProviderToken');
    }

    /** @param  array<string, mixed>  $config */
    public function jwt(array $config, ?int $now = null): string
    {
        return Cache::remember(self::cacheKey($config), now()->addMinutes(50), fn () => self::sign($config, $now ?? time()));
    }

    /** @param  array<string, mixed>  $config */
    public static function sign(array $config, int $now): string
    {
        $key = openssl_pkey_get_private((string) $config['private_key']);
        if ($key === false) {
            throw new RuntimeException('the .p8 key is not a valid private key');
        }
        $header = self::b64(json_encode(['alg' => 'ES256', 'kid' => $config['key_id']]));
        $claims = self::b64(json_encode(['iss' => $config['team_id'], 'iat' => $now]));
        if (! openssl_sign("{$header}.{$claims}", $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('signing failed');
        }

        return "{$header}.{$claims}.".self::b64(self::derToJose($der));
    }

    /** openssl gives an ECDSA signature in DER; a JWT wants r and s, 32 bytes each. */
    public static function derToJose(string $der): string
    {
        $pos = 2; // SEQUENCE tag and length (a P-256 signature is under 128 bytes)
        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            if (ord($der[$pos]) !== 0x02) {
                throw new RuntimeException('unexpected signature format');
            }
            $len = ord($der[$pos + 1]);
            $parts[] = str_pad(ltrim(substr($der, $pos + 2, $len), "\0"), 32, "\0", STR_PAD_LEFT);
            $pos += 2 + $len;
        }

        return $parts[0].$parts[1];
    }

    public static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** @param  array<string, mixed>  $config */
    private static function cacheKey(array $config): string
    {
        return 'push:apns-jwt:'.sha1($config['team_id'].'|'.$config['key_id'].'|'.$config['private_key']);
    }
}
