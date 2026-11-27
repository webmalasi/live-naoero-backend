<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PageBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageBannerApiController extends Controller
{
    /**
     * Get all active page banners.
     * Returns both ordered list and keyed map (by page_key) for frontend consumption.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PageBanner::query()->active()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('page_name', 'like', "%{$search}%")
                  ->orWhere('page_key', 'like', "%{$search}%")
                  ->orWhere('banner_heading', 'like', "%{$search}%")
                  ->orWhere('banner_description', 'like', "%{$search}%");
            });
        }

        $banners = $query->get()->map(function ($banner) {
            return [
                'id' => $banner->id,
                'page_key' => $banner->page_key,
                'page_name' => $banner->page_name,
                'page_route' => $banner->page_route,
                'badge_text' => $banner->banner_badge_text,
                'image' => $banner->banner_image_url,
                'banner_image' => $banner->banner_image_url,
                'heading' => $banner->banner_heading,
                'banner_heading' => $banner->banner_heading,
                'description' => $banner->banner_description,
                'banner_description' => $banner->banner_description,
                'button_text' => $banner->banner_button_text,
                'banner_button_text' => $banner->banner_button_text,
                'button_link' => $banner->banner_button_link,
                'banner_button_link' => $banner->banner_button_link,
                'sort_order' => $banner->sort_order,
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at,
            ];
        });

        // Keyed dictionary by page_key
        $keyed = [];
        foreach ($banners as $b) {
            $keyed[$b['page_key']] = $b;
        }

        return response()->json([
            'success' => true,
            'count' => $banners->count(),
            'data' => $banners,
            'banners_by_page' => $keyed,
        ]);
    }

    /**
     * Get single banner by page key or identifier.
     */
    public function show(string $identifier): JsonResponse
    {
        $banner = null;

        if (is_numeric($identifier)) {
            $banner = PageBanner::find($identifier);
        }

        if (!$banner) {
            $banner = PageBanner::findByPage($identifier);
        }

        if (!$banner) {
            return response()->json([
                'success' => false,
                'message' => "Banner for page '{$identifier}' not found.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $banner->id,
                'page_key' => $banner->page_key,
                'page_name' => $banner->page_name,
                'page_route' => $banner->page_route,
                'badge_text' => $banner->banner_badge_text,
                'image' => $banner->banner_image_url,
                'banner_image' => $banner->banner_image_url,
                'heading' => $banner->banner_heading,
                'banner_heading' => $banner->banner_heading,
                'description' => $banner->banner_description,
                'banner_description' => $banner->banner_description,
                'button_text' => $banner->banner_button_text,
                'banner_button_text' => $banner->banner_button_text,
                'button_link' => $banner->banner_button_link,
                'banner_button_link' => $banner->banner_button_link,
                'sort_order' => $banner->sort_order,
                'is_active' => $banner->is_active,
                'created_at' => $banner->created_at,
                'updated_at' => $banner->updated_at,
            ],
        ]);
    }
}
