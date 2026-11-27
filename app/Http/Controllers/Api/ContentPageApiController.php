<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Services\Content\ContentPages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Pages edited in the admin panel (Charter, Freight, Baggage …) for the
 * website and the app.
 *
 * Every reply carries the content's version as its ETag and must be checked
 * again before it is reused (no-cache): a client that sends If-None-Match
 * gets an empty 304 while nothing changed, so asking often is cheap. That is
 * how an admin edit shows on the website and in the app within seconds.
 */
class ContentPageApiController extends Controller
{
    public function __construct(private readonly ContentPages $pages) {}

    /** GET /api/pages?group=travel-info */
    public function index(Request $request): JsonResponse|Response
    {
        $group = $request->query('group');
        $group = is_string($group) && array_key_exists($group, ContentPage::GROUPS) ? $group : null;
        $list = $this->pages->listing($group);

        return $this->reply($request, ['success' => true, 'count' => count($list), 'data' => $list], sha1(json_encode($list)));
    }

    /** GET /api/pages/{slug}?channel=website|app */
    public function show(Request $request, string $slug): JsonResponse|Response
    {
        $channel = in_array($request->query('channel'), ['website', 'app'], true) ? $request->query('channel') : null;
        $page = ContentPage::query()->published()->where('slug', $slug)->first();
        if (! $page) {
            return response()->json(['success' => false, 'message' => "Page '{$slug}' not found."], 404, ['Cache-Control' => 'no-cache']);
        }
        $data = $this->pages->payload($page, $channel);

        return $this->reply($request, ['success' => true, 'data' => $data], $data['version']);
    }

    /**
     * GET /api/content/versions: one small reply with the version of every
     * page and every other admin-managed list. Ask it every 30 seconds or
     * so and fetch again only what changed.
     */
    public function versions(Request $request): JsonResponse|Response
    {
        $versions = $this->pages->versions();

        return $this->reply($request, ['success' => true, 'data' => $versions], $versions['version']);
    }

    private function reply(Request $request, array $body, string $version): JsonResponse|Response
    {
        $etag = '"'.substr($version, 0, 32).'"';
        $headers = [
            'ETag' => $etag,
            'X-Content-Version' => trim($etag, '"'),
            'Cache-Control' => 'public, no-cache, max-age=0, must-revalidate',
            'Vary' => 'Accept-Encoding',
        ];
        $sent = array_map(fn ($t) => trim(str_replace('W/', '', $t)), explode(',', (string) $request->header('If-None-Match')));
        if (in_array($etag, $sent, true)) {
            return response('', 304, $headers);
        }

        return response()->json($body, 200, $headers, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
