<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\LogApiRequest;
use App\Services\Logging\ServerLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Mobile app API (customers, flights, bookings, payments).
            Route::middleware(['api', LogApiRequest::class])
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // App API errors are expected outcomes with their own JSON shape.
        $exceptions->dontReport(ApiException::class);
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1', 'api/v1/*') || $e instanceof ApiException) {
                return null;
            }
            $error = fn (int $status, string $code, string $message, array $extra = []) => response()->json(
                ['error' => ['code' => $code, 'message' => $message, ...$extra]],
                $status,
                ['Cache-Control' => 'no-store'],
            );

            if ($e instanceof NotFoundHttpException) {
                return $error(404, 'not_found', 'Not found.');
            }
            if ($e instanceof MethodNotAllowedHttpException) {
                return $error(405, 'method_not_allowed', 'This method is not allowed here.');
            }
            if ($e instanceof ValidationException) {
                return $error(400, 'invalid_request', $e->validator->errors()->first());
            }
            if ($e instanceof HttpExceptionInterface) {
                return $error($e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Request failed.');
            }

            $log = app(ServerLog::class);
            $log->log('error', 'http', "{$request->method()} /{$request->path()} crashed", status: 500, detail: [
                'error' => $e::class.': '.$e->getMessage(),
                'stack' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8)),
            ]);

            return $error(500, 'internal_error', 'Something went wrong. Please try again.', ['requestId' => $log->requestId]);
        });
    })->create();
