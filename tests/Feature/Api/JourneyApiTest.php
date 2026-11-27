<?php

namespace Tests\Feature\Api;

use App\Models\EnhanceJourneyItem;
use App\Models\OurJourneyMilestone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JourneyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        OurJourneyMilestone::create([
            'year' => '1970',
            'title' => 'Inception & First Flight',
            'description' => 'Air Nauru commences commercial operations connecting Nauru to Brisbane.',
            'image' => '/assets/about/ourjourney1.png',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        OurJourneyMilestone::create([
            'year' => '1985',
            'title' => 'Expanding Across the Pacific',
            'description' => 'Operating Boeing 737 and 727 jet aircraft connecting Micronesia and Melanesia.',
            'image' => '/assets/about/ourjourney2.png',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        OurJourneyMilestone::create([
            'year' => '1999',
            'title' => 'Inactive Milestone',
            'description' => 'Hidden milestone test.',
            'sort_order' => 99,
            'is_active' => false,
        ]);

        EnhanceJourneyItem::create([
            'title' => 'Hotels & Resorts',
            'description' => 'Discover relaxing beachfront stays in Nauru, Fiji, and Kiribati.',
            'icon' => 'Hotel',
            'image' => '/assets/home/hotel.png',
            'link_url' => '/hotels',
            'link_text' => 'Know More',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        EnhanceJourneyItem::create([
            'title' => 'Rental Car',
            'description' => 'Explore the Pacific islands at your own leisure with trusted partners.',
            'icon' => 'Car',
            'image' => '/assets/home/car.png',
            'link_url' => '/rental-cars',
            'link_text' => 'Know More',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        EnhanceJourneyItem::create([
            'title' => 'Inactive Enhance Card',
            'description' => 'Should be hidden from public API.',
            'icon' => 'Compass',
            'sort_order' => 99,
            'is_active' => false,
        ]);
    }

    public function test_can_get_our_journey_milestones_api(): void
    {
        $response = $this->getJson('/api/our-journey');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'heading' => 'Our Journey',
                'count' => 2,
            ]);

        $json = $response->json();
        $this->assertCount(2, $json['data']);
        $this->assertEquals('1970', $json['data'][0]['year']);
        $this->assertEquals('Inception & First Flight', $json['data'][0]['title']);
        $this->assertEquals('1985', $json['data'][1]['year']);
    }

    public function test_can_get_enhance_journey_cards_api(): void
    {
        $response = $this->getJson('/api/enhance-journey');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'heading' => 'Enhance Your Journey',
                'count' => 2,
            ]);

        $json = $response->json();
        $this->assertCount(2, $json['data']);
        $this->assertEquals('Hotels & Resorts', $json['data'][0]['title']);
        $this->assertEquals('Hotel', $json['data'][0]['icon']);
        $this->assertEquals('Rental Car', $json['data'][1]['title']);
    }

    public function test_can_get_journey_all_composite_api(): void
    {
        $response = $this->getJson('/api/journey/all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'our_journey' => [
                    'count' => 2,
                ],
                'enhance_journey' => [
                    'count' => 2,
                ],
            ]);

        $json = $response->json();
        $this->assertCount(2, $json['our_journey']['milestones']);
        $this->assertCount(2, $json['enhance_journey']['items']);
    }

    public function test_global_settings_includes_journey_and_enhance(): void
    {
        $response = $this->getJson('/api/global');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('enhance_journey', $json['data']);
        $this->assertArrayHasKey('our_journey', $json['data']);
        $this->assertCount(2, $json['data']['enhance_journey']['items']);
        $this->assertCount(2, $json['data']['our_journey']['milestones']);
    }
}
