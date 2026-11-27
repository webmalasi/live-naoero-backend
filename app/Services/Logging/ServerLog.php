<?php

namespace App\Services\Logging;

use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Structured logging for everything the app API does. Each entry is written
 * as one JSON line to the "naoero" log channel with an ISO timestamp, and to
 * the server_logs table at the end of the request (one insert), so it can be
 * searched by request, customer, level or time.
 *
 * Passwords, tokens and the Zenith key never reach a log: fields with
 * sensitive names are replaced with "[redacted]".
 */
class ServerLog
{
    private const SENSITIVE = '/pass(word)?|secret|token|authorization|auth_?key|authenticationkey|cookie|hash|image/i';

    private const MAX_QUEUE = 5000;

    public ?string $requestId = null;

    public ?string $method = null;

    public ?string $path = null;

    public ?string $ip = null;

    public ?string $customerId = null;

    /** @var list<array<string, mixed>> */
    private array $queue = [];

    private int $dropped = 0;

    public function startRequest(string $method, string $path, ?string $ip): string
    {
        $this->requestId = (string) Str::uuid();
        $this->method = $method;
        $this->path = $path;
        $this->ip = $ip;
        $this->customerId = null;

        return $this->requestId;
    }

    /** Tags later entries in this request with the signed-in customer. */
    public function setCustomer(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @param  array<string, mixed>|null  $detail
     */
    public function log(string $level, string $source, string $message, ?int $status = null, ?float $durationMs = null, ?array $detail = null): void
    {
        $entry = [
            'logged_at' => Js::iso(now()),
            'level' => $level,
            'source' => $source,
            'message' => mb_substr($message, 0, 1000),
            'request_id' => $this->requestId,
            'method' => $this->method,
            'path' => $this->path,
            'status' => $status,
            'duration_ms' => $durationMs === null ? null : (int) round($durationMs),
            'ip' => $this->ip,
            'customer_id' => $this->customerId,
            'detail' => $detail === null ? null : self::redact(array_filter($detail, fn ($v) => $v !== null)),
        ];

        try {
            Log::channel('naoero')->log($level === 'warn' ? 'warning' : $level, Js::encode($entry));
        } catch (Throwable) {
            // The log file must never break a request.
        }

        if (! config('naoero.server_logs')) {
            return;
        }
        if (count($this->queue) >= self::MAX_QUEUE) {
            $this->dropped++;

            return;
        }
        $this->queue[] = $entry;
    }

    public function info(string $source, string $message, ?array $detail = null): void
    {
        $this->log('info', $source, $message, detail: $detail);
    }

    public function warn(string $source, string $message, ?array $detail = null): void
    {
        $this->log('warn', $source, $message, detail: $detail);
    }

    public function error(string $source, string $message, ?array $detail = null): void
    {
        $this->log('error', $source, $message, detail: $detail);
    }

    /** Writes the queued entries to server_logs in one insert. Never throws. */
    public function flush(): void
    {
        if ($this->queue === []) {
            return;
        }
        $batch = $this->queue;
        $this->queue = [];
        if ($this->dropped > 0) {
            $batch[] = [...$batch[0], 'level' => 'warn', 'source' => 'server', 'message' => "log queue full, dropped {$this->dropped} entries", 'detail' => null];
            $this->dropped = 0;
        }

        try {
            foreach (array_chunk($batch, 200) as $chunk) {
                DB::table('server_logs')->insert(array_map(fn (array $e) => [
                    ...$e,
                    'logged_at' => Js::dbTime(now()->parse($e['logged_at'])),
                    'detail' => $e['detail'] === null ? null : Js::encode($e['detail']),
                ], $chunk));
            }
        } catch (Throwable $e) {
            // Never log through log() here: that would loop.
            try {
                Log::channel('naoero')->error(Js::encode([
                    'logged_at' => Js::iso(now()), 'level' => 'error', 'source' => 'server',
                    'message' => 'could not store '.count($batch).' log entries: '.$e->getMessage(),
                ]));
            } catch (Throwable) {
            }
        }
    }

    public static function redact(mixed $value, int $depth = 0): mixed
    {
        if ($value === null) {
            return null;
        }
        if ($depth > 6) {
            return '[deep]';
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map(fn ($v) => self::redact($v, $depth + 1), array_slice($value, 0, 50));
            }
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = preg_match(self::SENSITIVE, (string) $k) ? '[redacted]' : self::redact($v, $depth + 1);
            }

            return $out;
        }
        if ($value instanceof \ArrayObject || $value instanceof \stdClass) {
            return self::redact(Js::plain($value), $depth);
        }
        if (is_string($value) && mb_strlen($value) > 2000) {
            return mb_substr($value, 0, 2000).'…';
        }

        return $value;
    }
}
