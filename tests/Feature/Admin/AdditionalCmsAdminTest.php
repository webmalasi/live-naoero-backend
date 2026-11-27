<?php

namespace Tests\Feature\Admin;

use App\Models\AgencyContact;
use App\Models\FlightSchedule;
use App\Models\GalleryItem;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdditionalCmsAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Super Admin']);
        $this->admin = User::create([
            'name' => 'Admin Controller',
            'email' => 'admin@naoeroairlines.com',
            'password' => bcrypt('password'),
            'status' => 'active',
        ]);
        $this->admin->assignRole($role);
    }

    public function test_agency_crud_operations()
    {
        // Index
        $response = $this->actingAs($this->admin)->get(route('admin.agencies.index'));
        $response->assertStatus(200);

        // Store
        $storeRes = $this->actingAs($this->admin)->post(route('admin.agencies.store'), [
            'country' => 'Fiji',
            'flag' => '🇫🇯',
            'name' => 'Suva Ticketing Office',
            'address' => 'Victoria Parade, Suva, Fiji',
            'phones' => "+679 331 2233\n+679 331 2234",
            'emails' => "suva@naoeroairlines.com\nhelp@naoeroairlines.com",
            'website' => 'https://www.naoeroairlines.com',
            'is_active' => '1',
            'sort_order' => 2,
        ]);
        $storeRes->assertRedirect(route('admin.agencies.index'));
        $this->assertDatabaseHas('agency_contacts', ['name' => 'Suva Ticketing Office', 'country' => 'Fiji']);

        $agency = AgencyContact::first();

        // Edit & Update
        $updateRes = $this->actingAs($this->admin)->put(route('admin.agencies.update', $agency), [
            'country' => 'Fiji Islands',
            'name' => 'Suva PSA Main Office',
            'is_active' => '1',
        ]);
        $updateRes->assertRedirect(route('admin.agencies.index'));
        $this->assertDatabaseHas('agency_contacts', ['name' => 'Suva PSA Main Office', 'country' => 'Fiji Islands']);

        // Toggle Active
        $toggleRes = $this->actingAs($this->admin)->patch(route('admin.agencies.toggle-active', $agency));
        $toggleRes->assertRedirect();
        $this->assertEquals(false, $agency->fresh()->is_active);

        // Delete
        $deleteRes = $this->actingAs($this->admin)->delete(route('admin.agencies.destroy', $agency));
        $deleteRes->assertRedirect(route('admin.agencies.index'));
        $this->assertDatabaseMissing('agency_contacts', ['id' => $agency->id]);
    }

    public function test_schedule_crud_operations()
    {
        Storage::fake('public');

        // Index
        $this->actingAs($this->admin)->get(route('admin.schedules.index'))->assertStatus(200);

        $file = UploadedFile::fake()->create('summer_timetable.pdf', 1000, 'application/pdf');

        // Store
        $storeRes = $this->actingAs($this->admin)->post(route('admin.schedules.store'), [
            'title' => 'Island Hopper Timetable',
            'period' => 'Nov 2026 - Mar 2027',
            'route' => 'Brisbane - Nauru - Majuro',
            'file' => $file,
            'is_active' => '1',
            'sort_order' => 1,
        ]);
        $storeRes->assertRedirect(route('admin.schedules.index'));
        $this->assertDatabaseHas('flight_schedules', ['title' => 'Island Hopper Timetable']);

        $schedule = FlightSchedule::first();

        // Update
        $updateRes = $this->actingAs($this->admin)->put(route('admin.schedules.update', $schedule), [
            'title' => 'Island Hopper Timetable Revised',
            'period' => 'Nov 2026 - Apr 2027',
            'is_active' => '1',
        ]);
        $updateRes->assertRedirect(route('admin.schedules.index'));
        $this->assertDatabaseHas('flight_schedules', ['title' => 'Island Hopper Timetable Revised']);

        // Destroy
        $this->actingAs($this->admin)->delete(route('admin.schedules.destroy', $schedule))
            ->assertRedirect(route('admin.schedules.index'));
        $this->assertDatabaseMissing('flight_schedules', ['id' => $schedule->id]);
    }

    public function test_gallery_crud_operations()
    {
        Storage::fake('public');

        // Index
        $this->actingAs($this->admin)->get(route('admin.gallery.index'))->assertStatus(200);

        $image = UploadedFile::fake()->image('cabin_interior.jpg', 800, 600);

        // Store
        $storeRes = $this->actingAs($this->admin)->post(route('admin.gallery.store'), [
            'title' => 'Modern Business Class Cabin',
            'destination' => 'Nauru',
            'category' => 'Onboard',
            'image_file' => $image,
            'is_active' => '1',
            'sort_order' => 1,
        ]);
        $storeRes->assertRedirect(route('admin.gallery.index'));
        $this->assertDatabaseHas('gallery_items', ['title' => 'Modern Business Class Cabin']);

        $item = GalleryItem::first();

        // Update
        $this->actingAs($this->admin)->put(route('admin.gallery.update', $item), [
            'title' => 'Modern Business Class Cabin Deluxe',
            'category' => 'Onboard Experience',
            'is_active' => '1',
        ])->assertRedirect(route('admin.gallery.index'));
        $this->assertDatabaseHas('gallery_items', ['title' => 'Modern Business Class Cabin Deluxe']);

        // Destroy
        $this->actingAs($this->admin)->delete(route('admin.gallery.destroy', $item))
            ->assertRedirect(route('admin.gallery.index'));
        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    public function test_newsletter_subscriber_management()
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'flyer@pacific.com',
            'source' => 'web_popup',
            'is_active' => true,
        ]);

        // Index
        $this->actingAs($this->admin)->get(route('admin.newsletter.index'))->assertStatus(200);

        // Toggle status
        $this->actingAs($this->admin)->patch(route('admin.newsletter.toggle-status', $subscriber))
            ->assertRedirect();
        $this->assertEquals(false, $subscriber->fresh()->is_active);

        // Export CSV
        $export = $this->actingAs($this->admin)->get(route('admin.newsletter.export'));
        $export->assertStatus(200);
        $this->assertStringContainsString('text/csv', $export->headers->get('content-type'));

        // Destroy
        $this->actingAs($this->admin)->delete(route('admin.newsletter.destroy', $subscriber))
            ->assertRedirect(route('admin.newsletter.index'));
        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $subscriber->id]);
    }
}
