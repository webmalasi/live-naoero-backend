<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Sends to Android phones through Firebase Cloud Messaging (HTTP v1). FCM
 * is only the delivery pipe: tokens, keys and notices all live in our
 * database. Authorised with the Firebase project's service account: a
 * signed JWT exchanged for an hour-long Google access token, reused for 50
 * minutes.
 */
class FcmClient
{
    /** The Android notification channel the app creates. */
    public const CHANNEL = 'naoero_updates';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(private readonly PushCredentials $credentials) {}

    /**
     * @param  array<string, string>  $data  string values only (FCM's rule)
     */
    public function send(string $token, string $title, string $body, array $data, ?string $tag = null): PushResult
    {
        $config = $this->credentials->config('fcm');
        if (! PushCredentials::complete('fcm', $config)) {
            return PushResult::failed(null, 'FCM is not set up.');
        }
        $account = $config['service_account'];
        try {
            $access = $this->accessToken($account);
        } catch (Throwable $e) {
            return PushResult::failed(null, 'Google sign-in for FCM failed: '.$e->getMessage(), retryable: true);
        }

        $message = [
            'token' => $token,
            'notification' => ['title' => $title, 'body' => $body],
            'data' => $data,
            'android' => [
                'priority' => 'HIGH',
                'ttl' => (3 * 86400).'s',
                'notification' => array_filter(['channel_id' => self::CHANNEL, 'tag' => $tag, 'default_sound' => true]),
            ],
        ];
        try {
            $response = Http::withToken($access)->timeout(15)
                ->post("https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send", ['message' => $message]);
        } catch (Throwable $e) {
            return PushResult::failed(null, 'Google could not be reached: '.$e->getMessage(), retryable: true);
        }

        $status = $response->status();
        if ($status === 200) {
            return PushResult::sent($status, $response->json('name'));
        }
        $error = (string) ($response->json('error.message') ?? 'HTTP '.$status);
        $codes = collect($response->json('error.details') ?? [])->pluck('errorCode')->filter()->all();
        // 404 / UNREGISTERED: the app was removed; 400 naming the token: it was never valid.
        if ($status === 404 || in_array('UNREGISTERED', $codes, true)
            || ($status === 400 && str_contains(strtolower($error), 'registration token'))) {
            return PushResult::invalidToken($status, $error);
        }
        if ($status === 401) {
            Cache::forget(self::cacheKey($account));
        }

        return PushResult::failed($status, $error, retryable: in_array($status, [401, 429], true) || $status >= 500);
    }

    /** @param  array<string, mixed>  $account  the service account JSON */
    public function accessToken(array $account): string
    {
        return Cache::remember(self::cacheKey($account), now()->addMinutes(50), function () use ($account) {
            $uri = $account['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $response = Http::asForm()->timeout(15)->post($uri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => self::assertion($account, time()),
            ]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token)) {
                throw new RuntimeException((string) ($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()));
            }

            return $token;
        });
    }

    /** @param  array<string, mixed>  $account */
    public static function assertion(array $account, int $now): string
    {
        $key = openssl_pkey_get_private((string) $account['private_key']);
        if ($key === false) {
            throw new RuntimeException('the service account private key is not valid');
        }
        $header = ApnsClient::b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = ApnsClient::b64(json_encode([
            'iss' => $account['client_email'],
            'scope' => self::SCOPE,
            'aud' => $account['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES));
        openssl_sign("{$header}.{$claims}", $signature, $key, OPENSSL_ALGO_SHA256);

        return "{$header}.{$claims}.".ApnsClient::b64($signature);
    }

    /** @param  array<string, mixed>  $account */
    private static function cacheKey(array $account): string
    {
        return 'push:fcm-token:'.sha1(($account['client_email'] ?? '').'|'.($account['private_key'] ?? ''));
    }
}
