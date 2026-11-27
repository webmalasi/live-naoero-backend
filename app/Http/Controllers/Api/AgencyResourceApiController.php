<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgencyResource;
use App\Models\PageBanner;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgencyResourceApiController extends Controller
{
    /**
     * Get all active travel agency policies and guidance resources.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AgencyResource::query()->active()->ordered();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'description' => $item->description,
                'category' => $item->category,
                'icon' => $item->icon ?? 'FileText',
                'file_url' => $item->download_url,
                'external_url' => $item->external_url,
                'sort_order' => $item->sort_order,
            ];
        });

        $heading = SiteSetting::where('key', 'agency_resources_heading')->value('value') ?? 'Agency Resources';
        $introText = SiteSetting::where('key', 'agency_resources_intro')->value('value') ?? 'Naoero Airlines partners with travel agents who provide outstanding service to our mutual customers. We use the Zenith TTI Passenger Sales System. For the best fares, travel agents can book tickets online using our Extranet Agency Login. Alternatively, Naoero Airlines flights can be booked within the Amadeus reservation system using ON flight numbers and ticketed through IATA BSP (123) in Australia. In Sabre and Travelport, Naoero Airlines flights can be booked on HR169 ticket stock in all Hahn Air sales markets including Australia and New Zealand. For travel agents booking and issuing tickets via GDS, the following policies have been published to ensure best practice is followed, unnecessary costs are avoided, and all possible inventory is available for sale to our customers. Please click on the policy listed, to read full details.';
        $banner = PageBanner::findByPage('agency-resources');

        return response()->json([
            'success' => true,
            'heading' => $heading,
            'intro' => $introText,
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
            ] : null,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    /**
     * Get single agency policy resource.
     */
    public function show(Request $request, $identifier): JsonResponse
    {
        $item = is_numeric($identifier)
            ? AgencyResource::find($identifier)
            : AgencyResource::where('slug', $identifier)->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Agency resource policy not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'description' => $item->description,
                'category' => $item->category,
                'icon' => $item->icon ?? 'FileText',
                'file_url' => $item->download_url,
                'external_url' => $item->external_url,
                'sort_order' => $item->sort_order,
            ],
        ]);
    }
}
