<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * API reference of the website content API (pages, banners, team, news,
 * forms …): the OpenAPI document and Swagger UI. The app's booking API has
 * its own at /api/v1/docs.
 */
class SiteDocsController extends Controller
{
    private const SWAGGER_UI_VERSION = '5.17.14';

    /** The OpenAPI 3.1 description (import into Postman, Swagger, etc.). */
    public function openapi(): Response
    {
        return response(file_get_contents(resource_path('openapi/site-v1.json')), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=300',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** Swagger UI (pinned version from a CDN) reading the OpenAPI document. */
    public function docs(): Response
    {
        $version = self::SWAGGER_UI_VERSION;
        $spec = '/api/openapi.json';
        $page = <<<HTML
        <!doctype html>
        <html lang="en">
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>Naoero Airlines website API</title>
          <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui.css">
          <style>
            body { margin: 0; background: #fafafa; }
            .topbar { display: none; }
            .brand { background: #012169; color: #fff; padding: 14px 24px; font: 600 16px/1.2 system-ui, sans-serif; border-bottom: 4px solid #ffc631; display: flex; flex-wrap: wrap; gap: 8px 24px; align-items: center; }
            .brand span { color: #ffc631; letter-spacing: 2px; margin-right: 10px; }
            .brand a { color: #fff; font-weight: 500; font-size: 14px; opacity: .85; }
          </style>
        </head>
        <body>
          <div class="brand"><div><span>NAOERO AIRLINES</span>Website content API</div><a href="/api/v1/docs">App booking API &rarr;</a></div>
          <div id="swagger-ui"></div>
          <script src="https://unpkg.com/swagger-ui-dist@{$version}/swagger-ui-bundle.js" crossorigin></script>
          <script>
            window.ui = SwaggerUIBundle({
              url: '{$spec}',
              dom_id: '#swagger-ui',
              deepLinking: true,
              persistAuthorization: true,
              displayRequestDuration: true,
              docExpansion: 'list',
              tryItOutEnabled: true,
              defaultModelsExpandDepth: 0,
            });
          </script>
        </body>
        </html>
        HTML;

        return response($page, 200, ['Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'public, max-age=300']);
    }
}
