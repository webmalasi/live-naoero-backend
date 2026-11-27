<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Logging\ServerLog;
use App\Services\Push\PushCredentials;
use App\Services\Push\PushNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Push Notifications (System & Settings): the Apple and Firebase keys
 * pushes are sent with (encrypted in push_credentials, never shown back),
 * every phone registered by the app, and every push sent with Apple's or
 * Google's answer. A test push checks a phone end to end.
 */
class PushNotificationController extends Controller
{
    public function __construct(private readonly PushCredentials $credentials) {}

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $search = trim((string) $request->input('search'));

        $devices = DB::table('customer_devices as d')
            ->leftJoin('customers as c', 'c.id', '=', 'd.customer_id')
            ->select('d.*', 'c.zenith_login', 'c.first_name', 'c.last_name')->selectRaw('coalesce(c.email, c.zenith_login) as email')
            ->selectSub(fn ($q) => $q->from('refresh_tokens as t')->whereColumn('t.family_id', 'd.session_family_id')
                ->whereNull('t.revoked_at')->where('t.expires_at', '>', now())->selectRaw('count(*)'), 'live_sessions')
            ->when(in_array($request->input('platform'), ['ios', 'android'], true), fn ($q) => $q->where('d.platform', $request->input('platform')))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereRaw('lower(c.zenith_login) like ?', ['%'.mb_strtolower($search).'%'])->orWhereRaw("lower(coalesce(c.email, '')) like ?", ['%'.mb_strtolower($search).'%'])
                ->orWhere('d.device_uid', $search)->orWhere('d.device_model', 'like', "%{$search}%")))
            ->orderByDesc('d.last_registered_at')
            ->paginate(25, ['*'], 'devices')->withQueryString();

        $deliveries = DB::table('push_deliveries as p')
            ->leftJoin('customer_devices as d', 'd.id', '=', 'p.device_id')
            ->leftJoin('customers as c', 'c.id', '=', 'p.customer_id')
            ->select('p.*', 'd.platform', 'd.device_model')->selectRaw('coalesce(c.email, c.zenith_login) as email')
            ->when(in_array($request->input('status'), ['sent', 'failed', 'invalid_token', 'sending'], true), fn ($q) => $q->where('p.status', $request->input('status')))
            ->orderByDesc('p.created_at')
            ->paginate(25, ['*'], 'deliveries')->withQueryString();

        $since = now()->subDay();
        $stats = [
            'devices' => DB::table('customer_devices')->whereNotNull('customer_id')->count(),
            'reachable' => DB::table('customer_devices')->whereNotNull('customer_id')->whereNotNull('push_token')->whereNull('token_invalid_at')
                ->where('notifications_enabled', true)->where('push_enabled', true)->count(),
            'ios' => DB::table('customer_devices')->whereNotNull('customer_id')->where('platform', 'ios')->count(),
            'android' => DB::table('customer_devices')->whereNotNull('customer_id')->where('platform', 'android')->count(),
            'sent_day' => DB::table('push_deliveries')->where('status', 'sent')->where('created_at', '>=', $since)->count(),
            'failed_day' => DB::table('push_deliveries')->whereIn('status', ['failed', 'invalid_token'])->where('created_at', '>=', $since)->count(),
        ];

        $rows = DB::table('push_credentials')->get()->keyBy('provider');
        $providers = [];
        foreach (PushCredentials::PROVIDERS as $key => $label) {
            $config = $this->credentials->config($key);
            $providers[$key] = [
                'label' => $label,
                'row' => $rows[$key] ?? null,
                'enabled' => (bool) ($rows[$key]->enabled ?? false),
                'complete' => PushCredentials::complete($key, $config),
                'ready' => $this->credentials->enabled($key),
                'config' => self::safe($key, $config),
            ];
        }

        return view('admin.push.index', [
            'providers' => $providers,
            'devices' => $devices,
            'deliveries' => $deliveries,
            'stats' => $stats,
            'search' => $search,
            'canEdit' => $request->user()?->can('settings.api'),
        ]);
    }

    public function saveApns(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $old = $this->credentials->config('apns');
        $input = $request->validate([
            'team_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'key_id' => ['required', 'string', 'regex:/^[A-Z0-9]{10}$/'],
            'bundle_id' => ['required', 'string', 'max:155', 'regex:/^[A-Za-z0-9.-]+$/'],
            'private_key' => [filled($old['private_key'] ?? null) ? 'nullable' : 'required_without:private_key_file', 'string', 'max:5000'],
            'private_key_file' => ['nullable', 'file', 'max:10'],
            'enabled' => ['nullable', 'boolean'],
        ], [
            'team_id.regex' => 'The Team ID is 10 letters and digits (Apple Developer > Membership).',
            'key_id.regex' => 'The Key ID is 10 letters and digits (shown with the .p8 key).',
        ]);
        $key = $request->hasFile('private_key_file') ? (string) file_get_contents($request->file('private_key_file')->getRealPath()) : trim((string) ($input['private_key'] ?? ''));
        $key = $key !== '' ? $key : ($old['private_key'] ?? '');
        $pkey = openssl_pkey_get_private($key);
        if ($pkey === false || (openssl_pkey_get_details($pkey)['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            return back()->withInput()->withErrors(['private_key' => 'This is not an Apple .p8 key. Paste the whole file, from -----BEGIN PRIVATE KEY----- to -----END PRIVATE KEY-----.']);
        }

        $this->credentials->save('apns', [
            'team_id' => $input['team_id'],
            'key_id' => $input['key_id'],
            'bundle_id' => $input['bundle_id'],
            'private_key' => $key,
        ], $request->boolean('enabled'), $request->user()?->id);
        ActivityLog::log('updated', 'push', 'Updated the Apple push (APNs) key'.($request->boolean('enabled') ? ', switched on' : ', switched off'));

        return back()->with('success', 'Apple push settings saved.');
    }

    public function saveFcm(Request $request): RedirectResponse
    {
        $this->authorizeEdit($request);
        $old = $this->credentials->config('fcm');
        $input = $request->validate([
            'service_account' => [filled($old['service_account'] ?? null) ? 'nullable' : 'required_without:service_account_file', 'string', 'max:20000'],
            'service_account_file' => ['nullable', 'file', 'max:20'],
            'app_id' => ['required', 'string', 'max:120', 'regex:/^1:\d+:android:[0-9a-f]+$/'],
            'api_key' => ['required', 'string', 'max:120'],
            'sender_id' => ['nullable', 'string', 'max:40', 'regex:/^\d*$/'],
            'enabled' => ['nullable', 'boolean'],
        ], [
            'app_id.regex' => 'The Android app ID looks like 1:1234567890:android:abc123 (Firebase > Project settings > Your apps).',
        ]);
        $json = $request->hasFile('service_account_file') ? (string) file_get_contents($request->file('service_account_file')->getRealPath()) : trim((string) ($input['service_account'] ?? ''));
        $account = $json !== '' ? json_decode($json, true) : ($old['service_account'] ?? null);
        if (! is_array($account) || ($account['type'] ?? null) !== 'service_account' || empty($account['project_id']) || empty($account['client_email'])
            || openssl_pkey_get_private((string) ($account['private_key'] ?? '')) === false) {
            return back()->withInput()->withErrors(['service_account' => 'This is not a Firebase service account key. In Firebase > Project settings > Service accounts, choose "Generate new private key" and paste or upload the whole JSON file.']);
        }
        $senderId = $input['sender_id'] ?: (explode(':', $input['app_id'])[1] ?? '');

        $this->credentials->save('fcm', [
            'service_account' => array_intersect_key($account, array_flip(['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri'])),
            'android' => ['app_id' => $input['app_id'], 'api_key' => $input['api_key'], 'sender_id' => $senderId],
        ], $request->boolean('enabled'), $request->user()?->id);
        ActivityLog::log('updated', 'push', 'Updated the Firebase (FCM) key for Android pushes'.($request->boolean('enabled') ? ', switched on' : ', switched off'));

        return back()->with('success', 'Firebase settings saved.');
    }

    public function test(Request $request, string $device, PushNotifier $notifier): RedirectResponse
    {
        $this->authorizeEdit($request);
        $result = $notifier->test($device);
        app(ServerLog::class)->flush();
        ActivityLog::log('created', 'push', "Sent a test push to device {$device}: {$result->status}");

        return back()->with($result->ok() ? 'success' : 'error', $result->ok()
            ? 'Test push sent. It should appear on the phone within a few seconds.'
            : 'The test push was not delivered: '.($result->error ?? $result->status));
    }

    /**
     * What the page may show of the saved settings: never the keys.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private static function safe(string $provider, array $config): array
    {
        if ($provider === 'apns') {
            return [
                'team_id' => $config['team_id'] ?? '',
                'key_id' => $config['key_id'] ?? '',
                'bundle_id' => $config['bundle_id'] ?? '',
                'has_key' => filled($config['private_key'] ?? null),
            ];
        }

        return [
            'project_id' => $config['service_account']['project_id'] ?? '',
            'client_email' => $config['service_account']['client_email'] ?? '',
            'has_key' => filled($config['service_account']['private_key'] ?? null),
            'app_id' => $config['android']['app_id'] ?? '',
            'api_key' => $config['android']['api_key'] ?? '',
            'sender_id' => $config['android']['sender_id'] ?? '',
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('system.push') || $request->user()?->can('settings.api'), 403, 'You do not have access to push notifications.');
    }

    private function authorizeEdit(Request $request): void
    {
        abort_unless($request->user()?->can('settings.api'), 403, 'You do not have access to change push settings.');
    }
}
