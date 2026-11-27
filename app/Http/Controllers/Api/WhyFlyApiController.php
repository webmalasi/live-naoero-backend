<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\WhyFlyFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhyFlyApiController extends Controller
{
    /**
     * Get all active Why Fly With Us feature cards.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WhyFlyFeature::query()->active()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $features = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'icon' => $item->icon,
                'icon_image' => $item->icon_image_url,
                'link_url' => $item->link_url,
                'link_text' => $item->link_text,
                'sort_order' => $item->sort_order,
            ];
        });

        // Configurable section heading from site settings or default
        $sectionHeading = SiteSetting::where('key', 'why_fly_heading')->value('value') ?? 'Why Fly with us';
        $sectionSubheading = SiteSetting::where('key', 'why_fly_subheading')->value('value') ?? 'Connecting the Pacific with safety, hospitality, and pride for over 50 years.';

        return response()->json([
            'success' => true,
            'heading' => $sectionHeading,
            'subheading' => $sectionSubheading,
            'count' => $features->count(),
            'data' => $features,
        ]);
    }

    /**
     * Get single Why Fly With Us feature card.
     */
    public function show(int $id): JsonResponse
    {
        $feature = WhyFlyFeature::find($id);

        if (!$feature) {
            return response()->json([
                'success' => false,
                'message' => "Why Fly feature #{$id} not found.",
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $feature->id,
                'title' => $feature->title,
                'description' => $feature->description,
                'icon' => $feature->icon,
                'icon_image' => $feature->icon_image_url,
                'link_url' => $feature->link_url,
                'link_text' => $feature->link_text,
                'sort_order' => $feature->sort_order,
                'is_active' => $feature->is_active,
                'created_at' => $feature->created_at,
                'updated_at' => $feature->updated_at,
            ],
        ]);
    }
}
