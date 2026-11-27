<?php

namespace Tests\Feature\Api;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSettingsApiTest extends TestCase
{
    use RefreshDatabase;
    public function test_global_settings_api_returns_logo_top_header_text_and_menu(): void
    {
        SiteSetting::updateOrCreate(
            ['key' => 'site_logo'],
            ['value' => '/assets/logo.png', 'group' => 'branding', 'label' => 'Logo']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'top_header_text'],
            ['value' => 'Welcome to Naoero Airline', 'group' => 'header', 'label' => 'Header Text']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'top_header_menu'],
            [
                'value' => json_encode([
                    ['label' => 'My Booking', 'route' => 'https://book.naoeroairlines.com/manage', 'icon' => 'Ticket', 'is_external' => true],
                    ['label' => 'Flight Status', 'route' => '/travel-info/flight-schedule', 'icon' => 'PlaneTakeoff', 'is_external' => false],
                    ['label' => 'Contact Us', 'route' => '/contact-us', 'icon' => 'Phone', 'is_external' => false],
                ]),
                'group' => 'header',
                'label' => 'Menu'
            ]
        );

        $response = $this->getJson('/api/global');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'top_header_text' => 'Welcome to Naoero Airline',
                    'welcome_text' => 'Welcome to Naoero Airline',
                ],
            ]);

        $json = $response->json();
        $this->assertArrayHasKey('logo', $json['data']);
        $this->assertArrayHasKey('menu', $json['data']);
        $this->assertCount(3, $json['data']['menu']);
        $this->assertEquals('My Booking', $json['data']['menu'][0]['label']);
        $this->assertEquals('Flight Status', $json['data']['menu'][1]['label']);
        $this->assertEquals('Contact Us', $json['data']['menu'][2]['label']);
    }

    public function test_settings_and_header_alias_endpoints(): void
    {
        $responseSettings = $this->getJson('/api/settings');
        $responseSettings->assertStatus(200)->assertJson(['success' => true]);

        $responseHeader = $this->getJson('/api/header');
        $responseHeader->assertStatus(200)->assertJson(['success' => true]);
    }
}
