<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GalleryApiController extends Controller
{
    /**
     * Get active gallery photos.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GalleryItem::active()->ordered();

        if ($category = $request->input('category')) {
            if (strtolower($category) !== 'all') {
                $query->where('category', $category);
            }
        }

        if ($destination = $request->input('destination')) {
            $query->where('destination', 'like', "%{$destination}%");
        }

        $items = $query->get();

        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    /**
     * Get list of unique gallery categories.
     */
    public function categories(): JsonResponse
    {
        $categories = GalleryItem::active()
            ->distinct()
            ->whereNotNull('category')
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }
}
