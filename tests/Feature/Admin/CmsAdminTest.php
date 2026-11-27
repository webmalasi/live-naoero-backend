<?php

namespace Tests\Feature\Admin;

use App\Models\Career;
use App\Models\Faq;
use App\Models\Fleet;
use App\Models\Inquiry;
use App\Models\JobApplication;
use App\Models\Notice;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\SpecialOffer;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_access_fleet_crud()
    {
        // Index
        $response = $this->actingAs($this->admin)->get(route('admin.fleets.index'));
        $response->assertStatus(200);

        // Create form
        $create = $this->actingAs($this->admin)->get(route('admin.fleets.create'));
        $create->assertStatus(200);

        // Store
        $store = $this->actingAs($this->admin)->post(route('admin.fleets.store'), [
            'type' => 'Boeing 737-300',
            'registration' => 'VH-NUE',
            'category' => 'passenger',
            'seating' => '130 Seats',
            'configuration' => 'Standard',
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.fleets.index'));
        $this->assertDatabaseHas('fleets', ['registration' => 'VH-NUE']);

        // Edit
        $fleet = Fleet::where('registration', 'VH-NUE')->first();
        $edit = $this->actingAs($this->admin)->get(route('admin.fleets.edit', $fleet));
        $edit->assertStatus(200);

        // Update
        $update = $this->actingAs($this->admin)->put(route('admin.fleets.update', $fleet), [
            'type' => 'Boeing 737-300 Updated',
            'registration' => 'VH-NUE',
            'category' => 'passenger',
            'is_active' => true,
        ]);
        $update->assertRedirect(route('admin.fleets.index'));
        $this->assertDatabaseHas('fleets', ['type' => 'Boeing 737-300 Updated']);

        // Toggle active
        $toggle = $this->actingAs($this->admin)->patch(route('admin.fleets.toggle-active', $fleet));
        $toggle->assertStatus(302);
        $this->assertDatabaseHas('fleets', ['registration' => 'VH-NUE', 'is_active' => false]);

        // Destroy
        $destroy = $this->actingAs($this->admin)->delete(route('admin.fleets.destroy', $fleet));
        $destroy->assertRedirect(route('admin.fleets.index'));
        $this->assertDatabaseMissing('fleets', ['registration' => 'VH-NUE']);
    }

    public function test_admin_can_access_posts_crud()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.posts.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.posts.store'), [
            'title' => 'Admin Test Post',
            'type' => 'news',
            'category' => 'News & Updates',
            'excerpt' => 'Test excerpt',
            'content' => 'Test content',
            'is_published' => true,
        ]);
        $store->assertRedirect(route('admin.posts.index'));
        $this->assertDatabaseHas('posts', ['title' => 'Admin Test Post']);

        $post = Post::where('title', 'Admin Test Post')->first();
        $edit = $this->actingAs($this->admin)->get(route('admin.posts.edit', $post));
        $edit->assertStatus(200);

        $destroy = $this->actingAs($this->admin)->delete(route('admin.posts.destroy', $post));
        $destroy->assertRedirect(route('admin.posts.index'));
        $this->assertDatabaseMissing('posts', ['title' => 'Admin Test Post']);
    }

    public function test_admin_can_access_testimonials_crud()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.testimonials.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.testimonials.store'), [
            'name' => 'John Passenger',
            'location' => 'Nauru',
            'quote' => 'Great flight!',
            'rating' => 5,
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.testimonials.index'));
        $this->assertDatabaseHas('testimonials', ['name' => 'John Passenger']);

        $testimonial = Testimonial::where('name', 'John Passenger')->first();
        $destroy = $this->actingAs($this->admin)->delete(route('admin.testimonials.destroy', $testimonial));
        $destroy->assertRedirect(route('admin.testimonials.index'));
        $this->assertDatabaseMissing('testimonials', ['name' => 'John Passenger']);
    }

    public function test_admin_can_access_team_crud()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.team-members.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.team-members.store'), [
            'name' => 'Captain Sarah',
            'role' => 'Chief Pilot',
            'department' => 'Flight Operations',
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.team-members.index'));
        $this->assertDatabaseHas('team_members', ['name' => 'Captain Sarah']);

        $member = TeamMember::where('name', 'Captain Sarah')->first();
        $destroy = $this->actingAs($this->admin)->delete(route('admin.team-members.destroy', $member));
        $destroy->assertRedirect(route('admin.team-members.index'));
        $this->assertDatabaseMissing('team_members', ['name' => 'Captain Sarah']);
    }

    public function test_admin_can_access_careers_and_applications()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.careers.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.careers.store'), [
            'title' => 'Flight Dispatcher',
            'location' => 'Brisbane',
            'employment_type' => 'Full-time',
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.careers.index'));
        $this->assertDatabaseHas('careers', ['title' => 'Flight Dispatcher']);

        $career = Career::where('title', 'Flight Dispatcher')->first();

        // Create dummy application
        $app = JobApplication::create([
            'career_id' => $career->id,
            'first_name' => 'Applicant',
            'last_name' => 'Tom',
            'email' => 'tom@app.com',
            'status' => 'pending',
        ]);

        $appIndex = $this->actingAs($this->admin)->get(route('admin.job-applications.index'));
        $appIndex->assertStatus(200);

        $updateStatus = $this->actingAs($this->admin)->patch(route('admin.job-applications.status', $app), [
            'status' => 'shortlisted',
        ]);
        $updateStatus->assertStatus(302);
        $this->assertDatabaseHas('job_applications', ['id' => $app->id, 'status' => 'shortlisted']);

        $destroyApp = $this->actingAs($this->admin)->delete(route('admin.job-applications.destroy', $app));
        $destroyApp->assertStatus(302);
        $this->assertDatabaseMissing('job_applications', ['id' => $app->id]);
    }

    public function test_admin_can_access_specials_crud()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.specials.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.specials.store'), [
            'title' => 'Christmas Holiday Sale',
            'origin' => 'Brisbane',
            'destination' => 'Tarawa',
            'price' => 599.00,
            'currency' => 'AUD',
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.specials.index'));
        $this->assertDatabaseHas('special_offers', ['title' => 'Christmas Holiday Sale']);

        $special = SpecialOffer::where('title', 'Christmas Holiday Sale')->first();
        $destroy = $this->actingAs($this->admin)->delete(route('admin.specials.destroy', $special));
        $destroy->assertRedirect(route('admin.specials.index'));
        $this->assertDatabaseMissing('special_offers', ['title' => 'Christmas Holiday Sale']);
    }

    public function test_admin_can_access_faqs_crud()
    {
        $response = $this->actingAs($this->admin)->get(route('admin.faqs.index'));
        $response->assertStatus(200);

        $store = $this->actingAs($this->admin)->post(route('admin.faqs.store'), [
            'category' => 'General',
            'question' => 'How can I change my flight booking?',
            'answer' => 'Contact reservations or your travel agent.',
            'is_active' => true,
        ]);
        $store->assertRedirect(route('admin.faqs.index'));
        $this->assertDatabaseHas('faqs', ['question' => 'How can I change my flight booking?']);

        $faq = Faq::where('question', 'How can I change my flight booking?')->first();
        $destroy = $this->actingAs($this->admin)->delete(route('admin.faqs.destroy', $faq));
        $destroy->assertRedirect(route('admin.faqs.index'));
        $this->assertDatabaseMissing('faqs', ['question' => 'How can I change my flight booking?']);
    }

    public function test_admin_can_manage_inquiries()
    {
        $inquiry = Inquiry::create([
            'type' => 'contact',
            'name' => 'Passenger Test',
            'email' => 'passenger@test.com',
            'subject' => 'Help needed',
            'message' => 'Please assist with my booking.',
            'status' => 'new',
        ]);

        $index = $this->actingAs($this->admin)->get(route('admin.inquiries.index'));
        $index->assertStatus(200);

        $show = $this->actingAs($this->admin)->get(route('admin.inquiries.show', $inquiry));
        $show->assertStatus(200);

        $update = $this->actingAs($this->admin)->patch(route('admin.inquiries.status', $inquiry), [
            'status' => 'resolved',
        ]);
        $update->assertStatus(302);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'resolved']);

        $destroy = $this->actingAs($this->admin)->delete(route('admin.inquiries.destroy', $inquiry));
        $destroy->assertRedirect(route('admin.inquiries.index'));
        $this->assertDatabaseMissing('inquiries', ['id' => $inquiry->id]);
    }

    public function test_admin_can_manage_site_settings_and_notice()
    {
        $notice = Notice::create([
            'title' => 'Test Notice',
            'message' => 'Test message',
            'is_active' => true,
        ]);

        SiteSetting::create([
            'key' => 'site_email',
            'value' => 'info@naoero-airlines.com',
            'group' => 'contact',
            'type' => 'text',
        ]);

        $index = $this->actingAs($this->admin)->get(route('admin.settings.index'));
        $index->assertStatus(200);

        $updateNotice = $this->actingAs($this->admin)->patch(route('admin.settings.notice', $notice), [
            'title' => 'Updated Notice',
            'message' => 'Updated message',
            'is_active' => false,
        ]);
        $updateNotice->assertStatus(302);
        $this->assertDatabaseHas('notices', ['title' => 'Updated Notice', 'is_active' => false]);

        $updateSettings = $this->actingAs($this->admin)->post(route('admin.settings.update'), [
            'site_email' => 'contact@naoero-airlines.com',
        ]);
        $updateSettings->assertStatus(302);
        $this->assertDatabaseHas('site_settings', ['key' => 'site_email', 'value' => 'contact@naoero-airlines.com']);
    }
}
