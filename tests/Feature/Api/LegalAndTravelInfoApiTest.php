<?php

namespace Tests\Feature\Api;

use App\Models\AgencyResource;
use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\LegalDocument;
use App\Models\TravelInfoCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalAndTravelInfoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Legal Documents
        LegalDocument::create([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'heading' => 'Privacy Policy',
            'subheading' => 'Naoero Airlines is committed to protecting your personal information.',
            'badge_text' => 'Updated 2026',
            'contact_email' => 'privacy@naoeroairlines.com.au',
            'summary' => 'Naoero Airlines is committed to protecting your privacy.',
            'sections' => [
                [
                    'heading' => 'Information We Collect',
                    'content' => 'We collect personal information necessary for bookings.',
                ],
            ],
            'is_active' => true,
        ]);

        LegalDocument::create([
            'slug' => 'terms-of-use',
            'title' => 'Terms of Use',
            'heading' => 'Website Terms of Use',
            'subheading' => 'Please review the following website terms carefully.',
            'badge_text' => 'Terms & Conditions',
            'contact_email' => 'privacy@naoeroairlines.com.au',
            'summary' => 'Terms governing the access and usage of Naoero Airlines websites.',
            'sections' => [
                [
                    'heading' => 'Acceptance of Terms',
                    'content' => 'By accessing this website you agree to be bound by these terms.',
                ],
            ],
            'is_active' => true,
        ]);

        LegalDocument::create([
            'slug' => 'conditions-of-carriage',
            'title' => 'Conditions of Carriage',
            'heading' => 'Passenger Conditions of Carriage',
            'subheading' => 'Contractual legal terms, passenger rights, and baggage rules.',
            'badge_text' => 'Passenger Rights & Contract',
            'contact_email' => 'reservations@naoeroairlines.com.au',
            'summary' => 'Terms governing the carriage of passengers and baggage on Naoero Airlines.',
            'sections' => [
                [
                    'heading' => 'Article 1 - Definitions',
                    'content' => 'As you read these conditions, please note carrier definitions.',
                ],
            ],
            'is_active' => true,
        ]);

        // 2. Agency Resources
        AgencyResource::create([
            'title' => 'Name Change Policy',
            'slug' => 'name-change-policy',
            'description' => 'Guidelines on passenger name corrections and transfers.',
            'category' => 'Ticketing & Fares',
            'icon' => 'FileText',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        AgencyResource::create([
            'title' => 'Schedule Change Policy',
            'slug' => 'schedule-change-policy',
            'description' => 'Procedures for rebooking passengers affected by schedule adjustments.',
            'category' => 'Flight Operations',
            'icon' => 'FileSpreadsheet',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // 3. Check-In Times
        CheckInTime::create([
            'port' => 'Brisbane (BNE)',
            'airport_code' => 'BNE',
            'opens' => '3 hours prior to departure',
            'closes' => '60 minutes prior to departure',
            'is_highlighted' => false,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        CheckInTime::create([
            'port' => 'Nauru (INU)',
            'airport_code' => 'INU',
            'opens' => '3 hours prior to departure',
            'closes' => '45 minutes prior to departure',
            'is_highlighted' => true,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // 4. Dangerous Goods Items
        DangerousGoodsItem::create([
            'item_name' => 'Lithium Ion Batteries (< 100Wh)',
            'carry_on' => 'Yes (In equipment & spares)',
            'checked' => 'In equipment only (NO spares)',
            'approval_required' => 'No',
            'category' => 'quick_reference',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        DangerousGoodsItem::create([
            'item_name' => 'Smart Luggage with Lithium Batteries',
            'details' => 'If power bank / battery is removable, it must be removed and kept in cabin.',
            'carry_on' => 'Yes (if within cabin bag limits)',
            'checked' => 'Only if battery removed',
            'approval_required' => 'No',
            'category' => 'conditional',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // 5. Travel Info Cards
        TravelInfoCard::create([
            'title' => 'Baggage Allowances',
            'slug' => 'baggage-allowances',
            'description' => 'Cabin and checked luggage weight allowances, fees, and excess baggage.',
            'icon' => 'Briefcase',
            'route_url' => '/travel-info/baggage',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_can_fetch_privacy_policy_via_multiple_endpoints(): void
    {
        $res1 = $this->getJson('/api/privacy-policy');
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'privacy-policy',
                    'title' => 'Privacy Policy',
                ],
            ]);

        $res2 = $this->getJson('/api/privacy');
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'privacy-policy',
                ],
            ]);
    }

    public function test_can_fetch_terms_of_use_via_multiple_endpoints(): void
    {
        $res1 = $this->getJson('/api/terms-of-use');
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'terms-of-use',
                ],
            ]);

        $res2 = $this->getJson('/api/terms');
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'terms-of-use',
                ],
            ]);

        $res3 = $this->getJson('/api/terms-and-conditions');
        $res3->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'terms-of-use',
                ],
            ]);
    }

    public function test_can_fetch_conditions_of_carriage(): void
    {
        $res1 = $this->getJson('/api/conditions-of-carriage');
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'conditions-of-carriage',
                    'title' => 'Conditions of Carriage',
                ],
            ]);

        $res2 = $this->getJson('/api/travel-info/conditions-of-carriage');
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'conditions-of-carriage',
                ],
            ]);
    }

    public function test_can_fetch_agency_resources(): void
    {
        $response = $this->getJson('/api/agency-resources');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $this->assertEquals('Name Change Policy', $response->json('data.0.title'));

        $single = $this->getJson('/api/agency-resources/name-change-policy');
        $single->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'name-change-policy',
                ],
            ]);
    }

    public function test_can_fetch_travel_info_overview_and_cards(): void
    {
        $response = $this->getJson('/api/travel-info');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'helpful_information' => [
                    'count' => 1,
                ],
            ]);

        $alias = $this->getJson('/api/travel-info/helpful-information');
        $alias->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_fetch_check_in_times(): void
    {
        $res1 = $this->getJson('/api/travel-info/check-in-times');
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $res2 = $this->getJson('/api/check-in-times');
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);

        $res3 = $this->getJson('/api/travel-info/before-you-fly/check-in-times');
        $res3->assertStatus(200)
            ->assertJson([
                'success' => true,
                'count' => 2,
            ]);
    }

    public function test_can_fetch_dangerous_goods(): void
    {
        $res1 = $this->getJson('/api/travel-info/dangerous-goods');
        $res1->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $json = $res1->json();
        $this->assertCount(1, $json['quick_reference']);
        $this->assertCount(1, $json['items_accepted_with_conditions']);

        $res2 = $this->getJson('/api/dangerous-goods');
        $res2->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_can_fetch_before_you_fly_directory(): void
    {
        $response = $this->getJson('/api/travel-info/before-you-fly');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertGreaterThanOrEqual(5, $response->json('count'));
    }
}
