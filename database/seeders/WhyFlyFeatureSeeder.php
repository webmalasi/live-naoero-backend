<?php

namespace Database\Seeders;

use App\Models\WhyFlyFeature;
use Illuminate\Database\Seeder;

class WhyFlyFeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'title' => 'Trusted Routes',
                'description' => 'The official national carrier connecting the Pacific for over 50 years.',
                'icon' => 'Plane',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'No Hidden Fees',
                'description' => 'Your ticket always includes checked baggage with zero surprise costs.',
                'icon' => 'Package',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Full Hospitality',
                'description' => 'Every flight includes complimentary hot meals and premium onboard care.',
                'icon' => 'UtensilsCrossed',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Certified Safety',
                'description' => 'Operating a modern, fully certified Boeing fleet with elite safety standards.',
                'icon' => 'ShieldCheck',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($features as $feature) {
            WhyFlyFeature::updateOrCreate(
                ['title' => $feature['title']],
                $feature
            );
        }
    }
}
