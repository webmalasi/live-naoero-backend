<?php

namespace Database\Seeders;

use App\Models\EnhanceJourneyItem;
use App\Models\OurJourneyMilestone;
use Illuminate\Database\Seeder;

class JourneySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Our Journey Timeline Milestones (About Us Page)
        $milestones = [
            [
                'year' => '1970',
                'title' => 'Inception & First Flight',
                'description' => 'Air Nauru commences commercial operations connecting the island Republic of Nauru with Brisbane, Australia.',
                'image' => '/assets/about/ourjourney1.png',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'year' => '1985',
                'title' => 'Pacific Network Expansion',
                'description' => 'Route network expands to encompass Fiji, Kiribati, Marshall Islands, Micronesia, and New Zealand.',
                'image' => '/assets/about/ourjourney2.png',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'year' => '2006',
                'title' => 'Rebranding to Our Airline',
                'description' => 'Strategic regional partnership and jet modernization with Boeing 737 aircraft fleet.',
                'image' => '/assets/about/ourjourney3.png',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'year' => '2024+',
                'title' => 'Naoero Airlines & Next-Gen Fleet',
                'description' => 'Introduction of Boeing 737-800 passenger and dedicated freighter aircraft connecting Australia, Micronesia, and Palau.',
                'image' => '/assets/about/ourjourney4.png',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($milestones as $milestone) {
            OurJourneyMilestone::updateOrCreate(
                ['year' => $milestone['year']],
                $milestone
            );
        }

        // 2. Enhance Your Journey Cards (Homepage Booking Essentials)
        $enhanceItems = [
            [
                'title' => 'Hotels',
                'description' => 'Comfortable stays at trusted hotels near your destination.',
                'icon' => 'BedDouble',
                'image' => '/assets/home/hotesls.jpg',
                'link_url' => '/hotels',
                'link_text' => 'Know More',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Rental Car',
                'description' => 'Rent a car and explore every destination with ease.',
                'icon' => 'Car',
                'image' => '/assets/home/Rentalcar.png',
                'link_url' => '/car-rental',
                'link_text' => 'Know More',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Travel Insurance',
                'description' => 'Travel with confidence through comprehensive insurance coverage.',
                'icon' => 'HeartHandshake',
                'image' => '/assets/home/travelinsurenace.png',
                'link_url' => '/travel-insurance',
                'link_text' => 'Know More',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Activities',
                'description' => 'Explore exciting attractions, tours, and unforgettable adventures.',
                'icon' => 'Compass',
                'image' => '/assets/home/activity.png',
                'link_url' => '/activities',
                'link_text' => 'Know More',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($enhanceItems as $item) {
            EnhanceJourneyItem::updateOrCreate(
                ['title' => $item['title']],
                $item
            );
        }
    }
}
