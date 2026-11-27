<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\PageBanner;
use App\Models\SiteSetting;
use App\Models\TravelInfoCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TravelInfoApiController extends Controller
{
    /**
     * Get Travel Information Hub & Helpful Information cards.
     */
    public function overview(Request $request): JsonResponse
    {
        $cards = TravelInfoCard::query()->active()->ordered()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'description' => $item->description,
                'icon' => $item->icon ?? 'Send',
                'route_url' => $item->route_url,
                'has_link' => $item->has_link,
                'sort_order' => $item->sort_order,
            ];
        });

        $banner = PageBanner::findByPage('travel-info');

        $introParagraphs = [
            "To ensure a smooth journey, we've compiled key travel information to help you prepare before your flight. How can we assist you? Our fleet of Boeing 737-300, 737-700, and 737-800 aircraft, in a two-class layout with business class and economy class, serve the Pacific region with unwavering focus on safety, service excellence, and innovation.",
            "We continue to bridge distances, strengthen regional ties, and enhance the travel experience for our valued passengers. Our fleet includes Boeing 737-300 passenger aircraft configured in a two-class layout, offering both Business Class and Economy Class for a comfortable travel experience.",
            "Additionally, we operate a Boeing 737-300 freighter, supporting cargo transport across the region.",
            "All our aircraft are maintained to the highest standards, following Boeing specifications and Civil Aviation Safety Authority of Australia (CASA) regulations, ensuring the utmost safety and reliability for our passengers and cargo customers alike.",
            "For more information on baggage, check-in, onboard services, and travel policies, please explore our website or reach out to our team. We look forward to welcoming you on board!",
        ];

        $quickLinks = [
            [
                'title' => 'Before you fly',
                'description' => 'Read more about our Before you fly travel checklist here !',
                'link' => '/travel-info/before-you-fly',
                'image' => url('/assets/team/quickLink1.png'),
            ],
            [
                'title' => 'Destination',
                'description' => 'Explore our Pacific flight network and island guides.',
                'link' => '/destinations',
                'image' => url('/assets/team/quickLink2.png'),
            ],
            [
                'title' => 'On Board',
                'description' => 'Pacific hospitality, inflight meals, and cabin comfort.',
                'link' => '/on-board',
                'image' => url('/assets/team/quickLink3.png'),
            ],
        ];

        return response()->json([
            'success' => true,
            'heading' => 'Travel Information',
            'subheading' => 'Your Journey with Naoero Airlines. No matter your destination or reason for travel, our goal is to get you there safely, quickly, and comfortably',
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
            ] : null,
            'intro_paragraphs' => $introParagraphs,
            'helpful_information' => [
                'heading' => 'Helpful Information For Your Journey',
                'count' => $cards->count(),
                'cards' => $cards,
            ],
            'quick_links' => $quickLinks,
        ]);
    }

    /**
     * Get Airport Port Check-In Times Timetable.
     */
    public function checkInTimes(Request $request): JsonResponse
    {
        $ports = CheckInTime::query()->active()->ordered()->get()->map(function ($p) {
            return [
                'id' => $p->id,
                'port' => $p->port,
                'airport_code' => $p->airport_code,
                'opens' => $p->opens,
                'closes' => $p->closes,
                'highlighted' => (bool)$p->is_highlighted,
                'is_highlighted' => (bool)$p->is_highlighted,
                'notes' => $p->notes,
                'sort_order' => $p->sort_order,
            ];
        });

        $banner = PageBanner::findByPage('check-in-times');

        return response()->json([
            'success' => true,
            'heading' => 'Check-In Times',
            'subheading' => 'Here at Naoero Airlines, we try to make your check-in experience as smooth as possible.',
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
            ] : null,
            'effective_notice' => 'New Check-in Times for Tarawa, Majuro and Pohnpei - Effective 6th November 2025',
            'count' => $ports->count(),
            'check_in_times' => $ports,
            'data' => $ports,
            'important_information' => [
                'We ask that you arrive at the airport to complete check-in and immigration formalities as early as possible to allow plenty of time for a smooth transition through the airport to your gate.',
                'If you require additional assistance due to mobility or special needs, please contact us prior to departure.',
                'At Check-in you will be asked to show a ticket that shows you travelling on that date. Tickets showing any other date will not be accepted. All flight changes must be made with Reservations in line with the conditions attached to your fare. A copy of the conditions are sent with every e-ticket purchased.',
                'As some ports require a return ticket, you may also be required to present a ticket showing your onward journey.',
                'Tickets may be presented either on paper or on an electronic device.',
            ],
            'boarding_rule' => 'You will also need to be at the boarding gate no later than 30 minutes before the stated departure time. This will assist us in ensuring an on-time departure and minimize delays and inconvenience to all passengers.',
            'connecting_carriers' => [
                'rules' => [
                    'You will receive your Boarding Pass and checked Baggage receipts for every bag that you check in for your Naoero Airlines flight.',
                    'Check-in opens 3 hours before your flight is scheduled to depart.',
                    'Check-in closes 1 hour before the scheduled departure time.',
                ],
                'notice' => 'Naoero Airlines does not have provision to transfer baggage to/from non-Naoero Airlines flights.',
            ],
            'support_contact' => [
                'phone' => '+61 7 3229 6455',
                'email' => 'reservations@naoeroair.com',
            ],
        ]);
    }

    /**
     * Get Dangerous Goods Guide & Prohibited Items Regulations.
     */
    public function dangerousGoods(Request $request): JsonResponse
    {
        $quickRef = DangerousGoodsItem::query()->active()->category('quick_reference')->ordered()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'item' => $item->item_name,
                'item_name' => $item->item_name,
                'carry_on' => $item->carry_on,
                'checked' => $item->checked,
                'approval' => $item->approval_required,
                'approval_required' => $item->approval_required,
            ];
        });

        $conditional = DangerousGoodsItem::query()->active()->category('conditional')->ordered()->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->item_name,
                'description' => $item->details,
                'icon' => $item->icon,
                'carry_on' => $item->carry_on,
                'checked' => $item->checked,
                'approval' => $item->approval_required,
            ];
        });

        $banner = PageBanner::findByPage('dangerous-goods');

        return response()->json([
            'success' => true,
            'heading' => 'Dangerous Goods',
            'subheading' => 'Important passenger safety guidelines and regulated materials.',
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
            ] : null,
            'last_reviewed_at' => '2026-08-18',
            'next_review_at' => '2027-01-01',
            'important_notice' => 'IMPORTANT: Declare dangerous goods during online or airport check-in. Dangerous goods are items or substances that may endanger the safety of an aircraft or people on board.',
            'quick_reference' => $quickRef,
            'items_accepted_with_conditions' => $conditional,
            'other_dangerous_goods' => [
                'Explosives and fireworks',
                'Flammable liquids and fuels',
                'Gas cylinders and compressed gases',
                'Corrosive or toxic substances',
                'Security or disabling devices containing dangerous goods',
                'Electro-shock weapons or similar prohibited devices',
            ],
            'cabin_bag_moved_to_hold_removal' => [
                'Power banks',
                'Spare lithium batteries',
                'Vapes and electronic cigarettes',
            ],
            'alcohol_policy' => 'Alcoholic beverages are subject to dangerous goods limits based on alcohol strength and packaging in compliance with current IATA Dangerous Goods Regulations and CASA guidelines.',
            'support_phone' => '+61 7 3229 6455',
            'resources' => [
                ['label' => 'CASA Pack Right', 'description' => 'search your item before you fly.'],
                ['label' => 'IATA Dangerous Goods Guidance for Passengers', 'description' => 'current passenger guidance and Table 2.3.A.'],
                ['label' => 'Naoero Airlines – Dangerous Goods', 'description' => 'terms and operator-specific requirements.'],
            ],
        ]);
    }

    /**
     * Get Before You Fly Index & Sub-categories.
     */
    public function beforeYouFly(Request $request): JsonResponse
    {
        $banner = PageBanner::findByPage('before-you-fly');

        $sections = [
            [
                'slug' => 'check-in-times',
                'title' => 'Check-In Times',
                'description' => 'Official airport check-in desk opening and closing timetable across all Pacific ports.',
                'route' => '/travel-info/before-you-fly/check-in-times',
                'icon' => 'Clock',
            ],
            [
                'slug' => 'dangerous-goods',
                'title' => 'Dangerous Goods',
                'description' => 'Carry-on vs checked baggage regulations for batteries, power banks, liquids, and restricted items.',
                'route' => '/travel-info/dangerous-goods',
                'icon' => 'AlertTriangle',
            ],
            [
                'slug' => 'conditions-of-carriage',
                'title' => 'Conditions of Carriage',
                'description' => 'Contractual legal terms, passenger rights, liability limitations, and baggage claims.',
                'route' => '/travel-info/conditions-of-carriage',
                'icon' => 'FileText',
            ],
            [
                'slug' => 'flying-while-pregnant',
                'title' => 'Flying While Pregnant',
                'description' => 'Medical clearances, travel gestation limits, and health guidelines for expectant mothers.',
                'route' => '/travel-info/before-you-fly/flying-while-pregnant',
                'icon' => 'Heart',
            ],
            [
                'slug' => 'travelling-with-children',
                'title' => 'Travelling With Children And Infants',
                'description' => 'Strollers, infant baggage allowances, onboard bassinets, and family seating.',
                'route' => '/travel-info/before-you-fly/travelling-with-children',
                'icon' => 'Users',
            ],
            [
                'slug' => 'unaccompanied-minors',
                'title' => 'Unaccompanied Minors',
                'description' => 'Dedicated staff supervision, guardian drop-off, and travel guidelines for solo young flyers.',
                'route' => '/travel-info/before-you-fly/unaccompanied-minors',
                'icon' => 'UserCheck',
            ],
            [
                'slug' => 'mobility-assistance',
                'title' => 'Special Requests and Mobility Assistance',
                'description' => 'Wheelchair services, medical oxygen, and assistance for passengers with disabilities.',
                'route' => '/travel-info/before-you-fly/mobility-assistance',
                'icon' => 'Accessibility',
            ],
        ];

        return response()->json([
            'success' => true,
            'heading' => 'Before You Fly',
            'subheading' => 'Everything you need to know to ensure a seamless and enjoyable Pacific journey.',
            'banner' => $banner ? [
                'heading' => $banner->banner_heading,
                'subheading' => $banner->banner_badge_text,
                'description' => $banner->banner_description,
                'image' => $banner->banner_image_url,
            ] : null,
            'count' => count($sections),
            'sections' => $sections,
        ]);
    }
}
