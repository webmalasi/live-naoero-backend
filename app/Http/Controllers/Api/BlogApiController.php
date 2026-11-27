<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogApiController extends Controller
{
    /**
     * Get list of published blog articles.
     * Accessible at: GET /api/blogs
     */
    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->published()->blog()->latestFirst();

        if ($category = $request->query('category')) {
            $query->where('category', $category);
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
        $blogs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $blogs->items(),
            'meta' => [
                'current_page' => $blogs->currentPage(),
                'last_page' => $blogs->lastPage(),
                'per_page' => $blogs->perPage(),
                'total' => $blogs->total(),
            ],
        ]);
    }

    /**
     * Get available blog categories with article count.
     * Accessible at: GET /api/blogs/categories
     */
    public function categories(): JsonResponse
    {
        $categories = Post::query()
            ->published()
            ->blog()
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
     * Get single blog post by slug or ID.
     * Accessible at: GET /api/blogs/{slug}
     */
    public function show(string $identifier): JsonResponse
    {
        $blog = Post::query()
            ->published()
            ->blog()
            ->where(function ($q) use ($identifier) {
                if (is_numeric($identifier)) {
                    $q->where('id', (int) $identifier)->orWhere('slug', $identifier);
                } else {
                    $q->where('slug', $identifier);
                }
            })
            ->first();

        if (! $blog) {
            return response()->json([
                'success' => false,
                'message' => 'Blog article not found.',
            ], 404);
        }

        // Increment views count
        $blog->increment('views_count');

        $recentBlogs = Post::query()
            ->published()
            ->blog()
            ->where('id', '!=', $blog->id)
            ->latestFirst()
            ->limit(4)
            ->get(['id', 'title', 'slug', 'image', 'category', 'author', 'excerpt', 'published_at']);

        return response()->json([
            'success' => true,
            'data' => $blog,
            'recentBlogs' => $recentBlogs,
            'recentPosts' => $recentBlogs, // alias for frontend convenience
        ]);
    }
}
