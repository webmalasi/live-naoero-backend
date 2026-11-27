<?php

namespace Tests\Feature\Admin;

use App\Models\Destination;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DestinationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Super Admin Role & User
        $role = Role::create(['name' => 'Super Admin']);
        $this->admin = User::factory()->create([
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_guest_cannot_access_admin_destinations()
    {
        $response = $this->get(route('admin.destinations.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_admin_can_view_destinations_index()
    {
        Destination::create([
            'name' => 'Brisbane',
            'slug' => 'brisbane',
            'country' => 'Australia',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.destinations.index'));

        $response->assertStatus(200)
            ->assertSee('Flight Destinations')
            ->assertSee('Brisbane');
    }

    public function test_admin_can_create_destination_with_file_uploads()
    {
        Storage::fake('public');

        $thumbnail = UploadedFile::fake()->image('brisbane-thumb.jpg', 600, 400);
        $banner = UploadedFile::fake()->image('brisbane-banner.jpg', 1600, 600);

        $response = $this->actingAs($this->admin)->post(route('admin.destinations.store'), [
            'name' => 'Fiji - Nadi',
            'slug' => 'nadi',
            'country' => 'Fiji',
            'flag' => '🇫🇯',
            'thumbnail_file' => $thumbnail,
            'banner_file' => $banner,
            'short_description' => 'Gateway to Fiji islands.',
            'discover_heading' => 'Discover Nadi',
            'intro' => 'A tropical paradise in the Pacific.',
            'is_featured' => 1,
            'is_active' => 1,
            'sort_order' => 1,
            'why_visit_json' => json_encode([
                ['title' => 'Island Life', 'description' => 'Beautiful beaches.'],
            ]),
        ]);

        $response->assertRedirect(route('admin.destinations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('destinations', [
            'name' => 'Fiji - Nadi',
            'slug' => 'nadi',
            'country' => 'Fiji',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $destination = Destination::where('slug', 'nadi')->first();
        $this->assertNotNull($destination->thumbnail);
        $this->assertNotNull($destination->banner_image);
        Storage::disk('public')->assertExists($destination->thumbnail);
        Storage::disk('public')->assertExists($destination->banner_image);
    }

    public function test_admin_can_update_destination()
    {
        $destination = Destination::create([
            'name' => 'Old Name',
            'slug' => 'old-name',
            'country' => 'Kiribati',
            'is_featured' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.destinations.update', $destination), [
            'name' => 'Updated Destination',
            'slug' => 'updated-destination',
            'country' => 'Kiribati Updated',
            'is_featured' => 1,
            'is_active' => 1,
            'sort_order' => 5,
        ]);

        $response->assertRedirect(route('admin.destinations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'name' => 'Updated Destination',
            'slug' => 'updated-destination',
            'country' => 'Kiribati Updated',
            'is_featured' => true,
        ]);
    }

    public function test_admin_can_toggle_active_and_featured_status()
    {
        $destination = Destination::create([
            'name' => 'Tarawa',
            'slug' => 'tarawa',
            'country' => 'Kiribati',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $responseActive = $this->actingAs($this->admin)
            ->patch(route('admin.destinations.toggle-active', $destination));
        $responseActive->assertSessionHas('success');
        $this->assertFalse($destination->fresh()->is_active);

        $responseFeatured = $this->actingAs($this->admin)
            ->patch(route('admin.destinations.toggle-featured', $destination));
        $responseFeatured->assertSessionHas('success');
        $this->assertFalse($destination->fresh()->is_featured);
    }

    public function test_admin_can_upload_image_asynchronously()
    {
        Storage::fake('public');

        $image = UploadedFile::fake()->image('activity-pic.jpg', 800, 600);

        $response = $this->actingAs($this->admin)->postJson(route('admin.destinations.upload-image'), [
            'image' => $image,
            'folder' => 'activities',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'path',
                'url',
                'message',
            ]);

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_admin_can_store_destination_with_why_visit_things_to_do_and_stays_images()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.destinations.store'), [
            'name' => 'Majuro Atoll',
            'slug' => 'majuro-atoll',
            'country' => 'Marshall Islands',
            'why_visit_json' => json_encode([
                [
                    'title' => 'Pristine Lagoons',
                    'description' => 'World class coral reefs.',
                    'image' => 'https://example.com/lagoon.jpg',
                ],
            ]),
            'things_to_do_json' => json_encode([
                [
                    'title' => 'WWII Wreck Diving',
                    'description' => 'Explore sunken history.',
                    'images' => [
                        'https://example.com/dive1.jpg',
                        'https://example.com/dive2.jpg',
                    ],
                ],
            ]),
            'stays_json' => json_encode([
                [
                    'title' => 'Marshall Islands Resort',
                    'description' => 'Overlooking the peaceful lagoon.',
                    'image' => 'https://example.com/hotel.jpg',
                ],
            ]),
        ]);

        $response->assertRedirect(route('admin.destinations.index'))
            ->assertSessionHas('success');

        $destination = Destination::where('slug', 'majuro-atoll')->first();
        $this->assertNotNull($destination);
        $this->assertEquals('https://example.com/lagoon.jpg', $destination->why_visit[0]['image']);
        $this->assertEquals('https://example.com/dive1.jpg', $destination->things_to_do[0]['images'][0]);
        $this->assertEquals('https://example.com/dive2.jpg', $destination->things_to_do[0]['images'][1]);
        $this->assertEquals('https://example.com/hotel.jpg', $destination->stays[0]['image']);
    }

    public function test_admin_can_delete_destination()
    {
        $destination = Destination::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'country' => 'Test',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.destinations.destroy', $destination));

        $response->assertRedirect(route('admin.destinations.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('destinations', [
            'id' => $destination->id,
        ]);
    }
}
