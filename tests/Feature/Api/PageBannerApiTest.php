<?php

namespace Tests\Feature\Api;

use App\Models\PageBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PageBanner::create([
            'page_key' => 'destinations',
            'page_name' => 'Destinations Hub',
            'page_route' => '/destinations',
            'banner_badge_text' => 'Pacific Network',
            'banner_image' => 'https://images.unsplash.com/photo-destinations.jpg',
            'banner_heading' => 'Explore Our Pacific Destinations',
            'banner_description' => 'Discover the pristine islands of the Pacific.',
            'banner_button_text' => 'Explore Routes',
            'banner_button_link' => '/travel-info/flight-schedule',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PageBanner::create([
            'page_key' => 'our-fleet',
            'page_name' => 'Aircraft Fleet',
            'page_route' => '/our-fleet',
            'banner_badge_text' => 'Boeing Fleet',
            'banner_image' => 'https://images.unsplash.com/photo-fleet.jpg',
            'banner_heading' => 'Our Modern Aircraft Fleet',
            'banner_description' => 'Boeing 737 passenger and freighter aircraft.',
            'banner_button_text' => 'View Fleet Specs',
            'banner_button_link' => '/our-fleet',
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }

    public function test_can_list_all_page_banners(): void
    {
        $response = $this->getJson('/api/banners');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('banners_by_page', $json);
        $this->assertArrayHasKey('destinations', $json['banners_by_page']);
        $this->assertArrayHasKey('our-fleet', $json['banners_by_page']);
        $this->assertEquals('Explore Our Pacific Destinations', $json['banners_by_page']['destinations']['banner_heading']);
    }

    public function test_can_get_single_banner_by_slug(): void
    {
        $response = $this->getJson('/api/banners/destinations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'page_key' => 'destinations',
                    'banner_heading' => 'Explore Our Pacific Destinations',
                    'banner_button_text' => 'Explore Routes',
                    'banner_button_link' => '/travel-info/flight-schedule',
                ],
            ]);
    }

    public function test_page_banners_alias_endpoint(): void
    {
        $response = $this->getJson('/api/page-banners');
        $response->assertStatus(200)->assertJson(['success' => true]);

        $responseSingle = $this->getJson('/api/page-banners/our-fleet');
        $responseSingle->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'page_key' => 'our-fleet',
                    'banner_heading' => 'Our Modern Aircraft Fleet',
                ],
            ]);
    }
}
