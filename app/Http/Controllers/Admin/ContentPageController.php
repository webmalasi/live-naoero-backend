<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContentPage;
use App\Models\ContentPageRevision;
use App\Services\Content\ContentPages;
use App\Services\Content\FeedEntries;
use App\Services\Content\PageUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Website and app pages (Charter, Freight, Baggage …): text, pictures,
 * cards, lists and questions, edited section by section. A save shows on the
 * website and in the app within seconds; every save can be undone from the
 * page's history.
 */
class ContentPageController extends Controller
{
    public function __construct(private readonly ContentPages $pages) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $all = ContentPage::query()->withCount('sections')->with('editor:id,name')->ordered()->get();
        $needle = mb_strtolower($search);
        $pages = $all->when($search !== '', fn ($c) => $c->filter(fn ($p) => str_contains(mb_strtolower($p->title), $needle) || str_contains($p->slug, Str::slug($search))))
            ->groupBy('group');
        $inMenu = collect(PageUsage::MENU)->flatten(1)->filter(fn ($i) => is_array($i) && $i[0] === 'page')->pluck(1)->all();
        $listsInMenu = collect(PageUsage::MENU)->flatten(1)->filter(fn ($i) => is_array($i) && $i[0] === 'list')->pluck(1)->all();

        return view('admin.content_pages.index', ['groups' => $pages, 'search' => $search, 'bySlug' => $all->keyBy('slug'), 'menu' => PageUsage::MENU,
            'otherPages' => $all->reject(fn ($p) => in_array($p->slug, $inMenu, true)),
            'otherLists' => array_diff_key(PageUsage::LIST_PAGES, array_flip($listsInMenu)), 'lists' => app(\App\Services\Content\PageUsage::class)->listsByPage(), 'listPages' => PageUsage::LIST_PAGES]);
    }

    /** A website page that is only a list: its entries are managed here, like any other page. */
    public function listPage(string $key): View
    {
        $page = PageUsage::LIST_PAGES[$key] ?? abort(404);
        $all = app(FeedEntries::class)->all();

        return view('admin.content_pages.list_page', [
            'title' => $page[0], 'address' => $page[1], 'holds' => $page[2],
            'feedEntries' => collect($page[3])->mapWithKeys(fn ($f) => [$f => $all[$f]])->all(),
            'all' => $all,
        ]);
    }

    public function create(): View
    {
        return view('admin.content_pages.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9-]*$/'],
            'group' => ['required', Rule::in(array_keys(ContentPage::GROUPS))],
            'path' => ['nullable', 'string', 'max:255'],
        ]);
        $slug = Str::slug(($validated['slug'] ?? null) ?: $validated['title']);
        if (ContentPage::where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['slug' => "A page with the address {$slug} already exists."]);
        }
        $page = $this->pages->save(new ContentPage, [
            'slug' => $slug,
            'title' => $validated['title'],
            'group' => $validated['group'],
            'path' => $validated['path'] ?? null,
            'is_published' => false,
            'sections' => [['key' => 'intro', 'type' => 'text', 'heading' => $validated['title']]],
        ], $request->user()?->id, 'Created');
        ActivityLog::log('created', 'content_pages', "Created page '{$page->title}' ({$page->slug})", ['page' => $page->id]);

        return redirect()->route('admin.content-pages.edit', $page)->with('success', 'Page created. It is hidden until you publish it.');
    }

    public function edit(ContentPage $contentPage): View
    {
        $contentPage->load(['sections', 'editor:id,name']);
        $revisions = $contentPage->revisions()->with('user:id,name')->limit(ContentPages::KEEP_REVISIONS)->get(['id', 'content_page_id', 'version', 'user_id', 'note', 'created_at']);

        return view('admin.content_pages.edit', [
            'page' => $contentPage,
            'data' => $this->pages->export($contentPage),
            'revisions' => $revisions,
            'types' => ContentPages::TYPES,
            'channels' => ContentPages::CHANNELS,
            'feeds' => collect(ContentPages::FEEDS)->map(fn ($f) => $f['label'])->all(),
            'groups' => ContentPage::GROUPS,
            'imageBase' => asset('storage'),
            'banner' => $this->pages->payload($contentPage)['banner'] ?? null,
            'icons' => ContentPages::ICONS,
            'feedEntries' => app(FeedEntries::class)->all(),
            'extraLists' => app(\App\Services\Content\PageUsage::class)->extraLists($contentPage->slug),
        ]);
    }

    public function update(Request $request, ContentPage $contentPage): RedirectResponse
    {
        $request->validate([
            'page_json' => ['required', 'string', 'max:2000000'],
            'version' => ['required', 'integer'],
        ]);
        if ((int) $request->input('version') !== $contentPage->version) {
            return back()->with('error', 'Someone else saved this page while you were editing it. Your changes were not saved: reload the page to see the latest version, then make your changes again.');
        }
        $data = json_decode($request->input('page_json'), true);
        if (! is_array($data) || ! isset($data['sections']) || ! is_array($data['sections'])) {
            return back()->with('error', 'The page could not be read. Nothing was saved; please try again.');
        }
        $data['slug'] = $contentPage->slug;
        $this->pages->save($contentPage, $data, $request->user()?->id, Str::limit(trim((string) $request->input('note')), 200, '') ?: null);
        ActivityLog::log('updated', 'content_pages', "Updated page '{$contentPage->title}' to version {$contentPage->version}", ['page' => $contentPage->id, 'version' => $contentPage->version]);

        return redirect()->route('admin.content-pages.edit', $contentPage)
            ->with('success', "Saved. The website and the app show version {$contentPage->version} within a few seconds.");
    }

    public function togglePublished(Request $request, ContentPage $contentPage): RedirectResponse
    {
        $data = $this->pages->export($contentPage);
        $data['is_published'] = ! $contentPage->is_published;
        $this->pages->save($contentPage, $data, $request->user()?->id, $data['is_published'] ? 'Published' : 'Hidden');
        ActivityLog::log('updated', 'content_pages', ($data['is_published'] ? 'Published' : 'Hid')." page '{$contentPage->title}'", ['page' => $contentPage->id]);

        return back()->with('success', $data['is_published']
            ? "'{$contentPage->title}' is published."
            : "'{$contentPage->title}' is hidden: the website and the app show their built-in text.");
    }

    public function restore(Request $request, ContentPage $contentPage, ContentPageRevision $revision): RedirectResponse
    {
        abort_unless($revision->content_page_id === $contentPage->id, 404);
        $this->pages->restore($contentPage, $revision, $request->user()?->id);
        ActivityLog::log('restored', 'content_pages', "Restored page '{$contentPage->title}' to version {$revision->version}", ['page' => $contentPage->id, 'version' => $revision->version]);

        return redirect()->route('admin.content-pages.edit', $contentPage)
            ->with('success', "Version {$revision->version} is back (saved as version {$contentPage->version}).");
    }

    /** The entries of every live list, fetched again after they were changed inside the editor. */
    public function feedEntries(): JsonResponse
    {
        return response()->json(app(FeedEntries::class)->all());
    }

    /** Pictures for sections and cards; returns the stored path. */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:8192']]);
        $path = $request->file('file')->store('content/pages', 'public');
        ActivityLog::log('uploaded', 'content_pages', 'Uploaded page image '.basename($path), ['path' => $path]);

        return response()->json(['path' => $path, 'url' => $this->pages->imageUrl($path)]);
    }
}
