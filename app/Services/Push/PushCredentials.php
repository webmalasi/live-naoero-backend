<?php

namespace App\Services\Push;

use App\Services\Support\Js;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The keys pushes are sent with, kept in push_credentials and encrypted
 * with the app key: APNs (iPhones) and FCM (Android). Set in the admin
 * panel (System & Settings > Push Notifications); never sent to a client,
 * except the Firebase app settings an Android phone needs to get a token.
 */
class PushCredentials
{
    public const PROVIDERS = ['apns' => 'Apple Push (APNs)', 'fcm' => 'Firebase Cloud Messaging (FCM)'];

    /**
     * The saved settings, enabled or not; [] when none are saved. Read
     * fresh each time: the queue worker runs for days and staff may change
     * the keys meanwhile.
     *
     * @return array<string, mixed>
     */
    public function config(string $provider): array
    {
        return $this->load($provider)['config'];
    }

    /** Switched on and complete enough to send with. */
    public function enabled(string $provider): bool
    {
        $saved = $this->load($provider);

        return $saved['enabled'] && self::complete($provider, $saved['config']);
    }

    /** @return array{enabled: bool, config: array<string, mixed>} */
    private function load(string $provider): array
    {
        $row = DB::table('push_credentials')->where('provider', $provider)->first();
        if (! $row) {
            return ['enabled' => false, 'config' => []];
        }
        try {
            return ['enabled' => (bool) $row->enabled, 'config' => json_decode(Crypt::decryptString($row->config), true) ?: []];
        } catch (Throwable) {
            // Encrypted with another app key: treated as not set.
            return ['enabled' => false, 'config' => []];
        }
    }

    /** @param  array<string, mixed>  $config */
    public static function complete(string $provider, array $config): bool
    {
        return match ($provider) {
            'apns' => filled($config['team_id'] ?? null) && filled($config['key_id'] ?? null) && filled($config['bundle_id'] ?? null) && filled($config['private_key'] ?? null),
            'fcm' => filled($config['service_account']['project_id'] ?? null) && filled($config['service_account']['client_email'] ?? null) && filled($config['service_account']['private_key'] ?? null),
            default => false,
        };
    }

    /** @param  array<string, mixed>  $config */
    public function save(string $provider, array $config, bool $enabled, ?int $userId = null): void
    {
        $now = Js::dbTime();
        DB::table('push_credentials')->updateOrInsert(['provider' => $provider], [
            'enabled' => $enabled,
            'config' => Crypt::encryptString(json_encode($config, JSON_UNESCAPED_SLASHES)),
            'updated_by' => $userId,
            'updated_at' => $now,
            'created_at' => DB::table('push_credentials')->where('provider', $provider)->value('created_at') ?? $now,
        ]);
    }

    public function recordTest(string $provider, string $result): void
    {
        DB::table('push_credentials')->where('provider', $provider)->update(['last_test_at' => Js::dbTime(), 'last_test_result' => mb_substr($result, 0, 500)]);
    }

    /**
     * What the app needs before it can ask for a token. The Firebase app
     * settings are not secret (Google ships them inside every Android app);
     * the service account and the Apple key never leave the server.
     *
     * @return array<string, mixed>
     */
    public function clientConfig(): array
    {
        $fcm = $this->config('fcm');
        $android = $fcm['android'] ?? [];
        $fcmReady = $this->enabled('fcm') && filled($android['app_id'] ?? null) && filled($android['api_key'] ?? null);

        return [
            'ios' => ['enabled' => $this->enabled('apns')],
            'android' => [
                'enabled' => $fcmReady,
                'firebase' => $fcmReady ? [
                    'apiKey' => $android['api_key'],
                    'appId' => $android['app_id'],
                    'messagingSenderId' => (string) ($android['sender_id'] ?? ''),
                    'projectId' => $fcm['service_account']['project_id'],
                ] : null,
                'channelId' => FcmClient::CHANNEL,
            ],
        ];
    }
}
