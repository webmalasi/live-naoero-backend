<?php

namespace Tests\Feature\Admin;

use App\Models\AgencyResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgencyResourcePdfTest extends TestCase
{
    use RefreshDatabase;

    private function resource(string $title, ?string $file, ?string $link = null): AgencyResource
    {
        return AgencyResource::create(['title' => $title, 'file_url' => $file, 'external_url' => $link, 'is_active' => true]);
    }

    private function admin(): User
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@naoeroairlines.com', 'password' => bcrypt('password123'), 'status' => 'active']);
        $admin->assignRole(Role::create(['name' => 'Super Admin', 'guard_name' => 'web']));

        return $admin;
    }

    public function test_only_an_entry_whose_pdf_is_really_there_has_a_pdf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('policies/real.pdf', '%PDF-1.4');
        Storage::disk('public')->put('policies/form.docx', 'doc');

        $this->assertTrue($this->resource('Stored', '/storage/policies/real.pdf')->has_pdf);
        $this->assertTrue($this->resource('Elsewhere', null, 'https://example.com/guide.pdf?v=2')->has_pdf);
        $this->assertFalse($this->resource('Missing file', '/assets/docs/agency/Missing_2026.pdf')->has_pdf);
        $this->assertFalse($this->resource('Missing upload', '/storage/policies/gone.pdf')->has_pdf);
        $this->assertFalse($this->resource('Word file', '/storage/policies/form.docx')->has_pdf);
        $this->assertFalse($this->resource('Web page', null, 'https://example.com/agency')->has_pdf);
        $this->assertFalse($this->resource('Nothing', null)->has_pdf);
    }

    public function test_admin_shows_whether_each_card_has_a_pdf(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('policies/real.pdf', '%PDF-1.4');
        $this->resource('Seating Policy', '/storage/policies/real.pdf');
        $this->resource('Broken Guide', '/assets/docs/agency/Missing_2026.pdf');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.agency-resources.index'))
            ->assertOk()
            ->assertSeeInOrder(['Seating Policy', 'PDF'])
            ->assertSeeInOrder(['Broken Guide', 'No PDF']);

        $page = $this->actingAs($admin)->get(route('admin.content-pages.list', 'agency-resources'))->assertOk();
        $rows = collect($page->viewData('feedEntries')['agency-resources']['rows'])->pluck('pdf', 'title');
        $this->assertSame(['Seating Policy' => true, 'Broken Guide' => false], $rows->all());
        $page->assertSee("entry.pdf ? 'PDF' : 'No PDF'", false);
    }

    public function test_the_cleanup_removes_only_cards_without_a_pdf_and_keeps_a_copy(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('policies/real.pdf', '%PDF-1.4');
        $kept = $this->resource('Seating Policy', '/storage/policies/real.pdf');
        $this->resource('Broken Guide', '/assets/docs/agency/Missing_2026.pdf');

        (require database_path('migrations/2026_11_24_100000_remove_agency_resources_without_a_pdf.php'))->up();

        $this->assertSame([$kept->id], AgencyResource::pluck('id')->all());
        $backups = Storage::disk('local')->files('backups');
        $this->assertCount(1, $backups);
        $this->assertSame('Broken Guide', json_decode(Storage::disk('local')->get($backups[0]), true)[0]['title']);
    }
}
