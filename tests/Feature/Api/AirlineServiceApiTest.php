<?php

namespace Tests\Feature\Api;

use App\Models\AirlineService;
use App\Models\PageBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirlineServiceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PageBanner::create([
            'page_key' => 'services',
            'page_name' => 'Services Hub',
            'page_route' => '/services',
            'banner_badge_text' => 'Aviation Solutions',
            'banner_image' => 'https://images.unsplash.com/photo-services.jpg',
            'banner_heading' => 'Comprehensive Pacific Aviation & Cargo Services',
            'banner_description' => 'From regular scheduled passenger flights to specialized cargo and ACMI leasing.',
            'banner_button_text' => 'Contact Us',
            'banner_button_link' => '/contact-us',
            'is_active' => true,
        ]);

        AirlineService::create([
            'title' => 'Passenger Services – Comfort Above the Clouds',
            'slug' => 'passenger-services',
            'type' => 'passenger',
            'icon' => 'bottleglass',
            'image' => 'https://images.unsplash.com/photo-pax.jpg',
            'description' => 'Delivering exceptional inflight service aboard our Boeing 737 aircraft.',
            'features' => ['Complimentary meals', 'Generous baggage allowance'],
            'cta_text' => 'Book Flights',
            'cta_link' => 'https://book.naoeroairlines.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        AirlineService::create([
            'title' => 'Cargo & Freight Services',
            'slug' => 'cargo-freight-services',
            'type' => 'cargo',
            'icon' => 'cargofreight',
            'image' => 'https://images.unsplash.com/photo-cargo.jpg',
            'description' => 'Timely freight solutions across Australia, Micronesia, and the South Pacific.',
            'features' => ['General cargo', 'Temperature-controlled perishables'],
            'cta_text' => 'Freight Inquiries',
            'cta_link' => '/freight',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        AirlineService::create([
            'title' => 'Inactive Service',
            'slug' => 'inactive-service',
            'type' => 'special',
            'description' => 'This is inactive and should not show in public API.',
            'is_active' => false,
            'sort_order' => 99,
        ]);
    }

    public function test_can_list_all_active_airline_services_with_banner_and_quicklinks(): void
    {
        $response = $this->getJson('/api/services');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $json = $response->json();

        $this->assertArrayHasKey('banner', $json);
        $this->assertEquals('Comprehensive Pacific Aviation & Cargo Services', $json['banner']['heading']);

        $this->assertArrayHasKey('data', $json);
        $this->assertCount(2, $json['data']);
        $this->assertEquals('passenger-services', $json['data'][0]['slug']);
        $this->assertEquals('cargo-freight-services', $json['data'][1]['slug']);

        $this->assertArrayHasKey('quick_links', $json);
        $this->assertNotEmpty($json['quick_links']);
    }

    public function test_can_filter_airline_services_by_type(): void
    {
        $response = $this->getJson('/api/services?type=cargo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 1,
            ]);

        $json = $response->json();
        $this->assertEquals('cargo-freight-services', $json['data'][0]['slug']);
        $this->assertEquals('cargo', $json['data'][0]['type']);
    }

    public function test_can_get_single_service_by_slug(): void
    {
        $response = $this->getJson('/api/services/passenger-services');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'passenger-services',
                    'type' => 'passenger',
                    'icon' => 'bottleglass',
                    'cta_text' => 'Book Flights',
                ],
            ]);
    }

    public function test_returns_404_for_nonexistent_service(): void
    {
        $response = $this->getJson('/api/services/non-existent-service');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }
}
