<?php

namespace Tests\Feature\Admin;

use App\Models\PageBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PageBannerAdminTest extends TestCase
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

    public function test_admin_can_view_banners_index_page(): void
    {
        PageBanner::create([
            'page_key' => 'destinations',
            'page_name' => 'Destinations Hub',
            'page_route' => '/destinations',
            'banner_image' => 'https://images.unsplash.com/photo-destinations.jpg',
            'banner_heading' => 'Explore Our Pacific Destinations',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/banners');
        $response->assertStatus(200)
            ->assertSee('Frontend Page Banners')
            ->assertSee('Destinations Hub')
            ->assertSee('Explore Our Pacific Destinations');
    }

    public function test_admin_can_create_new_page_banner_with_upload(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('banner_hero.jpg', 1920, 800);

        $payload = [
            'page_key' => 'custom-charters',
            'page_name' => 'Custom VIP Charters',
            'page_route' => '/charter',
            'banner_badge_text' => 'VIP Airline Charter',
            'banner_image_file' => $image,
            'banner_heading' => 'Fly On Your Own Schedule Across The Pacific',
            'banner_description' => 'Dedicated Boeing 737 aircraft charter tailored to your exact mission requirements.',
            'banner_button_text' => 'Request VIP Quote',
            'banner_button_link' => '/charter',
            'sort_order' => 5,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post('/admin/banners', $payload);
        $response->assertRedirect('/admin/banners')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('page_banners', [
            'page_key' => 'custom-charters',
            'page_name' => 'Custom VIP Charters',
            'banner_heading' => 'Fly On Your Own Schedule Across The Pacific',
            'banner_button_text' => 'Request VIP Quote',
        ]);

        $banner = PageBanner::where('page_key', 'custom-charters')->first();
        $this->assertStringStartsWith('/storage/banners/', $banner->banner_image);
    }

    public function test_admin_can_update_existing_page_banner(): void
    {
        $banner = PageBanner::create([
            'page_key' => 'blog',
            'page_name' => 'Blog Articles',
            'page_route' => '/blog',
            'banner_image' => 'https://images.unsplash.com/photo-blog.jpg',
            'banner_heading' => 'Original Blog Heading',
            'banner_description' => 'Original description',
            'banner_button_text' => 'Read',
            'banner_button_link' => '/blog',
            'is_active' => true,
        ]);

        $payload = [
            'page_key' => 'blog',
            'page_name' => 'Updated Blog & Travel Hub',
            'page_route' => '/blog',
            'banner_heading' => 'Stories from Nauru and the Pacific Atolls',
            'banner_description' => 'Updated travel insights and cultural spotlights.',
            'banner_button_text' => 'Explore All Stories',
            'banner_button_link' => '/blog',
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->put("/admin/banners/{$banner->id}", $payload);
        $response->assertRedirect('/admin/banners')
            ->assertSessionHas('success');

        $banner->refresh();
        $this->assertEquals('Updated Blog & Travel Hub', $banner->page_name);
        $this->assertEquals('Stories from Nauru and the Pacific Atolls', $banner->banner_heading);
        $this->assertEquals('Explore All Stories', $banner->banner_button_text);
    }

    public function test_admin_can_toggle_banner_active_status(): void
    {
        $banner = PageBanner::create([
            'page_key' => 'faq',
            'page_name' => 'FAQs Page',
            'banner_image' => 'https://images.unsplash.com/faq.jpg',
            'banner_heading' => 'FAQ Heading',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->patch("/admin/banners/{$banner->id}/toggle-active");
        $response->assertRedirect()->assertSessionHas('success');

        $banner->refresh();
        $this->assertFalse($banner->is_active);
    }
}
