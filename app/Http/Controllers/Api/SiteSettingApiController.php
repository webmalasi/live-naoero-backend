<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EnhanceJourneyItem;
use App\Models\Notice;
use App\Models\OurJourneyMilestone;
use App\Models\PageBanner;
use App\Models\SiteSetting;
use App\Models\WhyFlyFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingApiController extends Controller
{
    /**
     * Get active notice bar alerts / announcements for frontend ticker.
     */
    public function notice(): JsonResponse
    {
        $masterSetting = SiteSetting::where('key', 'notice_bar_enabled')->first();
        $masterEnabled = $masterSetting ? ($masterSetting->value === '1' || $masterSetting->value === 'true' || $masterSetting->value === true) : true;

        if (! $masterEnabled) {
            return response()->json([
                'success' => true,
                'master_enabled' => false,
                'data' => null,
                'items' => [],
                'count' => 0,
            ]);
        }

        $activeNotices = Notice::query()
            ->active()
            ->ordered()
            ->get();

        $primaryNotice = $activeNotices->first();

        return response()->json([
            'success' => true,
            'master_enabled' => true,
            'data' => $primaryNotice,
            'items' => $activeNotices,
            'count' => $activeNotices->count(),
        ]);
    }

    /**
     * Get unified global public site settings (branding logo, top header text, navigation menu, contact info, social links, notices).
     */
    public function settings(): JsonResponse
    {
        $rawSettings = SiteSetting::all()->pluck('value', 'key')->toArray();

        // Default / configured top header navigation menu
        $defaultMenu = [
            ['label' => 'My Booking', 'route' => 'https://book.naoeroairlines.com/manage', 'icon' => 'Ticket', 'is_external' => true],
            ['label' => 'Flight Status', 'route' => '/travel-info/flight-schedule', 'icon' => 'PlaneTakeoff', 'is_external' => false],
            ['label' => 'Contact Us', 'route' => '/contact-us', 'icon' => 'Phone', 'is_external' => false],
        ];

        $topHeaderMenu = $defaultMenu;

        if (! empty($rawSettings['top_header_menu'])) {
            $decoded = is_array($rawSettings['top_header_menu'])
                ? $rawSettings['top_header_menu']
                : json_decode($rawSettings['top_header_menu'], true);
            if (is_array($decoded) && count($decoded) > 0) {
                $topHeaderMenu = $decoded;
            }
        }

        // Resolve absolute logo URL
        $logoPath = $rawSettings['site_logo'] ?? '/assets/logo.png';
        if (! str_starts_with($logoPath, 'http://') && ! str_starts_with($logoPath, 'https://')) {
            $logoUrl = url($logoPath);
        } else {
            $logoUrl = $logoPath;
        }

        $topHeaderText = $rawSettings['top_header_text'] ?? 'Welcome to Naoero Airline';

        // Master Notice Bar Status & Latest Active Notice
        $masterNoticeEnabled = isset($rawSettings['notice_bar_enabled'])
            ? ($rawSettings['notice_bar_enabled'] === '1' || $rawSettings['notice_bar_enabled'] === 'true' || $rawSettings['notice_bar_enabled'] === true)
            : true;

        $activeNotice = null;
        if ($masterNoticeEnabled) {
            $activeNotice = Notice::query()->active()->ordered()->first();
        }

        // Fetch all active page banners
        $pageBanners = PageBanner::active()->ordered()->get()->keyBy('page_key')->map(function ($b) {
            return [
                'id' => $b->id,
                'page_key' => $b->page_key,
                'page_name' => $b->page_name,
                'page_route' => $b->page_route,
                'badge_text' => $b->banner_badge_text,
                'banner_image' => $b->banner_image_url,
                'image' => $b->banner_image_url,
                'banner_heading' => $b->banner_heading,
                'heading' => $b->banner_heading,
                'banner_description' => $b->banner_description,
                'description' => $b->banner_description,
                'banner_button_text' => $b->banner_button_text,
                'button_text' => $b->banner_button_text,
                'banner_button_link' => $b->banner_button_link,
                'button_link' => $b->banner_button_link,
            ];
        });

        $formattedSettings = array_merge($rawSettings, [
            // Core Global Brand & Header Elements
            'logo' => $logoUrl,
            'site_logo' => $logoUrl,
            'logo_url' => $logoUrl,
            'top_header_text' => $topHeaderText,
            'welcome_text' => $topHeaderText,
            'menu' => $topHeaderMenu,
            'top_header_menu' => $topHeaderMenu,
            'header_menu' => $topHeaderMenu,
            'notice_bar_enabled' => $masterNoticeEnabled,
            'active_notice' => $activeNotice,
            'page_banners' => $pageBanners,
            'banners' => $pageBanners,

            // Why Fly With Us Selling Points & Headings
            'why_fly' => [
                'heading' => $rawSettings['why_fly_heading'] ?? 'Why Fly with us',
                'subheading' => $rawSettings['why_fly_subheading'] ?? 'Connecting the Pacific with safety, hospitality, and pride for over 50 years.',
                'features' => WhyFlyFeature::query()->active()->ordered()->get()->map(function ($item) {
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
                }),
            ],
            'why_fly_with_us' => WhyFlyFeature::query()->active()->ordered()->get()->map(function ($item) {
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'icon' => $item->icon,
                    'icon_image' => $item->icon_image_url,
                    'sort_order' => $item->sort_order,
                ];
            }),

            // Enhance Your Journey Booking Essentials (Homepage)
            'enhance_journey' => [
                'heading' => $rawSettings['enhance_journey_heading'] ?? 'Enhance Your Journey',
                'subheading' => $rawSettings['enhance_journey_subheading'] ?? 'From stays to adventures, we\'ve got everything you need to make your trip unforgettable',
                'items' => EnhanceJourneyItem::query()->active()->ordered()->get()->map(function ($item) {
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
                }),
            ],

            // Our Journey Timeline (About Us Page)
            'our_journey' => [
                'heading' => $rawSettings['our_journey_heading'] ?? 'Our Journey',
                'subheading' => $rawSettings['our_journey_subheading'] ?? 'Connecting the Pacific for over 50 years with proud aviation heritage.',
                'milestones' => OurJourneyMilestone::query()->active()->ordered()->get()->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'year' => $item->year,
                        'title' => $item->title,
                        'description' => $item->description,
                        'image' => $item->image_url,
                        'sort_order' => $item->sort_order,
                    ];
                }),
            ],

            // Clean Structured Sections for Easy Frontend Integration
            'header' => [
                'logo' => $logoUrl,
                'welcome_text' => $topHeaderText,
                'top_header_text' => $topHeaderText,
                'menu' => $topHeaderMenu,
                'active_notice' => $activeNotice,
            ],
            'branding' => [
                'airline_name' => $rawSettings['airline_name'] ?? 'Naoero Airlines',
                'logo' => $logoUrl,
                'booking_engine_url' => $rawSettings['booking_engine_url'] ?? 'https://book.naoeroairlines.com',
            ],
            'contact_info' => [
                'phone_australia' => $rawSettings['contact_phone_aus'] ?? '+61 7 3229 6455',
                'phone_nauru' => $rawSettings['contact_phone_nauru'] ?? '+674 557 3755',
                'reservations_email' => $rawSettings['reservations_email'] ?? 'reservations@naoeroairlines.com.au',
                'cargo_email' => $rawSettings['cargo_email'] ?? 'freight@naoeroairlines.com.au',
                'head_office_address' => $rawSettings['head_office_address'] ?? 'Naoero International Airport, PO Box 40 Republic of Naoero',
                'brisbane_office_address' => $rawSettings['brisbane_office_address'] ?? 'Level 2, 99 Creek Street, Brisbane QLD 4000',
            ],
            'social_media' => [
                'facebook' => $rawSettings['facebook_url'] ?? 'https://facebook.com/NaoeroAirlines',
                'instagram' => $rawSettings['instagram_url'] ?? 'https://instagram.com/naoeroairlines',
            ],
        ]);

        return response()->json([
            'success' => true,
            'data' => $formattedSettings,
        ]);
    }

    /**
     * Update settings via API.
     */
    public function update(Request $request): JsonResponse
    {
        // The same permission as the admin panel's Site Settings screen.
        if (! $request->user()?->can('settings.general')) {
            return response()->json(['success' => false, 'message' => 'Your account does not have permission to change site settings.'], 403);
        }
        $payload = $request->all();

        // Handle logo update
        if ($request->has('logo') || $request->has('site_logo')) {
            $logo = $request->input('logo') ?? $request->input('site_logo');
            SiteSetting::updateOrCreate(
                ['key' => 'site_logo'],
                ['value' => $logo, 'group' => 'branding', 'type' => 'image', 'label' => 'Header & Brand Logo Image']
            );
        }

        // Handle top header text
        if ($request->has('top_header_text') || $request->has('welcome_text')) {
            $text = $request->input('top_header_text') ?? $request->input('welcome_text');
            SiteSetting::updateOrCreate(
                ['key' => 'top_header_text'],
                ['value' => $text, 'group' => 'header', 'type' => 'text', 'label' => 'Top Header Welcome Announcement']
            );
        }

        // Handle menu
        if ($request->has('menu') || $request->has('top_header_menu') || $request->has('menu_items')) {
            $menu = $request->input('menu') ?? $request->input('top_header_menu') ?? $request->input('menu_items');
            $menuJson = is_array($menu) ? json_encode($menu, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) : $menu;
            SiteSetting::updateOrCreate(
                ['key' => 'top_header_menu'],
                ['value' => $menuJson, 'group' => 'header', 'type' => 'json', 'label' => 'Top Header Quick Menu Navigation']
            );
        }

        // Handle all other keys
        $excluded = ['logo', 'site_logo', 'top_header_text', 'welcome_text', 'menu', 'top_header_menu', 'menu_items', '_token', '_method'];
        foreach ($payload as $key => $value) {
            if (! in_array($key, $excluded)) {
                $val = is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) $value;
                SiteSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $val]
                );
            }
        }

        ActivityLog::log('updated', 'settings', 'Updated global settings via REST API');

        return $this->settings();
    }
}
