<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Services\Notifications\CustomerNotices;
use App\Services\Push\CustomerDevices;
use App\Services\Push\PushCredentials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The app's installs for push notifications: the app registers its UID and
 * push token on every launch and sign-in (PUT), changes what it wants to
 * receive the same way, and unlinks on sign-out (DELETE). Everything is
 * kept in our database (customer_devices); nothing goes to Zenith.
 */
class DeviceController extends ApiController
{
    private const UID = '/^[A-Za-z0-9._:-]{16,64}$/';

    public function __construct(
        private readonly CustomerDevices $devices,
        private readonly PushCredentials $credentials,
    ) {}

    /** What the app needs to ask Apple or Google for a token. */
    public function config(): JsonResponse
    {
        return $this->json($this->credentials->clientConfig() + ['categories' => CustomerNotices::CATEGORIES]);
    }

    public function index(Request $request): JsonResponse
    {
        $current = $request->header('X-Device-Uid');
        $rows = DB::table('customer_devices')->where('customer_id', $this->customer($request)->id)
            ->orderByDesc('last_registered_at')->get();

        return $this->json(['devices' => $rows->map(fn ($d) => CustomerDevices::present($d, $current))->values()->all()]);
    }

    public function update(Request $request, string $uid): JsonResponse
    {
        $this->checkUid($uid);
        $input = $request->validate([
            'platform' => ['required', Rule::in(array_keys(CustomerDevices::PLATFORMS))],
            'pushToken' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'apnsEnvironment' => ['nullable', Rule::in(['sandbox', 'production'])],
            'appVersion' => ['nullable', 'string', 'max:40'],
            'osVersion' => ['nullable', 'string', 'max:80'],
            'deviceModel' => ['nullable', 'string', 'max:80'],
            'locale' => ['nullable', 'string', 'max:20'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'notificationsEnabled' => ['sometimes', 'boolean'],
            'pushEnabled' => ['sometimes', 'boolean'],
            'mutedCategories' => ['sometimes', 'nullable', 'array'],
            'mutedCategories.*' => ['string', Rule::in(array_keys(CustomerNotices::CATEGORIES))],
        ]);
        if (($input['platform'] === 'ios') && isset($input['pushToken']) && ! preg_match('/^[0-9a-fA-F]{64,200}$/', $input['pushToken'])) {
            throw ApiException::invalid('pushToken must be the APNs device token in hex.');
        }

        $device = $this->devices->register($this->customer($request)->id, $this->sessionId($request), $uid, $input);

        return $this->json(['device' => CustomerDevices::present($device, $uid)]);
    }

    /** Signing out on this phone. Unknown installs are fine: nothing to do. */
    public function destroy(Request $request, string $uid): JsonResponse
    {
        $this->checkUid($uid);

        return $this->json(['ok' => true, 'unlinked' => $this->devices->unlink($this->customer($request)->id, $uid)]);
    }

    private function checkUid(string $uid): void
    {
        if (! preg_match(self::UID, $uid)) {
            throw ApiException::invalid('The device UID must be 16-64 letters, digits, dots, dashes, colons or underscores.');
        }
    }
}
