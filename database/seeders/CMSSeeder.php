<?php

namespace Database\Seeders;

use App\Models\Career;
use App\Models\Faq;
use App\Models\Fleet;
use App\Models\Notice;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\SpecialOffer;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class CMSSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Fleets
        $fleets = [
            [
                'type' => 'B737-300',
                'registration' => 'VH-XNU',
                'category' => 'passenger',
                'seating' => '18J/102Y',
                'image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-XNU Boeing 737-300 Passenger Aircraft',
                'description' => 'Comfortable passenger aircraft serving Pacific routes with 18 Business Class and 102 Economy seats.',
                'sort_order' => 1,
            ],
            [
                'type' => 'B737-700',
                'registration' => 'VH-INU',
                'category' => 'passenger',
                'seating' => '12J/114Y',
                'image' => 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-INU Boeing 737-700 Passenger Aircraft',
                'description' => 'Modern twin-engine aircraft offering enhanced passenger comfort with 12 Business and 114 Economy seats.',
                'sort_order' => 2,
            ],
            [
                'type' => 'B737-800',
                'registration' => 'VH-AN2',
                'category' => 'passenger',
                'seating' => '10J/168Y',
                'image' => 'https://images.unsplash.com/photo-1520437358207-323b43b50729?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-AN2 Boeing 737-800 Passenger Aircraft',
                'description' => 'High capacity next-generation aircraft with spacious seating for up to 178 passengers.',
                'sort_order' => 3,
            ],
            [
                'type' => 'B737-300F',
                'registration' => 'VH-ONU',
                'category' => 'freight',
                'configuration' => "8 x PAG ULD's 10-12m3 EA",
                'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-ONU B737-300F Dedicated Freighter',
                'description' => 'Dedicated air freight cargo aircraft connecting Australia and Pacific island supply chains.',
                'sort_order' => 4,
            ],
            [
                'type' => 'B737-300F',
                'registration' => 'VH-YNU',
                'category' => 'freight',
                'configuration' => "8 x PAG ULD's 10-12m3 EA",
                'image' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-YNU B737-300F',
                'description' => 'Reliable Pacific freight workhorse with 8 pallet positions.',
                'sort_order' => 5,
            ],
            [
                'type' => 'B737-800F',
                'registration' => 'VH-8TG',
                'category' => 'freight',
                'configuration' => '1 x ULD 88" x 53", 11 x ULD 88" x 125"',
                'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&auto=format&fit=crop&q=80',
                'caption' => 'VH-8TG B737-800F Next-Gen Freighter',
                'description' => 'State-of-the-art Boeing 737-800 Freighter with higher payload capacity and greater range.',
                'sort_order' => 6,
            ],
        ];

        foreach ($fleets as $fleet) {
            Fleet::updateOrCreate(['registration' => $fleet['registration']], $fleet);
        }

        // 2. Posts (Blogs & News Hub Articles)
        $posts = [
            // BLOG ARTICLES (type => 'blog')
            [
                'title' => 'Naoero Airlines Expands Fiji Services With New Suva Route',
                'slug' => 'naoero-airlines-expands-fiji-services-with-new-suva-route',
                'type' => 'blog',
                'category' => 'Temporary Change',
                'author' => 'Naoero Airlines Team',
                'excerpt' => 'Naoero Airlines has officially launched a brand-new service to Suva, Fiji.',
                'content' => 'We are excited to announce the expansion of our Pacific flight network with a new direct service to Nausori International Airport in Suva, Fiji. This new route strengthens our commitment to connecting island communities with greater convenience, business opportunities, and tourism access.',
                'image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(5),
            ],
            [
                'title' => 'Naoero Hosts Inaugural Miss Micronesia Pageant and Micronesia Music Festival',
                'slug' => 'naoero-hosts-inaugural-miss-micronesia-pageant',
                'type' => 'blog',
                'category' => 'Pohnpei Blog',
                'author' => 'Cultural Affairs',
                'excerpt' => 'Naoero Hosts Inaugural Miss Micronesia Pageant & Micronesia Music Festival.',
                'content' => 'Delegates from across Micronesia gathered in Naoero for an unforgettable cultural showcase celebrating traditional dance, musical performances, and island heritage.',
                'image' => 'https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(10),
            ],
            [
                'title' => 'AERgO International Appointed Exclusive GSA for Naoero Airlines',
                'slug' => 'aergo-international-appointed-exclusive-gsa-for-naoero-airlines',
                'type' => 'blog',
                'category' => 'Pohnpei Blog',
                'author' => 'Tomahawk Support',
                'excerpt' => 'Naoero Airlines and AERgO International are pleased to announce the appointment.',
                'content' => 'Naoero Airlines has appointed AERgO International as its General Sales Agent (GSA) to expand global distribution and provide seamless flight booking services across regional partner networks.',
                'image' => 'https://images.unsplash.com/photo-1436491865332-7a61a109cc05?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(15),
            ],
            [
                'title' => 'Temporary Change Pohnpei-Palau-Pohnpei 21-22-28-29 March 2025',
                'slug' => 'temporary-change-pohnpei-palau-pohnpei',
                'type' => 'blog',
                'category' => 'Temporary Change',
                'author' => 'Flight Operations',
                'excerpt' => 'A temporary operational adjustment to our scheduled services between Pohnpei and Palau.',
                'content' => 'Please note a minor adjustment to scheduled flight times on the Pohnpei-Palau route due to routine airport runway maintenance.',
                'image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(20),
            ],
            [
                'title' => 'Pleasant Book On Pleasant Island',
                'slug' => 'why-surfing-in-pohnpei',
                'type' => 'blog',
                'category' => 'Pohnpei Blog',
                'author' => 'Guest Explorer',
                'excerpt' => 'After 30 years of waiting, a visit to Naoero achieves a childhood dream and results in a beautiful book.',
                'content' => 'Exploring the tranquil shores and warm communities of Pleasant Island (Naoero) offers a unique glimpse into the heart of Micronesia.',
                'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(25),
            ],
            [
                'title' => 'Leon Software Implementation',
                'slug' => 'leon-software',
                'type' => 'blog',
                'category' => 'Pohnpei Blog',
                'author' => 'Aviation Tech Team',
                'excerpt' => 'Leon started its life in Naoero Airlines to cover flight scheduling and flight crew rostering.',
                'content' => 'Implementing modern aviation software solutions allows Naoero Airlines to optimize crew rostering, flight dispatch, and aircraft turnarounds across our growing Pacific route network.',
                'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(30),
            ],
            [
                'title' => 'Merry Christmas From Naoero Airlines',
                'slug' => 'merry-christmas-from-naoero-airlines',
                'type' => 'blog',
                'category' => 'Pohnpei Blog',
                'author' => 'Executive Team',
                'excerpt' => 'Naoero Airlines joins the Naoeroan people in their celebration of Christmas.',
                'content' => 'Wishing all our valued passengers, partners, and team members across Australia and the Pacific Islands a safe and joyful holiday season.',
                'image' => 'https://images.unsplash.com/photo-1512389142860-9c449e58a543?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(40),
            ],

            // NEWS HUB & MEDIA RELEASES (type => 'news')
            [
                'title' => 'Brisbane to Palau - 6 hours Non-Stop',
                'slug' => 'brisbane-to-palau-6-hours-non-stop',
                'type' => 'news',
                'category' => 'Media Release',
                'author' => 'Media Relations',
                'excerpt' => 'Naoero Airlines introduces non-stop flights connecting Brisbane and Palau in just 6 hours.',
                'content' => 'Naoero Airlines is pleased to announce direct flights from Brisbane International Airport to Roman Tmetuchl International Airport in Palau, creating the fastest connection between Australia and the Republic of Palau.',
                'image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'title' => 'Remembering Carla Zampatti',
                'slug' => 'remembering-carla-zampatti',
                'type' => 'news',
                'category' => 'Sponsored Events',
                'author' => 'Public Affairs',
                'excerpt' => 'Naoero Airlines honors the legacy and enduring contributions of iconic designer Carla Zampatti.',
                'content' => 'Naoero Airlines pays tribute to the extraordinary life and achievements of Carla Zampatti AC, recognizing her immense contributions to Pacific design and cultural empowerment.',
                'image' => 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(7),
            ],
            [
                'title' => 'Naoero Airlines and Leon Software Partnership Announcement',
                'slug' => 'naoero-airlines-and-leon-software-partnership-announcement',
                'type' => 'news',
                'category' => 'Media Release',
                'author' => 'Media Relations',
                'excerpt' => 'Naoero Airlines and Leon Software sign strategic operations agreement.',
                'content' => 'A strategic partnership has been finalized between Naoero Airlines and Leon Software to implement an integrated cloud aviation platform for real-time fleet tracking and crew management.',
                'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(12),
            ],
            [
                'title' => 'Naoero Airlines Sponsors Pacific Regional Sports Championship',
                'slug' => 'naoero-airlines-sponsors-pacific-regional-sports-championship',
                'type' => 'news',
                'category' => 'Sponsored Sporting Events',
                'author' => 'Community Partnerships',
                'excerpt' => 'Proudly supporting island athletics and community sporting excellence across the Pacific.',
                'content' => 'As part of our continuous commitment to Pacific youth and community welfare, Naoero Airlines is sponsoring flight travel for regional sports delegations participating in upcoming international tournaments.',
                'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(18),
            ],
            [
                'title' => 'Naoero Airlines Adopts Technology to Help Safeguard Borders',
                'slug' => 'naoero-airlines-adopts-technology-to-help-safeguard-borders',
                'type' => 'news',
                'category' => 'Sponsored Events',
                'author' => 'Safety & Compliance',
                'excerpt' => 'Deploying automated biometric and advanced passenger information processing systems.',
                'content' => 'In collaboration with border security agencies across Australia and Micronesia, Naoero Airlines has integrated next-generation automated passenger verification systems to ensure highest standards of aviation safety.',
                'image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800&auto=format&fit=crop&q=80',
                'is_featured' => false,
                'is_published' => true,
                'published_at' => now()->subDays(24),
            ],
            [
                'title' => 'Naoero Airlines Extends Pacific Air Freight Services',
                'slug' => 'naoero-airlines-extends-pacific-air-freight-services',
                'type' => 'news',
                'category' => 'Company News',
                'author' => 'Cargo Operations',
                'excerpt' => 'Scheduled Boeing 737 freighter cargo capacity expanded across key Pacific supply routes.',
                'content' => 'To support critical medical, food supply, and commercial logistics throughout the Pacific, Naoero Airlines has added dedicated weekly freighter services connecting Brisbane, Honiara, Nauru, Tarawa, and Majuro.',
                'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&auto=format&fit=crop&q=80',
                'is_featured' => true,
                'is_published' => true,
                'published_at' => now()->subDays(28),
            ],
        ];

        foreach ($posts as $post) {
            Post::updateOrCreate(['slug' => $post['slug']], $post);
        }

        // 3. Testimonials
        $testimonials = [
            [
                'name' => 'Nick Wade',
                'location' => 'Brisbane, Australia',
                'quote' => 'Flying with Naoero Airlines was a wonderful experience. The cabin crew were extremely welcoming, on-time departure, and delicious in-flight refreshments.',
                'image' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=200&auto=format&fit=crop&q=80',
                'rating' => 5,
                'sort_order' => 1,
            ],
            [
                'name' => 'Natasha Reyes',
                'location' => 'Majuro, Marshall Islands',
                'quote' => 'The direct island connections made our family holiday seamless and stress-free. Very generous baggage allowance and comfortable seats!',
                'image' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=200&auto=format&fit=crop&q=80',
                'rating' => 5,
                'sort_order' => 2,
            ],
            [
                'name' => 'Carolyn Banks',
                'location' => 'Nadi, Fiji',
                'quote' => 'Reliable air travel across the central Pacific. The crew provided personalized service that truly makes you feel at home in the skies.',
                'image' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=200&auto=format&fit=crop&q=80',
                'rating' => 5,
                'sort_order' => 3,
            ],
        ];

        foreach ($testimonials as $testimonial) {
            Testimonial::updateOrCreate(['name' => $testimonial['name']], $testimonial);
        }

        // 4. Team Members
        $team = [
            [
                'name' => 'Captain James Dageago',
                'role' => 'Chief Pilot & Flight Operations Director',
                'department' => 'Flight Operations',
                'bio' => 'Over 25 years of commercial aviation experience flying Boeing 737 aircraft across the Pacific region.',
                'photo' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&auto=format&fit=crop&q=80',
                'sort_order' => 1,
            ],
            [
                'name' => 'Sarah Akubor',
                'role' => 'Head of Cabin Crew Services',
                'department' => 'Cabin Crew',
                'bio' => 'Dedicated to delivering authentic Pacific hospitality and maintaining the highest safety standards onboard.',
                'photo' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&auto=format&fit=crop&q=80',
                'sort_order' => 2,
            ],
            [
                'name' => 'David Detenamo',
                'role' => 'Commercial & Freight Manager',
                'department' => 'Commercial & Cargo',
                'bio' => 'Leading cargo charter operations and passenger route network development.',
                'photo' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=400&auto=format&fit=crop&q=80',
                'sort_order' => 3,
            ],
        ];

        foreach ($team as $member) {
            TeamMember::updateOrCreate(['name' => $member['name']], $member);
        }

        // 5. Careers
        $careers = [
            [
                'title' => 'B737 First Officer / Captain',
                'slug' => 'b737-first-officer-captain',
                'department' => 'Flight Operations',
                'location' => 'Brisbane, Australia',
                'employment_type' => 'Full-Time',
                'short_description' => 'Seeking experienced Boeing 737 pilots to join our Australian and Pacific operations.',
                'description' => 'Naoero Airlines is currently seeking suitably qualified B737 Captains and First Officers based in Brisbane for our passenger and freight network.',
                'requirements' => "• Valid ATPL (Air Transport Pilot Licence)\n• Current B737 Type Rating\n• Minimum 2,000 total flying hours\n• Class 1 Medical Certificate",
                'seek_url' => 'https://www.seek.com.au',
                'deadline' => now()->addMonths(2),
                'sort_order' => 1,
            ],
            [
                'title' => 'Flight Attendant / Cabin Crew',
                'slug' => 'flight-attendant-cabin-crew',
                'department' => 'In-Flight Services',
                'location' => 'Brisbane & Naoero',
                'employment_type' => 'Full-Time',
                'short_description' => 'Deliver world-class service and safety to our passengers across the Pacific.',
                'description' => 'We are welcoming enthusiastic, customer-focused individuals with a passion for aviation and Pacific hospitality.',
                'requirements' => "• Aviation Cabin Crew certification or prior customer service experience\n• Senior First Aid & CPR certificate\n• Valid passport with unrestricted travel rights\n• Fluent in English (Naoeroan a bonus)",
                'seek_url' => 'https://www.seek.com.au',
                'deadline' => now()->addMonths(1),
                'sort_order' => 2,
            ],
        ];

        foreach ($careers as $career) {
            Career::updateOrCreate(['slug' => $career['slug']], $career);
        }

        // 6. Special Offers
        $specials = [
            [
                'title' => 'Brisbane to Naoero Return Special',
                'slug' => 'brisbane-to-naoero-return-special',
                'origin' => 'Brisbane (BNE)',
                'destination' => 'Naoero (INU)',
                'price' => 899.00,
                'currency' => 'AUD',
                'badge' => 'Special Fare',
                'description' => 'Experience the natural beauty and warm welcome of Naoero with our exclusive seasonal special.',
                'terms' => 'Includes 30kg checked baggage and complimentary inflight meal. Non-refundable. Travel dates apply.',
                'image' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?w=800&auto=format&fit=crop&q=80',
                'valid_from' => now()->startOfMonth(),
                'valid_until' => now()->addMonths(3),
                'sort_order' => 1,
            ],
            [
                'title' => 'Pacific Island Hopper: Nadi to Tarawa',
                'slug' => 'pacific-island-hopper-nadi-to-tarawa',
                'origin' => 'Nadi (NAN)',
                'destination' => 'Tarawa (TRW)',
                'price' => 649.00,
                'currency' => 'AUD',
                'badge' => 'Island Explorer',
                'description' => 'Hop between Fiji and Kiribati seamlessly with our convenient weekly connections.',
                'terms' => 'Subject to seat availability. Taxes and surcharges included.',
                'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800&auto=format&fit=crop&q=80',
                'valid_from' => now()->startOfMonth(),
                'valid_until' => now()->addMonths(3),
                'sort_order' => 2,
            ],
        ];

        foreach ($specials as $offer) {
            SpecialOffer::updateOrCreate(['slug' => $offer['slug']], $offer);
        }

        // 7. FAQs
        $faqs = [
            [
                'category' => 'Baggage',
                'question' => 'What is the checked baggage allowance for Economy and Business class?',
                'answer' => 'Economy class passengers enjoy a generous 23kg checked baggage allowance. Business class passengers receive up to 32kg checked baggage, in addition to 7kg carry-on baggage.',
                'sort_order' => 1,
            ],
            [
                'category' => 'Check-in & Boarding',
                'question' => 'When does check-in open and close for international flights?',
                'answer' => 'Check-in counters open 3 hours prior to scheduled departure and strictly close 60 minutes before departure for international flights.',
                'sort_order' => 2,
            ],
            [
                'category' => 'Special Assistance',
                'question' => 'How can I request wheelchair assistance or mobility support?',
                'answer' => 'Wheelchair and mobility assistance can be requested at the time of booking or by contacting our reservations team at least 48 hours before your flight.',
                'sort_order' => 3,
            ],
            [
                'category' => 'Flight Schedule',
                'question' => 'Where can I download the latest flight schedule?',
                'answer' => 'You can view and download the updated PDF schedule directly on our Travel Info > Flight Schedule page.',
                'sort_order' => 4,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }

        // 8. Notice Bar & Flight Alerts
        Notice::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Update and News',
                'badge_text' => 'Update and News',
                'message' => 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
                'link' => '/flight-schedule',
                'type' => 'warning',
                'is_active' => true,
                'duration_hours' => 24,
                'starts_at' => now(),
                'expires_at' => now()->addHours(24),
                'priority' => 10,
            ]
        );

        Notice::updateOrCreate(
            ['id' => 2],
            [
                'title' => 'Pacific Flight Operations',
                'badge_text' => 'Schedule Update',
                'message' => 'Naoero Airlines Pacific route network is operating on regular schedule. Plan your next island getaway today!',
                'link' => '/destinations',
                'type' => 'info',
                'is_active' => true,
                'duration_hours' => 72,
                'starts_at' => now(),
                'expires_at' => now()->addHours(72),
                'priority' => 5,
            ]
        );

        // 9. Site Settings
        $settings = [
            ['key' => 'airline_name', 'value' => 'Naoero Airlines', 'group' => 'general', 'label' => 'Airline Official Name'],
            ['key' => 'site_logo', 'value' => '/assets/logo.png', 'group' => 'branding', 'label' => 'Header & Brand Logo Image'],
            ['key' => 'top_header_text', 'value' => 'Welcome to Naoero Airline', 'group' => 'header', 'label' => 'Top Header Welcome Announcement'],
            [
                'key' => 'top_header_menu',
                'value' => json_encode([
                    ['label' => 'My Booking', 'route' => 'https://book.naoeroairlines.com/manage', 'icon' => 'Ticket', 'is_external' => true],
                    ['label' => 'Flight Status', 'route' => '/travel-info/flight-schedule', 'icon' => 'PlaneTakeoff', 'is_external' => false],
                    ['label' => 'Contact Us', 'route' => '/contact-us', 'icon' => 'Phone', 'is_external' => false],
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                'group' => 'header',
                'type' => 'json',
                'label' => 'Top Header Quick Menu Navigation',
            ],
            ['key' => 'booking_engine_url', 'value' => 'https://book.naoeroairlines.com', 'group' => 'general', 'label' => 'Online Booking Engine URL'],
            ['key' => 'notice_bar_enabled', 'value' => '1', 'group' => 'general', 'label' => 'Header Notice Bar Master Switch'],
            ['key' => 'contact_phone_aus', 'value' => '+61 7 3229 6455', 'group' => 'contact', 'label' => 'Australia Contact Number'],
            ['key' => 'contact_phone_nauru', 'value' => '+674 557 3755', 'group' => 'contact', 'label' => 'Naoero Contact Number'],
            ['key' => 'reservations_email', 'value' => 'reservations@naoeroairlines.com.au', 'group' => 'contact', 'label' => 'Reservations Email'],
            ['key' => 'cargo_email', 'value' => 'freight@naoeroairlines.com.au', 'group' => 'contact', 'label' => 'Cargo & Freight Email'],
            ['key' => 'head_office_address', 'value' => 'Naoero International Airport, PO Box 40 Republic of Naoero', 'group' => 'contact', 'label' => 'Head Office Address'],
            ['key' => 'brisbane_office_address', 'value' => 'Level 2, 99 Creek Street, Brisbane QLD 4000', 'group' => 'contact', 'label' => 'Brisbane Operations Office'],
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/NaoeroAirlines', 'group' => 'social', 'label' => 'Facebook Page'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/naoeroairlines', 'group' => 'social', 'label' => 'Instagram Profile'],
        ];

        foreach ($settings as $setting) {
            SiteSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
