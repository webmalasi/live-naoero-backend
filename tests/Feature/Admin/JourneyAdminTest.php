<?php

namespace Tests\Feature\Admin;

use App\Models\EnhanceJourneyItem;
use App\Models\OurJourneyMilestone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JourneyAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('Password123!'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_admin_can_view_journey_hub_index_page(): void
    {
        OurJourneyMilestone::create([
            'year' => '1970',
            'title' => 'Inception',
            'description' => 'First commercial flight.',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        EnhanceJourneyItem::create([
            'title' => 'Hotels & Resorts',
            'description' => 'Island accommodations.',
            'icon' => 'Hotel',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/journey');
        $response->assertStatus(200)
            ->assertSeeText('Journey & Travel Essentials Hub')
            ->assertSeeText('Inception')
            ->assertSeeText('Hotels & Resorts');
    }

    public function test_admin_can_create_new_milestone(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('milestone_1990.jpg', 600, 400);

        $payload = [
            'year' => '1990',
            'title' => 'Direct Services to Australia',
            'description' => 'Expanding direct flights connecting Brisbane and Melbourne.',
            'sort_order' => 3,
            'is_active' => '1',
            'image_file' => $photo,
        ];

        $response = $this->actingAs($this->admin)->post('/admin/journey/milestones', $payload);

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseHas('our_journey_milestones', [
            'year' => '1990',
            'title' => 'Direct Services to Australia',
            'is_active' => true,
        ]);

        $milestone = OurJourneyMilestone::where('year', '1990')->first();
        $this->assertNotNull($milestone->image);
    }

    public function test_admin_can_update_milestone(): void
    {
        $milestone = OurJourneyMilestone::create([
            'year' => '1985',
            'title' => 'Expanding Across the Pacific',
            'description' => 'Original description.',
            'is_active' => true,
        ]);

        $payload = [
            'year' => '1985',
            'title' => 'Updated Pacific Fleet Expansion',
            'description' => 'Updated fleet description.',
            'sort_order' => 2,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put("/admin/journey/milestones/{$milestone->id}", $payload);

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseHas('our_journey_milestones', [
            'id' => $milestone->id,
            'title' => 'Updated Pacific Fleet Expansion',
        ]);
    }

    public function test_admin_can_toggle_milestone_active_status(): void
    {
        $milestone = OurJourneyMilestone::create([
            'year' => '2006',
            'title' => 'Rebranding',
            'description' => 'Rebranding era.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/journey/milestones/{$milestone->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('our_journey_milestones', [
            'id' => $milestone->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_milestone(): void
    {
        $milestone = OurJourneyMilestone::create([
            'year' => '2000',
            'title' => 'Delete Test',
            'description' => 'Delete description.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/journey/milestones/{$milestone->id}");

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseMissing('our_journey_milestones', [
            'id' => $milestone->id,
        ]);
    }

    public function test_admin_can_create_new_enhance_card(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('activities.jpg', 600, 400);

        $payload = [
            'title' => 'Island Activities & Tours',
            'description' => 'Book scuba diving and cultural heritage tours.',
            'icon' => 'Compass',
            'link_url' => '/tours',
            'link_text' => 'Explore Tours',
            'sort_order' => 4,
            'is_active' => '1',
            'image_file' => $photo,
        ];

        $response = $this->actingAs($this->admin)->post('/admin/journey/enhance', $payload);

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseHas('enhance_journey_items', [
            'title' => 'Island Activities & Tours',
            'icon' => 'Compass',
            'is_active' => true,
        ]);

        $card = EnhanceJourneyItem::where('title', 'Island Activities & Tours')->first();
        $this->assertNotNull($card->image);
    }

    public function test_admin_can_update_enhance_card(): void
    {
        $card = EnhanceJourneyItem::create([
            'title' => 'Rental Car',
            'description' => 'Original car description.',
            'icon' => 'Car',
            'is_active' => true,
        ]);

        $payload = [
            'title' => 'Updated Vehicle Rental',
            'description' => 'Drive through Pacific islands comfortably.',
            'icon' => 'Car',
            'sort_order' => 2,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put("/admin/journey/enhance/{$card->id}", $payload);

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseHas('enhance_journey_items', [
            'id' => $card->id,
            'title' => 'Updated Vehicle Rental',
        ]);
    }

    public function test_admin_can_toggle_enhance_active_status(): void
    {
        $card = EnhanceJourneyItem::create([
            'title' => 'Travel Insurance',
            'description' => 'Stay protected.',
            'icon' => 'ShieldCheck',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/journey/enhance/{$card->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('enhance_journey_items', [
            'id' => $card->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_delete_enhance_card(): void
    {
        $card = EnhanceJourneyItem::create([
            'title' => 'Delete Enhance Card',
            'description' => 'Delete card.',
            'icon' => 'ShieldCheck',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->delete("/admin/journey/enhance/{$card->id}");

        $response->assertRedirect('/admin/journey');
        $this->assertDatabaseMissing('enhance_journey_items', [
            'id' => $card->id,
        ]);
    }

    public function test_admin_can_update_journey_section_headings(): void
    {
        $payload = [
            'our_journey_heading' => 'Five Decades of Pacific Aviation',
            'our_journey_subheading' => 'Proud history connecting Micronesia and Australia.',
            'enhance_journey_heading' => 'Complete Your Travel Experience',
            'enhance_journey_subheading' => 'Add hotels, cars, and insurance to your flight.',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/journey/update-headings', $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('site_settings', [
            'key' => 'our_journey_heading',
            'value' => 'Five Decades of Pacific Aviation',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => 'enhance_journey_heading',
            'value' => 'Complete Your Travel Experience',
        ]);
    }
}
