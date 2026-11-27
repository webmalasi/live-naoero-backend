<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AirlineService;
use App\Models\PageBanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirlineServiceApiController extends Controller
{
    /**
     * Get complete Services page data including hero banner, service offerings, and quick links.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AirlineService::query()->active()->ordered();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $services = $query->get()->map(function ($service) {
            return [
                'id' => $service->id,
                'title' => $service->title,
                'slug' => $service->slug,
                'type' => $service->type,
                'icon' => $service->icon,
                'image' => $service->image_url,
                'description' => $service->description,
                'features' => $service->features ?? [],
                'points' => $service->features ?? [],
                'cta_text' => $service->cta_text,
                'cta_link' => $service->cta_link,
                'sort_order' => $service->sort_order,
            ];
        });

        // Banner data for /services page
        $banner = PageBanner::findByPage('services');

        // Quick Links for services page navigation
        $quickLinks = [
            [
                'title' => 'Before you fly',
                'description' => 'Read more about our Before you fly travel checklist here!',
                'image' => url('/assets/team/quickLink1.png'),
                'link' => '/travel-info/before-you-fly',
            ],
            [
                'title' => 'Schedule',
                'description' => 'View official Pacific island flight schedules and timetables!',
                'image' => url('/assets/team/quickLink2.png'),
                'link' => '/travel-info/flight-schedule',
            ],
            [
                'title' => 'Cargo',
                'description' => 'Our dedicated air freight team is here for you!',
                'image' => url('/assets/team/quickLink3.png'),
                'link' => '/freight',
            ],
        ];

        return response()->json([
            'success' => true,
            'count' => $services->count(),
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
                'button_text' => $banner->banner_button_text,
                'button_link' => $banner->banner_button_link,
            ] : null,
            'data' => $services,
            'quick_links' => $quickLinks,
        ]);
    }

    /**
     * Get single service detail by slug or ID.
     */
    public function show(string $identifier): JsonResponse
    {
        $service = is_numeric($identifier)
            ? AirlineService::find($identifier)
            : AirlineService::where('slug', $identifier)->first();

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => "Service '{$identifier}' not found.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $service->id,
                'title' => $service->title,
                'slug' => $service->slug,
                'type' => $service->type,
                'icon' => $service->icon,
                'image' => $service->image_url,
                'description' => $service->description,
                'features' => $service->features ?? [],
                'points' => $service->features ?? [],
                'cta_text' => $service->cta_text,
                'cta_link' => $service->cta_link,
                'sort_order' => $service->sort_order,
                'is_active' => $service->is_active,
                'created_at' => $service->created_at,
                'updated_at' => $service->updated_at,
            ],
        ]);
    }
}
