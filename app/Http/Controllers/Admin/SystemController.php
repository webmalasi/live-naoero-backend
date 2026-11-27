<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;

class SystemController extends Controller
{
    /**
     * Show system status, database details, and redis overview.
     */
    public function index(): View
    {
        // Database metrics
        $dbDriver = config('database.default');
        $dbHost = config("database.connections.{$dbDriver}.host", 'localhost');
        $dbDatabase = config("database.connections.{$dbDriver}.database", 'naoero_airlines');
        $dbConnected = false;
        $dbError = null;

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Throwable $e) {
            $dbError = $e->getMessage();
        }

        // Redis metrics
        $redisClient = config('database.redis.client', 'predis');
        $redisHost = config('database.redis.default.host', '127.0.0.1');
        $redisPort = config('database.redis.default.port', '6379');
        $redisConnected = false;
        $redisInfo = [];

        if (! app()->environment('testing')) {
            try {
                $redis = Redis::connection();
                $pong = $redis->ping();
                $redisConnected = ($pong === 'PONG' || $pong === true || $pong === '+PONG');
                if ($redisConnected && method_exists($redis, 'info')) {
                    $redisInfo = (array) $redis->info();
                }
            } catch (\Throwable $e) {
                $redisInfo['error'] = $e->getMessage();
            }
        } else {
            $redisConnected = true;
        }

        // Environment info
        $envInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug') ? 'Enabled' : 'Disabled',
            'app_timezone' => config('app.timezone'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
        ];

        return view('admin.system.index', compact(
            'dbDriver',
            'dbHost',
            'dbDatabase',
            'dbConnected',
            'dbError',
            'redisClient',
            'redisHost',
            'redisPort',
            'redisConnected',
            'redisInfo',
            'envInfo'
        ));
    }

    /**
     * Clear application & Redis cache.
     */
    public function flushCache(): RedirectResponse
    {
        Cache::flush();
        Artisan::call('cache:clear');

        ActivityLog::log('cache_flush', 'System', 'Cleared system & Redis application cache.');

        return back()->with('success', 'Application & Redis cache has been flushed successfully!');
    }

    /**
     * Optimize application cache.
     */
    public function optimize(): RedirectResponse
    {
        Artisan::call('optimize');

        ActivityLog::log('optimize', 'System', 'Optimized application routing and configuration caches.');

        return back()->with('success', 'System optimized successfully (routes and configuration cached).');
    }
}
