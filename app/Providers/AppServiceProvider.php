<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Auth\AdminAccess;
use App\Services\Logging\ServerLog;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One log context per request (request id, customer) for the app API.
        $this->app->scoped(ServerLog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Super Admin role has every permission, including ones added later.
        Gate::before(fn ($user) => $user instanceof User && $user->hasRole(AdminAccess::SUPER_ADMIN) ? true : null);

        // @canroute('admin.x.index') ... @endcanroute: shown only to staff who may open that admin route.
        Blade::if('canroute', fn (string $route, string $method = 'GET') => AdminAccess::allows(auth()->user(), $route, $method));
    }
}
