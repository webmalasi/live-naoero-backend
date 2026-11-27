<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsApiController extends Controller
{
    /**
     * Get list of published news and media releases.
     * Accessible at: GET /api/news
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->published()->news()->latestFirst();

        if ($category = $request->query('category')) {
            if ($category !== 'All') {
                $query->where('category', $category);
            }
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 12);
        $news = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $news->items(),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
            ],
        ]);
    }

    /**
     * Get available news categories with article count.
     * Accessible at: GET /api/news/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Post::query()
            ->published()
            ->news()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get single news item by slug or ID.
     * Accessible at: GET /api/news/{slug}
     */
    public function show(string $identifier): JsonResponse
    {
        $news = Post::query()
            ->published()
            ->news()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (! $news) {
            return response()->json([
                'success' => false,
                'message' => 'News article not found.',
            ], 404);
        }

        // Increment views count
        $news->increment('views_count');

        $recentNews = Post::query()
            ->published()
            ->news()
            ->where('id', '!=', $news->id)
            ->latestFirst()
            ->limit(4)
            ->get(['id', 'title', 'slug', 'image', 'category', 'author', 'excerpt', 'published_at']);

        return response()->json([
            'success' => true,
            'data' => $news,
            'recentNews' => $recentNews,
            'recentPosts' => $recentNews, // alias for frontend convenience
        ]);
    }
}
