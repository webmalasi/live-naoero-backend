<?php

namespace Tests\Feature\Api;

use App\Models\WhyFlyFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhyFlyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WhyFlyFeature::create([
            'title' => 'Trusted Routes',
            'description' => 'The official national carrier connecting the Pacific for over 50 years.',
            'icon' => 'Plane',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        WhyFlyFeature::create([
            'title' => 'No Hidden Fees',
            'description' => 'Your ticket always includes checked baggage with zero surprise costs.',
            'icon' => 'Package',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        WhyFlyFeature::create([
            'title' => 'Inactive Card',
            'description' => 'Should be hidden from public API.',
            'icon' => 'ShieldCheck',
            'sort_order' => 99,
            'is_active' => false,
        ]);
    }

    public function test_can_list_all_active_why_fly_cards(): void
    {
        $response = $this->getJson('/api/why-fly');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
                'heading' => 'Why Fly with us',
            ]);

        $json = $response->json();
        $this->assertCount(2, $json['data']);
        $this->assertEquals('Trusted Routes', $json['data'][0]['title']);
        $this->assertEquals('Plane', $json['data'][0]['icon']);
        $this->assertEquals('No Hidden Fees', $json['data'][1]['title']);
    }

    public function test_can_access_why_fly_with_us_alias_endpoint(): void
    {
        $response = $this->getJson('/api/why-fly-with-us');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    public function test_can_get_single_why_fly_card(): void
    {
        $feature = WhyFlyFeature::where('title', 'Trusted Routes')->first();

        $response = $this->getJson("/api/why-fly/{$feature->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $feature->id,
                    'title' => 'Trusted Routes',
                    'icon' => 'Plane',
                ],
            ]);
    }

    public function test_global_settings_includes_why_fly_cards(): void
    {
        $response = $this->getJson('/api/global');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('why_fly', $json['data']);
        $this->assertArrayHasKey('why_fly_with_us', $json['data']);
        $this->assertCount(2, $json['data']['why_fly']['features']);
    }
}
