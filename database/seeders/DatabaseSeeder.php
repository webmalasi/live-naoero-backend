<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            CountryStateCitySeeder::class,
            DropdownOptionSeeder::class,
            DestinationSeeder::class,
            CMSSeeder::class,
            AdditionalCMSSeeder::class,
            ShopSeeder::class,
            PageBannerSeeder::class,
            AirlineServiceSeeder::class,
            WhyFlyFeatureSeeder::class,
            JourneySeeder::class,
            LegalAndTravelInfoSeeder::class,
        ]);
    }
}
