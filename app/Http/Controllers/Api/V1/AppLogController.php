<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Api\V1\AppLogRequest;
use App\Services\Auth\AccessToken;
use App\Services\Auth\RequestMeta;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Receives the app's own logs (screens opened, actions, API calls, errors)
 * in batches and stores them in app_logs with the phone's timestamp and the
 * time they arrived. A valid Bearer token links them to the customer; it is
 * optional so logs from before login are kept too.
 */
class AppLogController extends ApiController
{
    public function __construct(private readonly ServerLog $log) {}

    public function store(AppLogRequest $request): JsonResponse
    {
        $ip = RequestMeta::clientKey($request);
        // A phone flushes every few seconds; this is generous but bounded.
        if (RateLimiter::tooManyAttempts("applogs:{$ip}", 120)) {
            throw new ApiException(429, 'too_many_requests', 'Too many log uploads.');
        }
        RateLimiter::hit("applogs:{$ip}", 60);

        $batch = $request->validated();
        $customerId = null;
        if (preg_match('/^Bearer\s+(.+)$/i', (string) $request->header('Authorization'), $m)) {
            try {
                $customerId = AccessToken::verify(trim($m[1]))['customerId'];
                $this->log->setCustomer($customerId);
            } catch (ApiException) {
                // An expired token still delivers the logs, just without the customer.
            }
        }

        $received = Js::dbTime();
        $rows = array_map(fn (array $e) => [
            'received_at' => $received,
            'client_time' => isset($e['ts']) ? Js::dbTime(CarbonImmutable::parse($e['ts'])) : null,
            'device_id' => $batch['deviceId'],
            'customer_id' => $customerId,
            'app_version' => $batch['appVersion'] ?? null,
            'platform' => $batch['platform'] ?? null,
            'level' => $e['level'],
            'event' => $e['event'],
            'message' => $e['message'] ?? null,
            'detail' => isset($e['detail']) ? Js::encode(Js::map(ServerLog::redact($e['detail']))) : null,
            'ip' => $ip === 'local' ? null : $ip,
        ], $batch['entries']);
        DB::table('app_logs')->insert($rows);

        $errors = count(array_filter($batch['entries'], fn ($e) => $e['level'] === 'error'));
        if ($errors) {
            $this->log->warn('app', "app reported {$errors} error(s)", ['deviceId' => $batch['deviceId']]);
        }

        return $this->json(['ok' => true, 'stored' => count($rows)]);
    }
}
