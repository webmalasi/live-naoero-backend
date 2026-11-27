<?php

namespace Tests\Feature\Admin;

use App\Models\AgencyResource;
use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\LegalDocument;
use App\Models\TravelInfoCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LegalAndTravelInfoAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@naoeroairlines.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_admin_can_view_agency_resources_index(): void
    {
        AgencyResource::create([
            'title' => 'Refund Policy',
            'slug' => 'refund-policy',
            'description' => 'Guidelines for ticket refunds and cancellations.',
            'category' => 'Ticketing & Fares',
            'icon' => 'FileText',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/agency-resources');
        $response->assertStatus(200)
            ->assertSee('Refund Policy')
            ->assertSee('Agency Resources');
    }

    public function test_admin_can_create_agency_resource(): void
    {
        $payload = [
            'title' => 'GDS Booking Guideline',
            'category' => 'Ticketing & Fares',
            'description' => 'Detailed booking procedures for agents using Amadeus and Sabre.',
            'external_url' => 'https://example.com/gds-guide',
            'icon' => 'FileText',
            'sort_order' => 5,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/agency-resources', $payload);
        $response->assertRedirect('/admin/agency-resources');

        $this->assertDatabaseHas('agency_resources', [
            'title' => 'GDS Booking Guideline',
            'category' => 'Ticketing & Fares',
        ]);
    }

    public function test_admin_can_toggle_agency_resource_active_status(): void
    {
        $item = AgencyResource::create([
            'title' => 'Waiver Policy',
            'slug' => 'waiver-policy',
            'description' => 'Waiver codes for travel disruptions.',
            'category' => 'Waivers & Disruptions',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)
            ->patchJson("/admin/agency-resources/{$item->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_active' => false,
            ]);

        $this->assertDatabaseHas('agency_resources', [
            'id' => $item->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_view_travel_info_hub(): void
    {
        CheckInTime::create([
            'port' => 'Sydney (SYD)',
            'airport_code' => 'SYD',
            'opens' => '3 hours prior',
            'closes' => '60 mins prior',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        DangerousGoodsItem::create([
            'item_name' => 'Aerosols',
            'carry_on' => 'Limited',
            'checked' => 'Yes',
            'approval_required' => 'No',
            'category' => 'quick_reference',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/travel-info');
        $response->assertStatus(200)
            ->assertSee('Travel Info & Operations Hub', false)
            ->assertSee('Sydney (SYD)')
            ->assertSee('Aerosols');
    }

    public function test_admin_can_create_check_in_time(): void
    {
        $payload = [
            'port' => 'Honiara (HIR)',
            'airport_code' => 'HIR',
            'opens' => '3 hours prior to departure',
            'closes' => '45 minutes prior to departure',
            'is_highlighted' => '1',
            'sort_order' => 10,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/travel-info/check-in', $payload);
        $response->assertRedirect('/admin/travel-info');

        $this->assertDatabaseHas('check_in_times', [
            'airport_code' => 'HIR',
            'is_highlighted' => true,
        ]);
    }

    public function test_admin_can_create_dangerous_goods_item(): void
    {
        $payload = [
            'item_name' => 'Dry Ice (Carbon Dioxide)',
            'category' => 'conditional',
            'details' => 'Up to 2.5 kg per person when used to pack perishables.',
            'carry_on' => 'Yes (max 2.5kg)',
            'checked' => 'Yes (max 2.5kg)',
            'approval_required' => 'Airline Approval Required',
            'icon' => 'Snowflake',
            'sort_order' => 4,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/travel-info/dangerous-goods', $payload);
        $response->assertRedirect('/admin/travel-info');

        $this->assertDatabaseHas('dangerous_goods_items', [
            'item_name' => 'Dry Ice (Carbon Dioxide)',
            'category' => 'conditional',
        ]);
    }

    public function test_admin_can_create_travel_info_card(): void
    {
        $payload = [
            'title' => 'In-Flight Dining',
            'description' => 'Complimentary Pacific hot meals and drinks on all routes.',
            'icon' => 'Utensils',
            'route_url' => '/on-board/dining',
            'has_link' => '1',
            'sort_order' => 2,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/travel-info/cards', $payload);
        $response->assertRedirect('/admin/travel-info');

        $this->assertDatabaseHas('travel_info_cards', [
            'title' => 'In-Flight Dining',
        ]);
    }

    public function test_admin_can_view_legal_documents_index_and_edit(): void
    {
        $doc = LegalDocument::create([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'heading' => 'Privacy Policy',
            'subheading' => 'Information privacy guidelines',
            'badge_text' => 'Official Policy',
            'contact_email' => 'privacy@naoeroairlines.com.au',
            'summary' => 'Our privacy guidelines summary.',
            'sections' => [
                [
                    'heading' => 'Data Collection',
                    'content' => 'Details about collected data.',
                ],
            ],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/legal-documents');
        $response->assertStatus(200)
            ->assertSee('Legal &amp; Policy Documents', false)
            ->assertSee('Privacy Policy');

        $editRes = $this->actingAs($this->admin)->get("/admin/legal-documents/{$doc->id}/edit");
        $editRes->assertStatus(200)
            ->assertSee('Edit Legal Document')
            ->assertSee('Privacy Policy');

        $updatePayload = [
            'title' => 'Privacy Policy (Updated)',
            'heading' => 'Official Privacy Policy',
            'subheading' => 'Updated privacy policy text',
            'badge_text' => 'Updated 2026',
            'contact_email' => 'dpo@naoeroairlines.com.au',
            'summary' => 'Updated privacy policy summary',
            'content' => 'Full legal text here.',
            'sections' => [
                [
                    'heading' => 'Updated Section 1',
                    'content' => 'Updated content for section 1.',
                ],
            ],
            'is_active' => '1',
        ];

        $updateRes = $this->actingAs($this->admin)->put("/admin/legal-documents/{$doc->id}", $updatePayload);
        $updateRes->assertRedirect('/admin/legal-documents');

        $this->assertDatabaseHas('legal_documents', [
            'id' => $doc->id,
            'title' => 'Privacy Policy (Updated)',
            'contact_email' => 'dpo@naoeroairlines.com.au',
        ]);
    }
}
