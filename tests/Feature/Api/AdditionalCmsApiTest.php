<?php

namespace Tests\Feature\Api;

use App\Models\AgencyContact;
use App\Models\FlightSchedule;
use App\Models\GalleryItem;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdditionalCmsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Agency
        AgencyContact::create([
            'country' => 'Australia',
            'flag' => '🇦🇺',
            'name' => 'Brisbane PSA Office',
            'address' => 'Level 3, Brisbane Airport, QLD 4008',
            'phones' => ['+61 7 3229 6455'],
            'emails' => ['brisbane@naoeroairlines.com'],
            'website' => 'https://www.naoeroairlines.com',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Seed Schedule
        FlightSchedule::create([
            'title' => 'Pacific Island Timetable 2026',
            'period' => 'April 2026 - October 2026',
            'route' => 'Brisbane - Nauru - Tarawa - Majuro',
            'file_url' => 'https://www.naoeroairlines.com/downloads/timetable.pdf',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Seed Gallery Item
        GalleryItem::create([
            'title' => 'Anibare Bay Sunset',
            'destination' => 'Nauru',
            'category' => 'Destinations',
            'image_url' => 'https://images.unsplash.com/photo-anibare.jpg',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Seed Subscriber
        NewsletterSubscriber::create([
            'email' => 'subscriber@example.com',
            'source' => 'website_footer',
            'is_active' => true,
            'subscribed_at' => now(),
        ]);
    }

    public function test_can_list_agency_contacts()
    {
        $response = $this->getJson('/api/agency-contacts');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['name' => 'Brisbane PSA Office', 'country' => 'Australia']);
    }

    public function test_can_list_flight_schedules_and_routes()
    {
        $response = $this->getJson('/api/flight-schedules');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['title' => 'Pacific Island Timetable 2026']);

        $routes = $this->getJson('/api/flight-schedules/routes');
        $routes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['Brisbane - Nauru - Tarawa - Majuro']);
    }

    public function test_can_list_gallery_and_categories()
    {
        $response = $this->getJson('/api/gallery');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['title' => 'Anibare Bay Sunset']);

        $cats = $this->getJson('/api/gallery/categories');
        $cats->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['Destinations']);
    }

    public function test_can_subscribe_and_unsubscribe_newsletter()
    {
        // Subscribe new email
        $sub = $this->postJson('/api/newsletter/subscribe', [
            'email' => 'newuser@pacific.com',
            'source' => 'flight_deals_page',
        ]);
        $sub->assertStatus(201)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'newuser@pacific.com', 'is_active' => true]);

        // Unsubscribe
        $unsub = $this->postJson('/api/newsletter/unsubscribe', [
            'email' => 'newuser@pacific.com',
        ]);
        $unsub->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'newuser@pacific.com', 'is_active' => false]);
    }

    public function test_can_submit_acmi_and_freight_inquiries()
    {
        // ACMI Inquiry
        $acmi = $this->postJson('/api/acmi-inquiries', [
            'name' => 'Aviation Charters Ltd',
            'company' => 'Pacific Wings ACMI',
            'email' => 'acmi@pacificwings.com',
            'phone' => '+61 400 999 888',
            'aircraftType' => 'Boeing 737-700',
            'duration' => '6 Months Wet Lease',
            'operatingBases' => 'Brisbane / Honiara',
            'message' => 'Seeking wet lease capacity for peak season island hops.',
        ]);
        $acmi->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'acmi', 'email' => 'acmi@pacificwings.com']);

        // Freight Inquiry
        $freight = $this->postJson('/api/freight-inquiries', [
            'name' => 'Cargo Express Pacific',
            'company' => 'Oceania Freight Corp',
            'email' => 'cargo@oceaniafreight.com',
            'phone' => '+61 400 777 666',
            'cargoType' => 'Perishables / General Freight',
            'origin' => 'Brisbane (BNE)',
            'destination' => 'Nauru (INU)',
            'estimatedWeight' => '4500 kg',
            'message' => 'Weekly scheduled cold-chain delivery.',
        ]);
        $freight->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'freight', 'email' => 'cargo@oceaniafreight.com']);
    }

    public function test_api_auth_login_me_logout()
    {
        $role = Role::create(['name' => 'Operations Manager']);
        $user = User::create([
            'name' => 'Captain Sarah',
            'email' => 'sarah@naoeroairlines.com',
            'password' => Hash::make('Secret123!'),
            'status' => 'active',
            'job_title' => 'Chief Pilot',
            'department' => 'Flight Operations',
        ]);
        $user->assignRole($role);

        // 1. Login
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'sarah@naoeroairlines.com',
            'password' => 'Secret123!',
        ]);

        $loginRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'name', 'role']]);

        $token = $loginRes->json('token');

        // 2. Get Me
        $meRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/auth/me');

        $meRes->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['email' => 'sarah@naoeroairlines.com', 'name' => 'Captain Sarah']);

        // 3. Logout
        $logoutRes = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $logoutRes->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
