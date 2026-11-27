<?php

namespace Tests\Feature;

use App\Models\Career;
use App\Models\ContentPage;
use App\Models\ContentPageRevision;
use App\Models\Destination;
use App\Models\LegalDocument;
use App\Models\OurJourneyMilestone;
use App\Models\PageBanner;
use App\Models\Post;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WhyFlyFeature;
use App\Services\Content\ContentPages;
use App\Services\Content\FeedEntries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Pages edited in the admin panel and read by the website and the app:
 * the original text is added once and never overwritten, edits show at
 * once (new version, new ETag), and every save can be undone.
 */
class ContentPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->admin = User::create(['name' => 'Admin User', 'email' => 'admin@naoeroairlines.com', 'password' => bcrypt('password123'), 'status' => 'active']);
        $this->admin->assignRole($role);
    }

    public function test_every_seed_file_is_a_valid_page(): void
    {
        $files = File::glob(database_path('seeders/content/pages/*.json'));
        $this->assertNotEmpty($files);
        $pages = app(ContentPages::class);
        foreach ($files as $file) {
            $data = json_decode(File::get($file), true);
            $this->assertIsArray($data, basename($file).' is not valid JSON');
            $this->assertSame(basename($file, '.json'), $data['slug'], 'file name and slug');
            $clean = $pages->clean($data);
            $this->assertNotEmpty($clean['sections'], $data['slug']);
            $keys = array_column($data['sections'], 'key');
            $this->assertSame($keys, array_column($clean['sections'], 'key'), "{$data['slug']}: keys are kept as written and unique");
            foreach ($data['sections'] as $section) {
                $this->assertArrayHasKey($section['type'], ContentPages::TYPES, "{$data['slug']}.{$section['key']}");
            }
        }
    }

    public function test_the_original_pages_are_added_once_and_edits_are_kept(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        $count = ContentPage::count();
        $this->assertGreaterThan(0, $count);
        $charter = ContentPage::where('slug', 'charter')->firstOrFail();
        $this->assertSame(1, $charter->version);

        $data = app(ContentPages::class)->export($charter);
        $data['sections'][0]['heading'] = 'Charter with us';
        app(ContentPages::class)->save($charter, $data, $this->admin->id);

        // Running it again (every deploy) keeps the edit.
        $this->artisan('content:pages')->assertSuccessful();
        $this->assertSame($count, ContentPage::count());
        $this->assertSame('Charter with us', $charter->fresh()->sections()->first()->heading);

        // --reset puts the original back, and the edit stays in the history.
        $this->artisan('content:pages', ['--reset' => ['charter']])->assertSuccessful();
        $this->assertSame('Charter', $charter->fresh()->sections()->first()->heading);
        $this->assertSame(3, $charter->fresh()->version);
        $this->assertSame(2, ContentPageRevision::where('content_page_id', $charter->id)->where('version', '>', 0)->count());
    }

    public function test_a_new_original_page_is_added_but_an_edited_one_only_gets_what_is_missing(): void
    {
        Storage::fake('public');
        $this->artisan('content:pages')->assertSuccessful();
        $pages = app(ContentPages::class);

        // Pretend the pages were seeded by an older version of the original text.
        ContentPageRevision::query()->update(['note' => 'Original text']);

        // "baggage" was never edited but lacks a section that the original text now has.
        ContentPage::where('slug', 'baggage')->firstOrFail()->sections()->where('key', 'tsa')->delete();

        // "acmi" was edited: its heading is the client's, a section and a picture are missing.
        $acmi = ContentPage::where('slug', 'acmi')->firstOrFail();
        $data = $pages->export($acmi);
        $data['sections'][0]['heading'] = 'ACMI by the client';
        $pages->save($acmi, $data, $this->admin->id, 'Client edit');
        ContentPageRevision::query()->where('content_page_id', $acmi->id)->update(['note' => 'Client edit']);
        $acmi->fresh()->sections()->where('key', 'quick-links')->delete();
        $acmi->fresh()->sections()->where('key', 'benefits')->update(['image' => null]);

        $this->artisan('content:pages')->assertSuccessful();

        $this->assertTrue(ContentPage::where('slug', 'baggage')->first()->sections()->where('key', 'tsa')->exists());
        $acmi = $acmi->fresh();
        $this->assertSame('ACMI by the client', $acmi->sections()->first()->heading);
        $this->assertTrue($acmi->sections()->where('key', 'quick-links')->exists());
        $this->assertNotEmpty($acmi->sections()->where('key', 'benefits')->first()->image);
        Storage::disk('public')->assertExists($acmi->sections()->where('key', 'benefits')->first()->image);

        // Once per original text: the client can remove a picture again and it stays removed.
        $acmi->sections()->where('key', 'benefits')->update(['image' => null]);
        $this->artisan('content:pages')->assertSuccessful();
        $this->assertEmpty($acmi->fresh()->sections()->where('key', 'benefits')->first()->image);
    }

    public function test_the_conditions_of_carriage_definitions_are_put_back_as_text(): void
    {
        $junk = "Intro. Terms Meaning Agreed Stopping Places Those places.\", ], [ 'Airline Designator Code', \"The two letters.\", ],";
        $doc = LegalDocument::create([
            'slug' => 'conditions-of-carriage', 'title' => 'Conditions of Carriage', 'is_active' => true,
            'sections' => [['heading' => 'Section 1. One', 'content' => 'Fine.'], ['heading' => 'Section 19. Terms Used In These Conditions', 'content' => $junk]],
            'content' => '<h2>Section 1. One</h2><p>Fine.</p><h2>Section 19. Terms Used In These Conditions</h2><table>'.$junk.'</table>',
        ]);

        $this->artisan('content:pages')->assertSuccessful();

        $doc = $doc->fresh();
        $this->assertSame('Fine.', $doc->sections[0]['content']);
        $this->assertStringNotContainsString('], [', $doc->sections[1]['content']);
        $this->assertStringContainsString('Agreed Stopping Places - Those places, except the place of departure', $doc->sections[1]['content']);
        $this->assertStringNotContainsString('], [', $doc->content);
        $this->assertStringContainsString('<td class="border border-slate-200 p-2 font-semibold">Stopover</td>', $doc->content);
        $this->assertStringContainsString('<h2>Section 1. One</h2>', $doc->content);

        // Clean text is left alone the next time.
        $before = $doc->content;
        $this->artisan('content:pages')->assertSuccessful();
        $this->assertSame($before, $doc->fresh()->content);
    }

    public function test_the_api_serves_the_page_with_its_version(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        PageBanner::create(['page_key' => 'charter', 'page_name' => 'Charter', 'banner_heading' => 'Charter', 'banner_description' => 'Fly with us', 'banner_image' => 'banners/charter.jpg', 'is_active' => true]);

        $res = $this->getJson('/api/pages/charter')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'charter')
            ->assertJsonPath('data.banner.description', 'Fly with us')
            ->assertJsonPath('data.sections.0.key', 'intro');
        $etag = $res->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('no-cache', $res->headers->get('Cache-Control'));
        $this->assertSame(trim($etag, '"'), $res->json('data.version'));

        // The same version again: an empty 304.
        $this->getJson('/api/pages/charter', ['If-None-Match' => $etag])->assertStatus(304);

        // Each design gets its own sections.
        $app = collect($this->getJson('/api/pages/charter?channel=app')->json('data.sections'))->pluck('show_on')->unique()->values()->all();
        $this->assertContains('app', $app);
        $this->assertNotContains('website', $app);
        $web = collect($this->getJson('/api/pages/charter?channel=website')->json('data.sections'))->pluck('show_on')->all();
        $this->assertNotContains('app', $web);

        // A hidden page is not served (the clients show their built-in text).
        ContentPage::where('slug', 'charter')->update(['is_published' => false]);
        $this->getJson('/api/pages/charter')->assertNotFound();
        $this->getJson('/api/pages/no-such-page')->assertNotFound();
    }

    public function test_the_news_and_destination_text_from_the_website_are_added_once(): void
    {
        Storage::fake('public');
        Destination::create([
            'name' => 'Nadi', 'slug' => 'nadi', 'country' => 'Fiji', 'is_active' => true,
            'why_visit' => [['title' => 'Beaches'], ['sectionBreak' => 'More'], ['title' => 'Food', 'image' => 'https://example.test/own.jpg']],
        ]);
        $this->artisan('content:pages')->assertSuccessful();

        $post = Post::where('slug', 'wellness-walk')->firstOrFail();
        $this->assertSame(['news', 'Media Release', true], [$post->type, $post->category, $post->is_published]);
        Storage::disk('public')->assertExists($post->image);
        $this->getJson('/api/news/wellness-walk')->assertOk();
        $this->assertNotEmpty(Destination::where('slug', 'nadi')->first()->info_sections);
        $cards = Destination::where('slug', 'nadi')->first()->why_visit;
        $this->assertNotEmpty($cards[0]['image'] ?? null, 'a Why visit card without a picture gets one');
        $this->assertArrayNotHasKey('image', $cards[1], 'a section break has no picture');
        $this->assertSame('https://example.test/own.jpg', $cards[2]['image'], 'a picture the client set is kept');

        // An edit made in the admin panel is never put back.
        $post->update(['title' => 'Edited']);
        Destination::where('slug', 'nadi')->update(['info_sections' => json_encode([['heading' => 'Mine']])]);
        $this->artisan('content:pages')->assertSuccessful();
        $this->assertSame('Edited', $post->fresh()->title);
        $this->assertSame('Mine', Destination::where('slug', 'nadi')->first()->info_sections[0]['heading']);
        $this->assertSame(1, Post::where('slug', 'wellness-walk')->count());
    }

    public function test_text_marks_become_safe_html(): void
    {
        $pages = app(ContentPages::class);
        $html = $pages->html("Intro with **bold** and [our fleet](/our-fleet).\nSecond line\n\n- one\n- two <script>x</script>\n\n1. first\n2. second\n\nMail info@naoeroairlines.com or see https://napacificbridge.info/charter.");
        $this->assertStringContainsString('<p>Intro with <strong>bold</strong> and <a href="/our-fleet">our fleet</a>.<br>Second line</p>', $html);
        $this->assertStringContainsString('<ul><li>one</li><li>two &lt;script&gt;x&lt;/script&gt;</li></ul>', $html);
        $this->assertStringContainsString('<ol><li>first</li><li>second</li></ol>', $html);
        $this->assertStringContainsString('<a href="mailto:info@naoeroairlines.com">info@naoeroairlines.com</a>', $html);
        $this->assertStringContainsString('<a href="https://napacificbridge.info/charter" target="_blank" rel="noopener">https://napacificbridge.info/charter</a>.', $html);
        $this->assertStringNotContainsString('<script>', $html);
        // Unsafe links are dropped.
        $this->assertStringNotContainsString('javascript', $pages->html('[x](javascript:alert(1))'));
        $this->assertNull($pages->safeUrl('javascript:alert(1)'));
    }

    public function test_versions_change_when_anything_changes(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        $first = $this->getJson('/api/content/versions')->assertOk()->json('data');
        $this->assertArrayHasKey('charter', $first['pages']);
        $this->assertContains('/team', $first['collections']['team']['paths']);

        $this->getJson('/api/content/versions', ['If-None-Match' => '"'.$first['version'].'"'])->assertStatus(304);

        // A page edit changes only that page.
        $page = ContentPage::where('slug', 'charter')->first();
        $data = app(ContentPages::class)->export($page);
        $data['sections'][0]['body'] = 'New text';
        app(ContentPages::class)->save($page, $data);
        $second = $this->getJson('/api/content/versions')->json('data');
        $this->assertNotSame($first['pages']['charter'], $second['pages']['charter']);
        $this->assertSame($first['collections']['team'], $second['collections']['team']);
        $this->assertNotSame($first['version'], $second['version']);

        // Another admin list (Our Team) changes its collection.
        TeamMember::create(['name' => 'Ana', 'role' => 'Captain', 'is_active' => true, 'sort_order' => 1]);
        $third = $this->getJson('/api/content/versions')->json('data');
        $this->assertNotSame($second['collections']['team']['version'], $third['collections']['team']['version']);
        $this->assertSame($second['pages']['charter'], $third['pages']['charter']);
    }

    public function test_the_admin_edits_a_page_and_can_undo_it(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        $page = ContentPage::where('slug', 'charter')->firstOrFail();

        $this->actingAs($this->admin)->get('/admin/content-pages')->assertOk()->assertSee('Charter');
        $this->actingAs($this->admin)->get("/admin/content-pages/{$page->id}/edit")->assertOk()->assertSee('Personalized Charter Solutions');

        $data = app(ContentPages::class)->export($page);
        $data['sections'][1]['heading'] = 'Tailored Charter Solutions';
        $data['sections'][] = ['key' => 'new-block', 'type' => 'notice', 'heading' => 'Cyclone season', 'body' => 'Check your flight before you leave.', 'show_on' => 'both'];
        $this->actingAs($this->admin)->put("/admin/content-pages/{$page->id}", ['version' => 1, 'page_json' => json_encode($data), 'note' => 'New heading'])
            ->assertRedirect("/admin/content-pages/{$page->id}/edit");
        $this->getJson('/api/pages/charter')->assertJsonPath('data.sections.1.heading', 'Tailored Charter Solutions')
            ->assertJsonFragment(['key' => 'new-block', 'heading' => 'Cyclone season']);

        // A second editor with the old version is stopped (no lost edits).
        $this->actingAs($this->admin)->put("/admin/content-pages/{$page->id}", ['version' => 1, 'page_json' => json_encode($data)])
            ->assertSessionHas('error');
        $this->assertSame(2, $page->fresh()->version);

        // Undo from the history.
        $revision = ContentPageRevision::where('content_page_id', $page->id)->where('version', 1)->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/content-pages/{$page->id}/revisions/{$revision->id}/restore")->assertRedirect();
        $this->getJson('/api/pages/charter')->assertJsonPath('data.sections.1.heading', 'Personalized Charter Solutions')
            ->assertJsonMissing(['key' => 'new-block']);
        $this->assertDatabaseHas('activity_logs', ['module' => 'content_pages', 'action' => 'restored']);

        // Hide and publish.
        $this->actingAs($this->admin)->patch("/admin/content-pages/{$page->id}/toggle-published")->assertRedirect();
        $this->getJson('/api/pages/charter')->assertNotFound();
        $this->actingAs($this->admin)->patch("/admin/content-pages/{$page->id}/toggle-published")->assertRedirect();
        $this->getJson('/api/pages/charter')->assertOk();
    }

    public function test_the_toolbar_formats_become_safe_html(): void
    {
        $pages = app(ContentPages::class);
        $this->assertSame('<p><strong>b</strong> <em>i</em> <u>u</u></p>', $pages->html('**b** *i* __u__'));
        $this->assertSame('<p><strong><em>both</em></strong></p>', $pages->html('***both***'));
        $this->assertSame('<p><u><strong>x</strong></u></p>', $pages->html('__**x**__'));
        // Not formatting: a lone star, a star in a field name, an address with underscores.
        $this->assertSame('<p>First Name *</p>', $pages->html('First Name *'));
        $this->assertSame('<p>2 * 3 * 4</p>', $pages->html('2 * 3 * 4'));
        $this->assertStringContainsString('href="https://a.test/x__y__z"', $pages->html('https://a.test/x__y__z'));
    }

    public function test_the_editor_gives_every_text_a_toolbar_and_pictures_their_place(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        PageBanner::create(['page_key' => 'freight-packaging', 'page_name' => 'Freight Packaging', 'banner_heading' => 'Banner words', 'banner_image' => 'banners/fp.jpg', 'is_active' => true]);
        $page = ContentPage::where('slug', 'freight-packaging')->firstOrFail();

        $html = $this->actingAs($this->admin)->get(route('admin.content-pages.edit', $page))->assertOk()->getContent();

        foreach (['section.body', 'section.note', 'item.text', 'page.summary'] as $field) {
            $this->assertStringContainsString('x-rich="'.$field.'"', $html);
            $this->assertStringNotContainsString('<textarea x-model="'.$field.'"', $html);
        }
        $this->assertStringContainsString('picturesFor(item)', $html);
        $this->assertStringContainsString('Banner words', $html, 'the page says when Page Banners decides the top text');

        // Each tip with pictures keeps them in a section the editor shows on the tip.
        $tips = $page->sections()->where('key', 'tips')->first()->items;
        foreach (['cargo-dimensions', 'sturdy-packing', 'tape-gun', 'dangerous-goods'] as $slug) {
            $this->assertContains($slug, array_column($tips, 'slug'));
            $this->assertNotEmpty($page->sections()->where('key', 'pictures-'.$slug)->first()->items);
        }
    }

    public function test_live_lists_show_their_entries_in_the_page_editor(): void
    {
        Storage::fake('public');
        $this->artisan('content:pages')->assertSuccessful();
        $career = Career::create(['title' => 'Cabin Crew', 'slug' => 'cabin-crew', 'is_active' => true]);
        $feature = WhyFlyFeature::create(['title' => 'Trusted Routes', 'description' => '<p>Safe</p>', 'is_active' => true]);
        $milestone = OurJourneyMilestone::create(['year' => '1970', 'title' => 'First Flight', 'description' => 'x', 'image' => '/assets/j.png', 'is_active' => true]);
        $this->artisan('content:pages')->assertSuccessful();

        // The About photo goes on the entry once, and a picture the client sets stays.
        $this->assertStringStartsWith('/storage/content/seed/about-new/', $feature->fresh()->icon_image);
        $feature->update(['icon_image' => '/storage/why_fly/mine.png']);
        $this->artisan('content:pages')->assertSuccessful();
        $this->assertSame('/storage/why_fly/mine.png', $feature->fresh()->icon_image);

        $feeds = app(FeedEntries::class)->all();
        foreach (ContentPages::FEEDS as $feed => $meta) {
            $this->assertArrayHasKey($feed, $feeds, $feed);
        }
        $this->assertSame('Cabin Crew', $feeds['careers']['rows'][0]['title']);
        $this->assertStringEndsWith('/careers/'.$career->id.'/edit', $feeds['careers']['rows'][0]['edit']);
        $this->assertSame(url('/assets/j.png'), $feeds['our-journey']['rows'][0]['image']);
        $this->assertStringContainsString('/milestones/'.$milestone->id.'/edit', $feeds['our-journey']['rows'][0]['edit']);
        $this->assertSame(url('/storage/why_fly/mine.png'), $feeds['why-fly']['rows'][0]['image']);

        foreach (['about-new', 'career'] as $slug) {
            $html = $this->actingAs($this->admin)->get(route('admin.content-pages.edit', ContentPage::where('slug', $slug)->first()))->assertOk()->getContent();
            $this->assertStringContainsString('feedEntries[section.feed].rows', $html);
            $this->assertStringContainsString('Cabin Crew', $html);
        }
    }

    public function test_the_admin_uploads_a_picture_and_creates_a_page(): void
    {
        Storage::fake('public');
        $res = $this->actingAs($this->admin)->post('/admin/content-pages/upload-image', ['file' => UploadedFile::fake()->image('lounge.jpg', 800, 400)], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringStartsWith('content/pages/', $res->json('path'));
        Storage::disk('public')->assertExists($res->json('path'));
        $this->actingAs($this->admin)->post('/admin/content-pages/upload-image', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($this->admin)->post('/admin/content-pages', ['title' => 'Cyclone Updates', 'group' => 'pages', 'path' => '/cyclone'])->assertRedirect();
        $page = ContentPage::where('slug', 'cyclone-updates')->firstOrFail();
        $this->assertFalse($page->is_published, 'a new page starts hidden');
        $this->getJson('/api/pages/cyclone-updates')->assertNotFound();
        $this->actingAs($this->admin)->post('/admin/content-pages', ['title' => 'Cyclone Updates', 'group' => 'pages'])->assertSessionHasErrors('slug');
    }

    public function test_the_dashboard_shows_the_pages_the_client_can_edit(): void
    {
        $this->artisan('content:pages')->assertSuccessful();
        ContentPage::where('slug', 'acmi')->update(['is_published' => false]);

        $this->actingAs($this->admin)->get('/admin/dashboard')->assertOk()
            ->assertSee('Website & App Pages', false)
            ->assertSee('Manage all pages')
            ->assertSee('Recently edited')
            ->assertSee(route('admin.content-pages.index'), false)
            ->assertSee('Hidden');
    }

    public function test_the_admin_pages_need_a_login(): void
    {
        $this->get('/admin/content-pages')->assertRedirect();
        $this->post('/admin/content-pages/upload-image')->assertRedirect();
    }

    public function test_the_pages_overview_and_each_shared_list_point_at_each_other(): void
    {
        $this->artisan('content:pages');

        $this->actingAs($this->admin)->get('/admin/content-pages')
            ->assertOk()->assertSee('Also shows:')->assertSee('Our Fleet');

        $this->actingAs($this->admin)->get('/admin/team-members')
            ->assertOk()->assertSee('This list is part of the pages')->assertSee('Our Team');
    }

    public function test_a_lists_screens_open_inside_the_page_editor_and_stay_there_after_saving(): void
    {
        $member = TeamMember::create(['name' => 'Pat', 'role' => 'Pilot', 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin)->get('/admin/team-members?embed=1')
            ->assertOk()->assertSee('data-embed-hide', false)->assertDontSee('This list is part of the pages');

        $response = $this->actingAs($this->admin)->patch('/admin/team-members/'.$member->id.'/toggle-active', ['embed' => 1]);
        if ($response->isRedirect()) {
            $this->assertStringContainsString('embed=1', $response->headers->get('Location'));
        }

        $this->actingAs($this->admin)->getJson('/admin/content-pages/feed-entries')
            ->assertOk()->assertJsonPath('team.rows.0.title', 'Pat');
    }

    public function test_everything_a_visitor_reads_is_managed_from_the_pages_overview(): void
    {
        $this->artisan('content:pages');

        $this->actingAs($this->admin)->get('/admin/content-pages')
            ->assertOk()->assertSee('Other pages')->assertSee('Agency Resources')->assertSee('Gallery')
            ->assertDontSee('Page Content Library')->assertSee('News &amp; Blog Posts', false);

        $destinations = ContentPage::where('slug', 'destinations')->first();
        $this->actingAs($this->admin)->get('/admin/content-pages/'.$destinations->id.'/edit')
            ->assertOk()->assertSee('Lists on this page')->assertSee('Manage Destinations');

        $this->actingAs($this->admin)->get('/admin/agency-resources')
            ->assertOk()->assertSee('Website &amp; App Pages', false);
    }

    public function test_a_page_made_of_a_list_is_edited_like_any_other_page(): void
    {
        $this->artisan('content:pages');
        $this->actingAs($this->admin)->get('/admin/content-pages')->assertSee('/admin/content-pages/lists/services', false);

        foreach (array_keys(\App\Services\Content\PageUsage::LIST_PAGES) as $key) {
            $this->actingAs($this->admin)->get('/admin/content-pages/lists/'.$key)
                ->assertOk()->assertSee('Website &amp; App Pages', false)->assertSee('Manage all')->assertSee('savePanel()', false);
            $html = $this->actingAs($this->admin)->get('/admin/content-pages/lists/'.$key)->getContent();
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), 'unbalanced divs on '.$key);
        }
        $this->actingAs($this->admin)->get('/admin/content-pages/lists/nope')->assertNotFound();
    }

    public function test_the_overview_is_laid_out_like_the_website_menu(): void
    {
        $this->artisan('content:pages');
        $html = $this->actingAs($this->admin)->get('/admin/content-pages')->assertOk()->getContent();

        foreach (['About Us', 'Agents', 'Destinations', 'Freight', 'Services', 'Travel Info', 'Before You Fly', 'Other pages'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }
        // every page appears somewhere: in the menu or under "Other pages"
        foreach (ContentPage::pluck('slug') as $slug) {
            $this->assertStringContainsString('/admin/content-pages/'.ContentPage::where('slug', $slug)->value('id').'/edit', $html, $slug);
        }
        $this->assertStringContainsString('lists/testimonials', $html);
        $this->assertSame(substr_count($html, '<tr'), substr_count($html, '</tr>'));
        $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'));
        $this->actingAs($this->admin)->get('/admin/content-pages?q=baggage')->assertOk()->assertSee('Baggage')->assertSee('pagesFilter(', false)->assertSee('data-text=', false);
    }

    public function test_a_page_only_opens_the_list_it_shows(): void
    {
        $this->artisan('content:pages');
        $feeds = app(FeedEntries::class)->all();
        $this->assertStringContainsString('only=enhance', $feeds['enhance-journey']['manage']);
        $this->assertStringContainsString('only=milestones', $feeds['our-journey']['manage']);
        $this->assertStringContainsString('only=dangerous-goods', $feeds['dangerous-goods']['manage']);

        $enhance = $this->actingAs($this->admin)->get('/admin/journey?embed=1&only=enhance')->assertOk();
        $enhance->assertSee('Enhance Your Journey Cards')->assertDontSee('Our Journey Timeline Milestones');
        $milestones = $this->actingAs($this->admin)->get('/admin/journey?embed=1&only=milestones')->assertOk();
        $milestones->assertSee('Our Journey Timeline Milestones')->assertDontSee('Enhance Your Journey Cards (');
        $this->actingAs($this->admin)->get('/admin/journey')->assertSee('Our Journey Timeline Milestones')->assertSee('Enhance Your Journey Cards');

        $travel = $this->actingAs($this->admin)->get('/admin/travel-info?only=cards')->assertOk();
        $travel->assertSee('Helpful Information For Your Journey Cards')->assertDontSee('Airport Check-In Times Timetable')->assertDontSee('Dangerous Goods Classifications');
        $this->assertStringNotContainsString('/legal-documents"', $feeds['terms-of-use']['manage']);

        $r = $this->actingAs($this->admin)->from('/admin/journey?embed=1&only=enhance')->post('/admin/journey/update-headings', ['embed' => 1, 'only' => 'enhance', 'our_journey_heading' => 'x', 'enhance_journey_heading' => 'y', 'enhance_journey_subheading' => 'z']);
        $this->assertStringContainsString('only=enhance', (string) $r->headers->get('Location'));
    }
}
