<?php

namespace App\Services\Zenith;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Caches what a loader returns for a while. A failed load is not cached, so
 * the next caller retries, and when a refresh fails but an older value
 * exists, the older value is served rather than failing the request.
 */
final class CachedValue
{
    /** Older values are kept this long as a fallback. */
    private const KEEP_SECONDS = 7 * 86400;

    public static function get(string $key, int $ttlSeconds, Closure $load): mixed
    {
        $store = Cache::store(config('naoero.cache_store'));
        $cacheKey = "naoero:{$key}";
        $entry = $store->get($cacheKey);
        if (is_array($entry) && ($entry['at'] ?? 0) > time() - $ttlSeconds) {
            return $entry['data'];
        }

        try {
            $data = $load();
        } catch (Throwable $e) {
            if (is_array($entry) && array_key_exists('data', $entry)) {
                return $entry['data'];
            }
            throw $e;
        }
        $store->put($cacheKey, ['at' => time(), 'data' => $data], self::KEEP_SECONDS);

        return $data;
    }

    public static function forget(string $key): void
    {
        Cache::store(config('naoero.cache_store'))->forget("naoero:{$key}");
    }
}
