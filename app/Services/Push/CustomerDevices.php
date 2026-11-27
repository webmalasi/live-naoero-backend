<?php

namespace App\Services\Push;

use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Each app install that can receive pushes (customer_devices): its UID,
 * the customer and sign-in session it belongs to, its push token and what
 * it wants to receive. The app registers on every launch and sign-in, and
 * whenever Apple or Google give it a new token.
 */
class CustomerDevices
{
    public const PLATFORMS = ['ios' => 'apns', 'android' => 'fcm'];

    /**
     * Adds or updates the install $uid for the signed-in customer. A phone
     * passed to another account moves with it; a token now held by another
     * install (the app was reinstalled) is taken from the old one.
     *
     * @param  array<string, mixed>  $input  validated by DeviceController
     */
    public function register(string $customerId, string $familyId, string $uid, array $input): object
    {
        $now = Js::dbTime();
        $platform = $input['platform'];
        $token = isset($input['pushToken']) && $input['pushToken'] !== '' ? trim((string) $input['pushToken']) : null;
        $hash = $token === null ? null : hash('sha256', $token);

        return DB::transaction(function () use ($customerId, $familyId, $uid, $input, $now, $platform, $token, $hash) {
            $existing = DB::table('customer_devices')->where('device_uid', $uid)->lockForUpdate()->first();
            if ($hash !== null) {
                DB::table('customer_devices')->where('token_hash', $hash)->where('device_uid', '!=', $uid)
                    ->update(['push_token' => null, 'token_hash' => null, 'updated_at' => $now]);
            }

            $values = [
                'customer_id' => $customerId,
                'session_family_id' => $familyId,
                'platform' => $platform,
                'provider' => self::PLATFORMS[$platform],
                'apns_environment' => $platform === 'ios' ? ($input['apnsEnvironment'] ?? 'production') : null,
                'app_version' => $input['appVersion'] ?? null,
                'os_version' => $input['osVersion'] ?? null,
                'device_model' => $input['deviceModel'] ?? null,
                'locale' => $input['locale'] ?? null,
                'timezone' => $input['timezone'] ?? null,
                'notifications_enabled' => (bool) ($input['notificationsEnabled'] ?? true),
                'last_registered_at' => $now,
                'updated_at' => $now,
            ];
            // Left out: keep what was set. Sent: replace.
            if (array_key_exists('pushEnabled', $input)) {
                $values['push_enabled'] = (bool) $input['pushEnabled'];
            }
            if (array_key_exists('mutedCategories', $input)) {
                $values['muted_categories'] = Js::encode(array_values(array_unique($input['mutedCategories'] ?? [])));
            }
            if (array_key_exists('pushToken', $input)) {
                $changed = $existing === null || $existing->token_hash !== $hash;
                $values['push_token'] = $token === null ? null : Crypt::encryptString($token);
                $values['token_hash'] = $hash;
                if ($changed) {
                    $values += ['token_invalid_at' => null, 'failure_count' => 0, 'last_error' => null];
                }
            }
            // A phone signed into another account starts with that account's choices.
            if ($existing && $existing->customer_id !== $customerId) {
                $values += ['push_enabled' => true, 'muted_categories' => null];
            }

            if ($existing) {
                DB::table('customer_devices')->where('id', $existing->id)->update($values);
            } else {
                DB::table('customer_devices')->insert($values + ['id' => (string) Str::uuid(), 'device_uid' => $uid, 'created_at' => $now]);
            }

            return DB::table('customer_devices')->where('device_uid', $uid)->first();
        });
    }

    /** Signing out on this phone: it stops receiving this customer's pushes. */
    public function unlink(string $customerId, string $uid): bool
    {
        return DB::table('customer_devices')->where('device_uid', $uid)->where('customer_id', $customerId)
            ->update(['customer_id' => null, 'session_family_id' => null, 'updated_at' => Js::dbTime()]) > 0;
    }

    /**
     * The installs a notice in $category goes to: signed in to a session
     * that is still active, with a valid token and pushes turned on.
     *
     * @return list<object>
     */
    public function recipients(string $customerId, ?string $category = null): array
    {
        $now = Js::dbTime();

        return DB::table('customer_devices as d')
            ->where('d.customer_id', $customerId)
            ->whereNotNull('d.push_token')->whereNull('d.token_invalid_at')
            ->where('d.notifications_enabled', true)->where('d.push_enabled', true)
            ->whereExists(fn ($q) => $q->from('refresh_tokens as t')->whereColumn('t.family_id', 'd.session_family_id')
                ->whereNull('t.revoked_at')->where('t.expires_at', '>', $now))
            ->get()
            ->filter(fn ($d) => $category === null || ! in_array($category, self::muted($d), true))
            ->values()->all();
    }

    /** @return list<string> */
    public static function muted(object $device): array
    {
        $muted = Js::decode($device->muted_categories ?? null);

        return is_array($muted) ? array_values(array_filter($muted, 'is_string')) : [];
    }

    public static function token(object $device): ?string
    {
        try {
            return $device->push_token ? Crypt::decryptString($device->push_token) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** How the app sees one of its installs: never the token itself. */
    public static function present(object $d, ?string $currentUid = null): array
    {
        return [
            'deviceUid' => $d->device_uid,
            'platform' => $d->platform,
            'provider' => $d->provider,
            'hasPushToken' => $d->push_token !== null && $d->token_invalid_at === null,
            'apnsEnvironment' => $d->apns_environment,
            'appVersion' => $d->app_version,
            'osVersion' => $d->os_version,
            'deviceModel' => $d->device_model,
            'notificationsEnabled' => (bool) $d->notifications_enabled,
            'pushEnabled' => (bool) $d->push_enabled,
            'mutedCategories' => self::muted($d),
            'categories' => CustomerNotices::CATEGORIES,
            'lastRegisteredAt' => Js::iso($d->last_registered_at),
            'lastPushAt' => Js::iso($d->last_push_at),
            'current' => $currentUid !== null && $currentUid === $d->device_uid,
        ];
    }
}
