<?php

namespace Tests\Feature\Api;

use App\Models\Career;
use App\Models\Faq;
use App\Models\Fleet;
use App\Models\Notice;
use App\Models\Post;
use App\Models\SiteSetting;
use App\Models\SpecialOffer;
use App\Models\TeamMember;
use App\Models\Testimonial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CmsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed sample CMS data
        Fleet::create([
            'type' => 'Boeing 737-700',
            'registration' => 'VH-INU',
            'category' => 'passenger',
            'seating' => '128 Seats',
            'configuration' => '12 Business / 116 Economy',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Post::create([
            'title' => 'Top 5 Hidden Spots in Pohnpei',
            'slug' => 'top-5-hidden-spots-in-pohnpei',
            'category' => 'Pohnpei Blog',
            'excerpt' => 'A guide to exploring pristine waterfalls.',
            'content' => 'Full article body here.',
            'author' => 'Travel Team',
            'is_published' => true,
            'is_featured' => true,
            'published_at' => now(),
        ]);

        Testimonial::create([
            'name' => 'Capt. John Doe',
            'location' => 'Brisbane',
            'quote' => 'Exceptional Pacific Island flight experience.',
            'rating' => 5,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        TeamMember::create([
            'name' => 'Brett Gebers',
            'role' => 'Chief Executive Officer',
            'department' => 'Executive Management',
            'bio' => 'Experienced aviation executive.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        SpecialOffer::create([
            'title' => 'Brisbane to Nauru Getaway',
            'slug' => 'brisbane-to-nauru-getaway',
            'origin' => 'Brisbane',
            'destination' => 'Nauru',
            'price' => 499.00,
            'currency' => 'AUD',
            'badge' => '20% OFF',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Career::create([
            'title' => 'B737 First Officer',
            'slug' => 'b737-first-officer',
            'department' => 'Flight Operations',
            'location' => 'Brisbane / Nauru',
            'employment_type' => 'Full-time',
            'short_description' => 'Seeking experienced B737 First Officers.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Faq::create([
            'category' => 'Baggage',
            'question' => 'What is the checked baggage allowance?',
            'answer' => 'Standard economy allowance is 23kg per passenger.',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Notice::create([
            'title' => 'Flight Notice',
            'message' => 'Check-in opens 3 hours before international departure.',
            'link' => '/destinations',
            'is_active' => true,
        ]);

        SiteSetting::create([
            'key' => 'site_phone',
            'value' => '+674 557 3737',
            'group' => 'contact',
            'type' => 'text',
        ]);
    }

    public function test_can_list_fleet_and_get_details()
    {
        $response = $this->getJson('/api/fleet');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['registration' => 'VH-INU']);

        $single = $this->getJson('/api/fleet/VH-INU');
        $single->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['registration' => 'VH-INU']);
    }

    public function test_can_list_posts_and_categories_and_single()
    {
        $response = $this->getJson('/api/posts');
        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['slug' => 'top-5-hidden-spots-in-pohnpei']);

        $categories = $this->getJson('/api/posts/categories');
        $categories->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['category' => 'Pohnpei Blog']);

        $single = $this->getJson('/api/posts/top-5-hidden-spots-in-pohnpei');
        $single->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['slug' => 'top-5-hidden-spots-in-pohnpei']);
    }

    public function test_can_list_testimonials()
    {
        $response = $this->getJson('/api/testimonials');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['name' => 'Capt. John Doe']);
    }

    public function test_can_list_team_members()
    {
        $response = $this->getJson('/api/team');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['name' => 'Brett Gebers']);
    }

    public function test_can_list_special_offers_and_single()
    {
        $response = $this->getJson('/api/specials');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['slug' => 'brisbane-to-nauru-getaway']);

        $single = $this->getJson('/api/specials/brisbane-to-nauru-getaway');
        $single->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['title' => 'Brisbane to Nauru Getaway']);
    }

    public function test_can_list_careers_and_submit_job_application()
    {
        Storage::fake('public');

        $response = $this->getJson('/api/careers');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['slug' => 'b737-first-officer']);

        $career = Career::first();

        $file = UploadedFile::fake()->create('resume.pdf', 500, 'application/pdf');

        $apply = $this->postJson("/api/careers/{$career->id}/apply", [
            'first_name' => 'Jane',
            'last_name' => 'Pilot',
            'email' => 'jane@pilot.com',
            'phone' => '+61 400 123 456',
            'cover_letter' => 'I would love to join Naoero Airlines.',
            'resume' => $file,
        ]);

        $apply->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('job_applications', [
            'career_id' => $career->id,
            'first_name' => 'Jane',
            'last_name' => 'Pilot',
            'email' => 'jane@pilot.com',
        ]);
    }

    public function test_can_list_faqs()
    {
        $response = $this->getJson('/api/faqs');
        $response->assertStatus(200)
            ->assertJson(['success' => true, 'count' => 1])
            ->assertJsonFragment(['category' => 'Baggage']);
    }

    public function test_can_submit_contact_and_feedback_and_charter_inquiries()
    {
        // Contact inquiry
        $contact = $this->postJson('/api/contact', [
            'name' => 'Mark Traveler',
            'email' => 'mark@traveler.com',
            'phone' => '+61 400 000 001',
            'subject' => 'Flight schedule inquiry',
            'message' => 'When is the next flight to Tarawa?',
        ]);
        $contact->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'contact', 'email' => 'mark@traveler.com']);

        // Feedback inquiry
        $feedback = $this->postJson('/api/feedback', [
            'name' => 'Alice Passenger',
            'email' => 'alice@passenger.com',
            'phone' => '+61 400 000 002',
            'message' => 'Great in-flight hospitality on the Brisbane flight.',
            'feedbackCategory' => 'In-flight experience',
        ]);
        $feedback->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'feedback', 'email' => 'alice@passenger.com']);

        // Group Booking inquiry
        $group = $this->postJson('/api/group-bookings', [
            'name' => 'Sports Team Coordinator',
            'email' => 'sports@team.com',
            'phone' => '+61 400 000 003',
            'passengerCount' => 24,
            'origin' => 'Brisbane',
            'destination' => 'Nauru',
            'notes' => 'Group travel for South Pacific games.',
        ]);
        $group->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'group_booking', 'email' => 'sports@team.com']);

        // Charter inquiry
        $charter = $this->postJson('/api/charter-inquiries', [
            'name' => 'Freight Logistics Manager',
            'email' => 'freight@logistics.com',
            'phone' => '+61 400 000 004',
            'route' => 'Brisbane - Honiara - Nauru',
            'charterType' => 'Freighter',
            'message' => 'Cargo charter inquiry for urgent humanitarian goods.',
        ]);
        $charter->assertStatus(201)->assertJson(['success' => true]);
        $this->assertDatabaseHas('inquiries', ['type' => 'charter', 'email' => 'freight@logistics.com']);
    }

    public function test_can_get_notice_bar_and_settings()
    {
        $notice = $this->getJson('/api/notice-bar');
        $notice->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['title' => 'Flight Notice']);

        $settings = $this->getJson('/api/settings');
        $settings->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonFragment(['site_phone' => '+674 557 3737']);
    }
}
