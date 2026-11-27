<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhanceJourneyItem;
use App\Models\OurJourneyMilestone;
use App\Models\PageBanner;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JourneyApiController extends Controller
{
    /**
     * Get Our Journey timeline milestones (About Us Page).
     */
    public function ourJourney(Request $request): JsonResponse
    {
        $query = OurJourneyMilestone::query()->active()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('year', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $milestones = $query->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'year' => $item->year,
                'title' => $item->title,
                'description' => $item->description,
                'image' => $item->image_url,
                'sort_order' => $item->sort_order,
            ];
        });

        $heading = SiteSetting::where('key', 'our_journey_heading')->value('value') ?? 'Our Journey';
        $subheading = SiteSetting::where('key', 'our_journey_subheading')->value('value') ?? 'Connecting the Pacific for over 50 years with proud aviation heritage.';
        $banner = PageBanner::findByPage('about');

        return response()->json([
            'success' => true,
            'heading' => $heading,
            'subheading' => $subheading,
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
                'button_text' => $banner->banner_button_text,
                'button_link' => $banner->banner_button_link,
            ] : null,
            'count' => $milestones->count(),
            'data' => $milestones,
        ]);
    }

    /**
     * Get Enhance Your Journey booking essentials (Homepage Section).
     */
    public function enhanceJourney(Request $request): JsonResponse
    {
        $query = EnhanceJourneyItem::query()->active()->ordered();

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
                'description' => $item->description,
                'icon' => $item->icon,
                'image' => $item->image_url,
                'link_url' => $item->link_url,
                'link_text' => $item->link_text ?? 'Know More',
                'sort_order' => $item->sort_order,
            ];
        });

        $heading = SiteSetting::where('key', 'enhance_journey_heading')->value('value') ?? 'Enhance Your Journey';
        $subheading = SiteSetting::where('key', 'enhance_journey_subheading')->value('value') ?? 'From stays to adventures, we\'ve got everything you need to make your trip unforgettable';

        return response()->json([
            'success' => true,
            'heading' => $heading,
            'subheading' => $subheading,
            'count' => $items->count(),
            'data' => $items,
        ]);
    }

    /**
     * Composite endpoint returning both Our Journey and Enhance Journey.
     */
    public function all(Request $request): JsonResponse
    {
        $milestones = OurJourneyMilestone::query()->active()->ordered()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'year' => $item->year,
                'title' => $item->title,
                'description' => $item->description,
                'image' => $item->image_url,
                'sort_order' => $item->sort_order,
            ];
        });

        $enhanceItems = EnhanceJourneyItem::query()->active()->ordered()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'icon' => $item->icon,
                'image' => $item->image_url,
                'link_url' => $item->link_url,
                'link_text' => $item->link_text ?? 'Know More',
                'sort_order' => $item->sort_order,
            ];
        });

        return response()->json([
            'success' => true,
            'our_journey' => [
                'heading' => SiteSetting::where('key', 'our_journey_heading')->value('value') ?? 'Our Journey',
                'count' => $milestones->count(),
                'milestones' => $milestones,
            ],
            'enhance_journey' => [
                'heading' => SiteSetting::where('key', 'enhance_journey_heading')->value('value') ?? 'Enhance Your Journey',
                'subheading' => SiteSetting::where('key', 'enhance_journey_subheading')->value('value') ?? 'From stays to adventures, we\'ve got everything you need to make your trip unforgettable',
                'count' => $enhanceItems->count(),
                'items' => $enhanceItems,
            ],
        ]);
    }
}
