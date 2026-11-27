<?php

namespace Database\Seeders;

use App\Models\AgencyContact;
use App\Models\FlightSchedule;
use App\Models\GalleryItem;
use Illuminate\Database\Seeder;

class AdditionalCMSSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Travel Agency Contacts
        $agencies = [
            // FSM
            [
                'country' => 'Federated States of Micronesia',
                'flag' => '🇫🇲',
                'name' => 'House of Travel',
                'address' => 'Pohnpei, Micronesia',
                'phones' => ['+691 320 5888'],
                'emails' => ['hotravel@outlook.com', 'hotravel@mail.fm'],
                'website' => null,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'country' => 'Federated States of Micronesia',
                'flag' => '🇫🇲',
                'name' => 'The Village Travel',
                'address' => 'Pohnpei, Micronesia',
                'phones' => ['+691 320 2777'],
                'emails' => ['villagetravel2@gmail.com'],
                'website' => 'https://villagetravel2.wixsite.com/thevillagetravel',
                'is_active' => true,
                'sort_order' => 2,
            ],
            // Fiji
            [
                'country' => 'Fiji',
                'flag' => '🇫🇯',
                'name' => 'One World Flight Centre - Suva',
                'address' => 'Mid City Plaza, Cumming Street, Suva',
                'phones' => ['+679 330 4656'],
                'emails' => ['sales@oneworldflight.com.fj'],
                'website' => 'https://oneworldflight.com.fj',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'country' => 'Fiji',
                'flag' => '🇫🇯',
                'name' => 'One World Flight Centre - Nadi',
                'address' => 'Main Street, Nadi',
                'phones' => ['+679 670 8156'],
                'emails' => ['owfcnadi@oneworldflight.com.fj'],
                'website' => 'https://oneworldflight.com.fj',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'country' => 'Fiji',
                'flag' => '🇫🇯',
                'name' => 'Lodhias Travel Services',
                'address' => 'Shop 1 Kwong Tiy Plaza Marks St. G.P.O. Box 595',
                'phones' => ['+679 331 5844'],
                'emails' => ['info@lts747.com'],
                'website' => 'https://www.lts.com.fj/',
                'is_active' => true,
                'sort_order' => 5,
            ],
            // Kiribati
            [
                'country' => 'Kiribati',
                'flag' => '🇰🇮',
                'name' => 'Tobaraoi Travel',
                'address' => 'Tobaraoi Building, Lagoon Drive, Bikenibeu, Tarawa, Kiribati',
                'phones' => ['+686 7402 8715'],
                'emails' => ['info@tobaraoi.com'],
                'website' => 'https://tobaraoi.com',
                'is_active' => true,
                'sort_order' => 6,
            ],
            // Marshall Islands
            [
                'country' => 'Marshall Islands',
                'flag' => '🇲🇭',
                'name' => 'Air Marshall Islands',
                'address' => '1319 Uliga, Downtown, Majuro, Marshall Islands',
                'phones' => ['+692 625 3931'],
                'emails' => ['supports@airmarshallislands.net'],
                'website' => 'https://www.airmarshallislands.net/',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'country' => 'Marshall Islands',
                'flag' => '🇲🇭',
                'name' => 'Pacific Unique Travel',
                'address' => '726 Delap Boulevard, Lagoon Rd, Majuro, Republic of Marshall Islands',
                'phones' => ['+692 625 3409'],
                'emails' => ['salesagent@putrmi.com'],
                'website' => 'https://www.piimajuro.com/',
                'is_active' => true,
                'sort_order' => 8,
            ],
            // Naoero
            [
                'country' => 'Naoero',
                'flag' => '🇳🇷',
                'name' => 'Naoero Airlines Main Office',
                'address' => 'Naoero International Airport Concourse',
                'phones' => ['+674 557 7005'],
                'emails' => ['reservations@naoeroairlines.com.au'],
                'website' => 'https://nauruair.com',
                'is_active' => true,
                'sort_order' => 9,
            ],
            // Palau
            [
                'country' => 'Palau',
                'flag' => '🇵🇼',
                'name' => 'Palau Travel and Tour Agency',
                'address' => 'Koror, Republic of Palau',
                'phones' => ['+680 488 2836', '+680 488 2838'],
                'emails' => ['palautravelagency@gmail.com'],
                'website' => 'https://palautravel-agency.blogspot.com/',
                'is_active' => true,
                'sort_order' => 10,
            ],
            // Solomon Islands
            [
                'country' => 'Solomon Islands',
                'flag' => '🇸🇧',
                'name' => 'Guadalcanal Travel Services',
                'address' => 'City Centre Building, Mendana Ave, Honiara, Solomon Islands',
                'phones' => ['+677 22586'],
                'emails' => ['gtstravel@gts.com.sb'],
                'website' => 'https://solomontravel.sb/',
                'is_active' => true,
                'sort_order' => 11,
            ],
        ];

        foreach ($agencies as $item) {
            AgencyContact::updateOrCreate(
                ['name' => $item['name'], 'country' => $item['country']],
                $item
            );
        }

        // 2. Flight Schedules
        FlightSchedule::updateOrCreate(
            ['title' => 'NS26-NW27 Updated Schedule'],
            [
                'title' => 'NS26-NW27 Updated Schedule',
                'period' => '02nd July 2026 - 04th January 2027',
                'route' => 'Brisbane - Naoero - Tarawa - Majuro - Nadi - Suva',
                'file_url' => 'https://nauruair.com/schedules/NS26-NW27-Schedule.pdf',
                'effective_from' => '2026-07-02',
                'effective_until' => '2027-01-04',
                'notes' => "Schedules published on the website are to be used as a guide only, as flights and times are subject to change.\nImportant Notice: Flights between Tarawa and Kiritimati (ON123 & ON124) will operate via Apia (tech stop only) temporarily.",
                'alert_notice' => 'Runway works are expected to resume at Naoero International Airport between June - September 2026. Scheduled services may be subject to schedule modification.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // 3. Gallery Items
        $gallery = [
            ['title' => 'Naoero Coastal Coastline', 'destination' => 'Naoero', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'],
            ['title' => 'Brisbane River & Skyline', 'destination' => 'Brisbane', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1506973035872-a4ec16b8e8d9?auto=format&fit=crop&w=800&q=80'],
            ['title' => 'Tarawa Lagoon at Sunrise', 'destination' => 'Tarawa', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1540555700478-4be289fbecef?auto=format&fit=crop&w=800&q=80'],
            ['title' => 'Majuro Coral Atoll Waters', 'destination' => 'Majuro', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1510414842594-a61c69b5ae57?auto=format&fit=crop&w=800&q=80'],
            ['title' => 'Pohnpei Tropical Rainforest', 'destination' => 'Pohnpei', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=800&q=80'],
            ['title' => 'Rock Islands Southern Lagoon, Palau', 'destination' => 'Palau', 'category' => 'destination', 'image_url' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?auto=format&fit=crop&w=800&q=80'],
        ];

        foreach ($gallery as $i => $item) {
            GalleryItem::updateOrCreate(
                ['title' => $item['title']],
                array_merge($item, ['is_active' => true, 'sort_order' => $i + 1])
            );
        }
    }
}
