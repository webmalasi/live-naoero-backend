<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostApiController extends Controller
{
    /**
     * Get list of published news and blog articles (unified).
     * Accessible at: GET /api/posts
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->published()->latestFirst();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

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

        $perPage = $request->integer('per_page', 9);
        $posts = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $posts->items(),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Get available post categories with article count.
     * Accessible at: GET /api/posts/categories
     */
    public function categories(Request $request): JsonResponse
    {
        $query = Post::query()->published();

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $categories = $query->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get single post by slug or ID.
     * Accessible at: GET /api/posts/{slug}
     */
    public function show(string $identifier): JsonResponse
    {
        $post = Post::query()
            ->published()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (! $post) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found.',
            ], 404);
        }

        // Increment views count
        $post->increment('views_count');

        $recentPosts = Post::query()
            ->published()
            ->where('id', '!=', $post->id)
            ->where('type', $post->type)
            ->latestFirst()
            ->limit(4)
            ->get(['id', 'title', 'slug', 'image', 'category', 'author', 'excerpt', 'published_at']);

        return response()->json([
            'success' => true,
            'data' => $post,
            'recentPosts' => $recentPosts,
        ]);
    }
}
