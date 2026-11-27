<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who tried what, when (auth_events). Drives the login, reset, booking and
 * payment attempt limits, and is the trail to look at if an account is ever
 * disputed.
 */
class AuthEvents
{
    public function __construct(private readonly ServerLog $log) {}

    public function record(string $type, bool $success, RequestMeta $meta, ?string $customerId = null, ?string $login = null, ?string $detail = null): void
    {
        if ($customerId) {
            $this->log->setCustomer($customerId);
        }
        $this->log->log($success ? 'info' : 'warn', 'auth', "{$type} ".($success ? 'succeeded' : 'failed'), detail: [
            'login' => $login, 'detail' => $detail, 'device' => $meta->deviceLabel,
        ]);
        try {
            // Its own savepoint: a failed insert must not abort the caller's transaction.
            DB::transaction(fn () => DB::table('auth_events')->insert([
                'customer_id' => $customerId,
                'login' => $login,
                'event' => $type,
                'success' => $success,
                'ip' => $meta->ip,
                'user_agent' => $meta->userAgent,
                'detail' => $detail,
                'created_at' => Js::dbTime(),
            ]));
        } catch (Throwable $e) {
            // Auditing must never break sign-in.
            $this->log->error('auth', "could not record event {$type}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Refuses an attempt when recent events for this login or this device's
     * IP reach the limits. Runs before Zenith is called, so nobody can use
     * the app to trip Zenith's own account lockout for a real customer.
     */
    public function enforceLimit(string $type, ?string $login, ?string $ip, int $perLogin, int $perIp, int $windowMinutes, bool $countSuccesses = false): void
    {
        $query = DB::table('auth_events')
            ->where('event', $type)
            ->where('created_at', '>', Js::dbTime(now()->subMinutes($windowMinutes)))
            ->where(function ($q) use ($login, $ip) {
                $q->whereRaw('lower(login) = lower(?)', [$login])->orWhere('ip', $ip);
            });
        if (! $countSuccesses) {
            $query->where('success', false);
        }
        $row = $query->selectRaw(
            'sum(case when lower(login) = lower(?) then 1 else 0 end) as by_login, sum(case when ip = ? then 1 else 0 end) as by_ip',
            [$login, $ip],
        )->first();

        if (($login && (int) ($row->by_login ?? 0) >= $perLogin) || ($ip && (int) ($row->by_ip ?? 0) >= $perIp)) {
            throw ApiException::tooManyAttempts($windowMinutes * 60);
        }
    }
}
