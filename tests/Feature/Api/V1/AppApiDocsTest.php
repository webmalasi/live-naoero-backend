<?php

namespace Tests\Feature\Api\V1;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The App API reference (/api/v1/docs) describes exactly the routes under
 * /api/v1: nothing documented that does not exist, nothing left undocumented.
 */
class AppApiDocsTest extends TestCase
{
    public function test_the_reference_matches_the_routes(): void
    {
        $spec = json_decode(file_get_contents(resource_path('openapi/app-v1.json')), true);
        $documented = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $documented[] = strtoupper($method).' '.$path;
                }
            }
        }

        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/v1/')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $actual[] = $method.' /'.preg_replace('/\{(\w+)\?\}/', '{$1}', $uri);
                }
            }
        }
        sort($documented);
        sort($actual);

        $this->assertSame([], array_values(array_diff($documented, $actual)), 'documented but not a route');
        $this->assertSame([], array_values(array_diff($actual, $documented)), 'a route that is not documented');
    }
}
