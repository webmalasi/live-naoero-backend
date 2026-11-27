<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Booking\BookingSummary;
use App\Services\Support\Js;
use App\Services\Zenith\ZenithClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** API reference: the OpenAPI document, Swagger UI, and a liveness check. */
class DocsController extends ApiController
{
    private const SWAGGER_UI_VERSION = '5.17.14';

    /** The OpenAPI 3.1 description of the app API (import into Postman, Swagger, etc.). */
    public function openapi(): Response
    {
        return response(file_get_contents(resource_path('openapi/app-v1.json')), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    /** Swagger UI (pinned version from a CDN) reading the OpenAPI document. */
    public function docs(): Response
    {
        $version = self::SWAGGER_UI_VERSION;
        $spec = '/api/v1/openapi.json';
        $page = <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Naoero Airlines API</title>
          <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui.css">
          <style>
            body { margin: 0; background: #fafafa; }
            .topbar { display: none; }
            .brand { background: #012169; color: #fff; padding: 14px 24px; font: 600 16px/1.2 system-ui, sans-serif; border-bottom: 4px solid #ffc631; }
            .brand span { color: #ffc631; letter-spacing: 2px; margin-right: 10px; }
          </style>
        </head>
        <body>
          <div class="brand"><span>NAOERO AIRLINES</span>API reference</div>
          <div id="swagger-ui"></div>
          <script src="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui-bundle.js" crossorigin></script>
          <script>
            window.ui = SwaggerUIBundle({
              url: '{$spec}',
              dom_id: '#swagger-ui',
              deepLinking: true,
              persistAuthorization: true,
              displayRequestDuration: true,
              docExpansion: 'none',
              tryItOutEnabled: false,
              defaultModelsExpandDepth: 0,
            });
          </script>
        </body>
        </html>
        HTML;

        return response($page, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'public, max-age=300']);
    }

    /**
     * Liveness check; says whether the key is configured, never what it is,
     * and whether Zenith answers (Ping, checked at most once a minute).
     */
    public function health(ZenithClient $zenith): JsonResponse
    {
        $configured = trim((string) config('naoero.zenith.key')) !== '';
        $ping = ! $configured ? ['reachable' => false, 'ms' => null, 'serverTimeUtc' => null, 'checkedAt' => Js::iso(now())]
            : Cache::store(config('naoero.cache_store'))->remember('naoero:zenith:ping', 60, function () use ($zenith) {
                $started = hrtime(true);
                try {
                    $res = $zenith->call('Ping', ['StringTest' => 'naoero-health']);
                    $ok = true;
                } catch (Throwable) {
                    $res = [];
                    $ok = false;
                }

                return [
                    'reachable' => $ok,
                    'ms' => (int) round((hrtime(true) - $started) / 1e6),
                    'serverTimeUtc' => isset($res['UTC']) ? BookingSummary::utc((string) $res['UTC']) : null,
                    'checkedAt' => Js::iso(now()),
                ];
            });

        return $this->json(['ok' => true, 'zenithKeyConfigured' => $configured, 'zenith' => $ping]);
    }
}
