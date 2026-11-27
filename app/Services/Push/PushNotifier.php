<?php

namespace App\Services\Push;

use App\Services\Logging\ServerLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;

/**
 * Pushes a saved notice (customer_notifications) to every phone of the
 * customer that can receive it: iPhones through APNs, Android through FCM.
 * Each push is one push_deliveries row, so a retried job never pushes the
 * same notice to a phone twice, and the admin panel shows what Apple or
 * Google answered. A token they reject is marked and never used again.
 */
class PushNotifier
{
    public function __construct(
        private readonly CustomerDevices $devices,
        private readonly PushCredentials $credentials,
        private readonly ApnsClient $apns,
        private readonly FcmClient $fcm,
        private readonly ServerLog $log,
    ) {}

    /**
     * @return array{sent: int, failed: int, retryable: int}
     */
    public function forNotification(string $notificationId): array
    {
        $totals = ['sent' => 0, 'failed' => 0, 'retryable' => 0];
        $n = DB::table('customer_notifications')->where('id', $notificationId)->first();
        if (! $n || $n->read_at !== null) {
            return $totals;
        }
        $category = $n->category ?? CustomerNotices::category($n->type);
        $devices = array_filter($this->devices->recipients($n->customer_id, $category), fn ($d) => $this->credentials->enabled($d->provider));
        if ($devices === []) {
            return $totals;
        }

        $data = Js::decode($n->data);
        $data = is_array($data) ? $data : [];
        $message = [
            'title' => $n->title,
            'body' => $n->body,
            'data' => array_filter([
                'notificationId' => $n->id,
                'type' => $n->type,
                'category' => $category,
                'pnr' => $n->pnr,
                'orderId' => $n->shop_order_id ?? null,
                'action' => ($action = CustomerNotices::action($n, $data)) ? Js::encode($action) : null,
                'createdAt' => Js::iso($n->created_at),
            ], fn ($v) => $v !== null),
            'badge' => DB::table('customer_notifications')->where('customer_id', $n->customer_id)->whereNull('read_at')->count(),
        ];

        foreach ($devices as $device) {
            $result = $this->deliver($device, $message, $n->id);
            if ($result === null) {
                continue;
            }
            $totals[$result->ok() ? 'sent' : 'failed']++;
            $totals['retryable'] += $result->retryable ? 1 : 0;
        }
        if ($totals['failed'] > 0) {
            $this->log->warn('push', 'some pushes failed', ['notification' => $n->id] + $totals);
        }

        return $totals;
    }

    /** A test push from the admin panel to one install. */
    public function test(string $deviceId): PushResult
    {
        $device = DB::table('customer_devices')->where('id', $deviceId)->first();
        if (! $device) {
            return PushResult::failed(null, 'Device not found.');
        }
        if (! $this->credentials->enabled($device->provider)) {
            return PushResult::failed(null, PushCredentials::PROVIDERS[$device->provider].' is not set up or switched off.');
        }
        $result = $this->deliver($device, [
            'title' => 'Naoero Airlines',
            'body' => 'This is a test notification. Pushes to this phone are working.',
            'data' => ['type' => 'test', 'category' => 'account'],
            'badge' => null,
        ], null, test: true);
        $this->credentials->recordTest($device->provider, ($result?->status ?? 'skipped').($result?->error ? ': '.$result->error : ''));

        return $result ?? PushResult::failed(null, 'Nothing was sent.');
    }

    /**
     * @param  array{title: string, body: string, data: array<string, string>, badge: ?int}  $message
     */
    private function deliver(object $device, array $message, ?string $notificationId, bool $test = false): ?PushResult
    {
        $now = Js::dbTime();
        $deliveryId = null;
        if ($notificationId !== null) {
            $previous = DB::table('push_deliveries')->where('notification_id', $notificationId)->where('device_id', $device->id)->first();
            if ($previous && in_array($previous->status, [PushResult::SENT, PushResult::INVALID_TOKEN], true)) {
                return null; // already pushed (a retried job)
            }
            $deliveryId = $previous->id ?? null;
        }
        $row = [
            'notification_id' => $notificationId,
            'device_id' => $device->id,
            'customer_id' => $device->customer_id,
            'provider' => $device->provider,
            'status' => 'sending',
            'title' => mb_substr($message['title'], 0, 200),
            'is_test' => $test,
        ];
        if ($deliveryId) {
            DB::table('push_deliveries')->where('id', $deliveryId)->update($row);
        } else {
            $deliveryId = DB::table('push_deliveries')->insertGetId($row + ['created_at' => $now]);
        }

        $token = CustomerDevices::token($device);
        $result = match (true) {
            $token === null => PushResult::invalidToken(null, 'The saved token could not be read.'),
            $device->provider === 'apns' => $this->apns->send($token, $device->apns_environment ?? 'production', self::apnsPayload($message), $notificationId),
            default => $this->fcm->send($token, $message['title'], $message['body'], array_map('strval', $message['data']), $notificationId),
        };

        DB::table('push_deliveries')->where('id', $deliveryId)->update([
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'provider_id' => $result->providerId,
            'error' => $result->error,
            'sent_at' => $result->ok() ? Js::dbTime() : null,
        ]);
        DB::table('customer_devices')->where('id', $device->id)->update(match ($result->status) {
            PushResult::SENT => ['last_push_at' => Js::dbTime(), 'failure_count' => 0, 'last_error' => null],
            PushResult::INVALID_TOKEN => ['token_invalid_at' => Js::dbTime(), 'last_error' => $result->error],
            default => ['failure_count' => DB::raw('failure_count + 1'), 'last_error' => $result->error],
        });

        return $result;
    }

    /** @param  array{title: string, body: string, data: array<string, string>, badge: ?int}  $message */
    private static function apnsPayload(array $message): array
    {
        return [
            'aps' => array_filter([
                'alert' => ['title' => $message['title'], 'body' => $message['body']],
                'sound' => 'default',
                'badge' => $message['badge'],
                'thread-id' => $message['data']['category'] ?? null,
            ], fn ($v) => $v !== null),
        ] + $message['data'];
    }
}
