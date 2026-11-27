<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The website API reference (/api/docs) describes exactly the routes the
 * website API has: nothing documented that does not exist, nothing public
 * left undocumented.
 */
class SiteApiDocsTest extends TestCase
{
    public function test_the_reference_is_served(): void
    {
        $this->get('/api/docs')->assertOk()->assertSee('Swagger')->assertSee('/api/openapi.json', false);
        $spec = $this->getJson('/api/openapi.json')->assertOk()->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertSame('https://api.napacificbridge.info/api', $spec['servers'][0]['url']);
    }

    public function test_the_reference_matches_the_routes(): void
    {
        $spec = json_decode(file_get_contents(resource_path('openapi/site-v1.json')), true);
        $documented = [];
        foreach ($spec['paths'] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }

        $actual = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || str_starts_with($uri, 'api/v1')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $actual[] = $method.' /'.substr(preg_replace('/\{(\w+)\?\}/', '{$1}', $uri), 4);
                }
            }
        }
        sort($documented);
        sort($actual);

        $this->assertSame([], array_values(array_diff($documented, $actual)), 'documented but not a route');
        $this->assertSame([], array_values(array_diff($actual, $documented)), 'a route that is not documented');
    }

    public function test_every_reference_in_the_document_resolves(): void
    {
        $text = file_get_contents(resource_path('openapi/site-v1.json'));
        $spec = json_decode($text, true);
        preg_match_all('/"\$ref": "#\/components\/schemas\/(\w+)"/', $text, $m);
        foreach (array_unique($m[1]) as $name) {
            $this->assertArrayHasKey($name, $spec['components']['schemas'], "schema {$name}");
        }
    }
}
