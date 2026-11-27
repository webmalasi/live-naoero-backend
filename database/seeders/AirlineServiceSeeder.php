<?php

namespace Database\Seeders;

use App\Models\AirlineService;
use Illuminate\Database\Seeder;

class AirlineServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'title' => 'Passenger Services – Comfort & Hospitality Above the Clouds',
                'slug' => 'passenger-services',
                'type' => 'passenger',
                'icon' => 'bottleglass',
                'image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800&auto=format&fit=crop&q=80',
                'description' => 'We take pride in delivering exceptional inflight service aboard our Boeing 737 aircraft. As a full-service airline, we ensure every passenger enjoys comfortable seating, delicious onboard meals, and generous baggage allowances while traveling to some of the most stunning destinations in the Pacific.',
                'features' => [
                    'Complimentary in-flight Pacific meals & beverages',
                    'Generous baggage allowance on all international routes',
                    'Spacious Business and Economy seating configurations',
                    'Dedicated family and special passenger assistance',
                ],
                'cta_text' => 'Book Flights',
                'cta_link' => 'https://book.naoeroairlines.com',
                'sort_order' => 1,
            ],
            [
                'title' => 'Cargo & Freight – Reliable Transport Across the Pacific',
                'slug' => 'cargo-freight-services',
                'type' => 'cargo',
                'icon' => 'cargofreight',
                'image' => 'https://images.unsplash.com/photo-1578575437130-527eed3abbec?w=800&auto=format&fit=crop&q=80',
                'description' => 'With a dedicated cargo fleet, Naoero Airlines provides safe, efficient, and timely freight solutions across Australia, Micronesia, and the South Pacific.',
                'features' => [
                    'General cargo and commercial freight transport',
                    'Temperature-controlled perishables & medical supplies',
                    'Dangerous goods & heavy equipment logistics',
                    'Dedicated weekly B737-800F freighter schedules',
                ],
                'cta_text' => 'Air Freight Inquiries',
                'cta_link' => '/freight',
                'sort_order' => 2,
            ],
            [
                'title' => 'Private Aircraft Charter – Tailored VIP & Group Flights',
                'slug' => 'aircraft-charter-services',
                'type' => 'charter',
                'icon' => 'plane',
                'image' => 'https://images.unsplash.com/photo-1570125909232-eb263c188f7e?w=800&auto=format&fit=crop&q=80',
                'description' => 'Experience complete flexibility with bespoke aircraft charter solutions for corporate delegations, government missions, sports teams, and emergency Pacific response operations.',
                'features' => [
                    'Flexible flight routing and customized departure schedules',
                    'Dedicated VIP passenger cabin and private ground handling',
                    'Capacity up to 178 passengers or dedicated palletized freight',
                    'Direct non-stop Pacific and Trans-Tasman route capabilities',
                ],
                'cta_text' => 'Request Charter Quote',
                'cta_link' => '/charter',
                'sort_order' => 3,
            ],
            [
                'title' => 'ACMI & Aircraft Wet Lease Solutions',
                'slug' => 'acmi-wet-lease-services',
                'type' => 'acmi',
                'icon' => 'shield',
                'image' => 'https://images.unsplash.com/photo-1520437358207-323b43b50729?w=800&auto=format&fit=crop&q=80',
                'description' => 'Providing fully certified Boeing 737 aircraft, flight crew, maintenance, and insurance (ACMI) capacity to support regional airlines and government transport needs.',
                'features' => [
                    'Complete Aircraft, Crew, Maintenance, and Insurance (ACMI)',
                    'Short-term seasonal capacity and long-term lease arrangements',
                    'Experienced Pacific-certified flight and cabin crew',
                    'Strict regulatory compliance with CASA and international aviation standards',
                ],
                'cta_text' => 'ACMI Partnership',
                'cta_link' => '/acmi',
                'sort_order' => 4,
            ],
        ];

        foreach ($services as $service) {
            AirlineService::updateOrCreate(
                ['slug' => $service['slug']],
                $service
            );
        }
    }
}
