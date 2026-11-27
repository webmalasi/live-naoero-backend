<?php

namespace App\Services\Content;

use App\Models\ContentPage;
use App\Models\ContentPageRevision;
use App\Models\PageBanner;
use App\Services\Logging\ServerLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pages edited in the admin panel and read by the website and the app.
 *
 * A page is an ordered list of sections. The website and the app know each
 * section by its key and show it with their own design; a section they do
 * not know is shown by its type. Text is plain, with a few marks:
 * a blank line starts a paragraph, lines starting with "-", "*" or "•" make
 * a list ("1." a numbered one), **bold**, [label](https://link), and web
 * and email addresses become links. `body_html` and `text_html` carry it
 * ready to show.
 *
 * Every save keeps the page as it was (revisions), and the page's version
 * changes, so `GET /api/content/versions` tells the website and the app
 * what to fetch again.
 */
class ContentPages
{
    /** Section types, with what the admin panel calls them. */
    public const TYPES = [
        'text' => 'Text',
        'image_text' => 'Image and text',
        'cards' => 'Cards',
        'list' => 'List',
        'steps' => 'Steps',
        'faq' => 'Questions and answers',
        'table' => 'Table',
        'links' => 'Links and buttons',
        'notice' => 'Notice',
        'gallery' => 'Images',
        'stats' => 'Figures',
        'contacts' => 'Contacts',
        'feed' => 'Live list from another admin screen',
    ];

    /** What an item (a card, a list entry, a question, a row …) can have. */
    public const ITEM_FIELDS = ['slug', 'title', 'subtitle', 'text', 'value', 'icon', 'image', 'gallery', 'link_label', 'link_url', 'list', 'cells'];

    /**
     * Lists kept in their own admin screens that a page can show. The page
     * points to the endpoint; the website and the app already read it.
     */
    /** Icons an entry can use; the website draws the same set (components/PageIcon.tsx). */
    public const ICONS = ['plane', 'plane-takeoff', 'plane-landing', 'globe', 'map', 'map-pin', 'compass', 'navigation', 'luggage', 'briefcase', 'package', 'truck', 'ship', 'anchor', 'users', 'user', 'user-check', 'baby', 'accessibility', 'heart', 'heart-handshake', 'hand-helping', 'star', 'award', 'badge-check', 'shield', 'shield-check', 'lock', 'key', 'clock', 'calendar', 'calendar-days', 'ticket', 'credit-card', 'wallet', 'dollar-sign', 'receipt', 'phone', 'phone-call', 'mail', 'message-circle', 'headphones', 'life-buoy', 'info', 'circle-help', 'circle-check', 'circle-dot', 'check', 'file-text', 'file-chart-column', 'clipboard-list', 'utensils-crossed', 'coffee', 'wine', 'wifi', 'tv', 'bed-double', 'car', 'sun', 'tree-palm', 'waves', 'mountain', 'camera', 'music', 'sparkles', 'leaf', 'building-2', 'landmark'];

    public const FEEDS = [
        'team' => ['label' => 'Our Team members', 'endpoint' => '/api/team', 'collection' => 'team'],
        'check-in-times' => ['label' => 'Check-in times', 'endpoint' => '/api/travel-info/check-in-times', 'collection' => 'travel-info'],
        'dangerous-goods' => ['label' => 'Dangerous goods items', 'endpoint' => '/api/travel-info/dangerous-goods', 'collection' => 'travel-info'],
        'terms-of-use' => ['label' => 'Terms of Use (legal document)', 'endpoint' => '/api/terms-of-use', 'collection' => 'legal'],
        'conditions-of-carriage' => ['label' => 'Conditions of Carriage (legal document)', 'endpoint' => '/api/conditions-of-carriage', 'collection' => 'legal'],
        'flight-schedules' => ['label' => 'Flight schedule table', 'endpoint' => '/api/flight-schedules', 'collection' => 'flight-schedules'],
        'specials' => ['label' => 'Special offers', 'endpoint' => '/api/specials', 'collection' => 'specials'],
        'careers' => ['label' => 'Job openings', 'endpoint' => '/api/careers', 'collection' => 'careers'],
        'faqs' => ['label' => 'FAQs', 'endpoint' => '/api/faqs', 'collection' => 'faqs'],
        'destinations' => ['label' => 'Destinations', 'endpoint' => '/api/destinations', 'collection' => 'destinations'],
        'our-journey' => ['label' => 'Our Journey timeline', 'endpoint' => '/api/our-journey', 'collection' => 'journey'],
        'why-fly' => ['label' => 'Why Fly With Us cards', 'endpoint' => '/api/why-fly', 'collection' => 'why-fly'],
        'enhance-journey' => ['label' => 'Enhance Your Journey cards', 'endpoint' => '/api/enhance-journey', 'collection' => 'journey'],
        'fleet' => ['label' => 'Our Fleet', 'endpoint' => '/api/fleet', 'collection' => 'fleet'],
        'agency-contacts' => ['label' => 'Travel agency contacts', 'endpoint' => '/api/agency-contacts', 'collection' => 'agencies'],
    ];

    /**
     * Other admin-managed content, by the tables it lives in and the
     * endpoints that serve it, so clients can tell when to fetch it again.
     */
    public const COLLECTIONS = [
        'global' => [['site_settings'], ['/global', '/global-settings', '/settings', '/header']],
        'banners' => [['page_banners'], ['/banners', '/page-banners']],
        'notices' => [['notices'], ['/notice-bar', '/notices']],
        'team' => [['team_members'], ['/team']],
        'careers' => [['careers'], ['/careers']],
        'specials' => [['special_offers'], ['/specials']],
        'faqs' => [['faqs'], ['/faqs']],
        'flight-schedules' => [['flight_schedules'], ['/flight-schedules']],
        'destinations' => [['destinations'], ['/destinations']],
        'fleet' => [['fleets'], ['/fleet']],
        'services' => [['airline_services'], ['/services']],
        'travel-info' => [['travel_info_cards', 'check_in_times', 'dangerous_goods_items'], ['/travel-info', '/check-in-times', '/dangerous-goods']],
        'legal' => [['legal_documents'], ['/privacy-policy', '/privacy', '/terms-of-use', '/terms', '/terms-and-conditions', '/conditions-of-carriage', '/legal']],
        'posts' => [['posts'], ['/blogs', '/news', '/posts']],
        'testimonials' => [['testimonials'], ['/testimonials']],
        'why-fly' => [['why_fly_features'], ['/why-fly', '/why-fly-with-us']],
        'journey' => [['our_journey_milestones', 'enhance_journey_items'], ['/our-journey', '/enhance-journey', '/journey']],
        'gallery' => [['gallery_items'], ['/gallery']],
        'agencies' => [['agency_contacts', 'agency_resources'], ['/agency-contacts', '/agency-resources', '/travel-agency-resources']],
        'shop' => [['shop_products', 'shop_banners'], ['/shop']],
    ];

    /** Where a section is shown: some blocks belong to one design only. */
    public const CHANNELS = ['both' => 'Website and app', 'website' => 'Website only', 'app' => 'App only'];

    public const KEEP_REVISIONS = 50;

    /** @var Collection<int, PageBanner>|null */
    private ?Collection $banners = null;

    // ---------------------------------------------------------------- reading

    /**
     * The page as the website and the app get it. With [channel] ("website"
     * or "app") only the sections shown there.
     */
    public function payload(ContentPage $page, ?string $channel = null): array
    {
        $page->loadMissing('sections');
        $sections = [];
        foreach ($page->sections as $section) {
            $where = $section->show_on ?: 'both';
            if ($section->is_visible && (! $channel || $where === 'both' || $where === $channel)) {
                $sections[] = $this->sectionPayload($section);
            }
        }
        $data = [
            'slug' => $page->slug,
            'title' => $page->title,
            'group' => $page->group,
            'path' => $page->path,
            'summary' => $page->summary,
            'seo' => [
                'title' => $page->seo_title ?: $page->title,
                'description' => $page->seo_description ?: $page->summary,
            ],
            'banner' => $this->bannerPayload($page->banner_key ?: $page->slug),
            'sections' => $sections,
            'updated_at' => $page->updated_at?->toIso8601String(),
        ];

        return ['version' => $this->hash($data)] + $data;
    }

    /** Short pages list: what exists and its version. */
    public function listing(?string $group = null): array
    {
        $pages = ContentPage::query()->published()->with('sections')->ordered()
            ->when($group, fn ($q) => $q->where('group', $group))->get();

        return $pages->map(function (ContentPage $page) {
            $full = $this->payload($page);

            return [
                'slug' => $page->slug,
                'title' => $page->title,
                'group' => $page->group,
                'path' => $page->path,
                'summary' => $page->summary,
                'version' => $full['version'],
                'updated_at' => $full['updated_at'],
                'endpoint' => '/api/pages/'.$page->slug,
            ];
        })->values()->all();
    }

    /**
     * A version for every page and every other admin-managed list. A client
     * keeps the versions it has shown and fetches again whatever changed.
     */
    public function versions(): array
    {
        $pages = [];
        foreach (ContentPage::query()->published()->with('sections')->get() as $page) {
            $pages[$page->slug] = $this->payload($page)['version'];
        }
        ksort($pages);
        $collections = [];
        foreach (self::COLLECTIONS as $name => [$tables, $paths]) {
            $collections[$name] = ['version' => $this->tableStamp($tables), 'paths' => $paths];
        }

        return ['pages' => $pages, 'collections' => $collections, 'version' => $this->hash([$pages, $collections])];
    }

    private function sectionPayload($section): array
    {
        $items = [];
        foreach ($section->items ?? [] as $item) {
            if (is_array($item) && ($clean = $this->itemPayload($item))) {
                $items[] = $clean;
            }
        }
        $feed = null;
        if ($section->type === 'feed' && isset(self::FEEDS[$section->feed])) {
            $feed = ['source' => $section->feed] + array_intersect_key(self::FEEDS[$section->feed], ['endpoint' => 1, 'collection' => 1]);
        }

        return [
            'key' => $section->key,
            'type' => $section->type,
            'show_on' => $section->show_on ?: 'both',
            'heading' => $section->heading,
            'subheading' => $section->subheading,
            'body' => $section->body,
            'body_html' => $this->html($section->body),
            'image' => $this->imageUrl($section->image),
            'image_alt' => $section->image_alt,
            'items' => $items,
            'columns' => array_values(array_filter($section->columns ?? [], fn ($c) => is_string($c) && $c !== '')),
            'button' => $section->button_label || $section->button_url
                ? ['label' => $section->button_label, 'url' => $this->safeUrl($section->button_url)]
                : null,
            'note' => $section->note,
            'note_html' => $this->html($section->note),
            'feed' => $feed,
        ];
    }

    private function itemPayload(array $item): ?array
    {
        $out = [];
        foreach (self::ITEM_FIELDS as $field) {
            $value = $item[$field] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            $out[$field] = match ($field) {
                'image' => $this->imageUrl($value),
                'gallery' => array_values(array_filter(array_map(fn ($i) => is_string($i) ? $this->imageUrl($i) : null, (array) $value))),
                'link_url' => $this->safeUrl($value),
                'list', 'cells' => array_values(array_map('strval', (array) $value)),
                default => (string) $value,
            };
        }
        if (isset($out['text'])) {
            $out['text_html'] = $this->html($out['text']);
        }

        return $out ?: null;
    }

    private function bannerPayload(string $key): ?array
    {
        $this->banners ??= PageBanner::query()->active()->get();
        $normalized = strtolower(trim(str_replace(['/', '_'], ['', '-'], $key)));
        $banner = $this->banners->first(fn (PageBanner $b) => in_array($b->page_key, [$key, $normalized], true)
            || in_array($b->page_route, [$key, '/'.ltrim($key, '/')], true));
        if (! $banner) {
            return null;
        }

        return [
            'page_key' => $banner->page_key,
            'heading' => $banner->banner_heading,
            'description' => $banner->banner_description,
            'badge_text' => $banner->banner_badge_text,
            'image' => $banner->banner_image_url,
            'button_text' => $banner->banner_button_text,
            'button_link' => $banner->banner_button_link,
        ];
    }

    /** How many rows and the last change, across [tables]. */
    private function tableStamp(array $tables): string
    {
        $parts = [];
        foreach ($tables as $table) {
            try {
                $row = DB::table($table)->selectRaw('count(*) as n, max(updated_at) as at')->first();
                $parts[] = $table.':'.($row->n ?? 0).':'.($row->at ?? '');
            } catch (\Throwable) {
                $parts[] = $table.':missing';
            }
        }

        return substr(sha1(implode('|', $parts)), 0, 16);
    }

    private function hash(mixed $data): string
    {
        return substr(sha1(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);
    }

    // ------------------------------------------------------------ formatting

    /** An uploaded path, a site address (/assets/…) or a full URL, as a URL. */
    public function imageUrl(?string $image): ?string
    {
        $image = trim((string) $image);
        if ($image === '') {
            return null;
        }
        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }
        if (str_starts_with($image, '/')) {
            return url($image);
        }

        return asset('storage/'.ltrim($image, '/'));
    }

    /** Only links that are safe to put on a page. */
    public function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $url)) {
            return $url;
        }

        return null;
    }

    /** The plain text with its marks, as HTML (always escaped). */
    public function html(?string $text): ?string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return null;
        }
        $out = [];
        foreach (preg_split("/\n\s*\n/", $text) as $block) {
            $lines = array_values(array_filter(array_map('rtrim', explode("\n", $block)), fn ($l) => trim($l) !== ''));
            $paragraph = [];
            $list = null;
            $flushParagraph = function () use (&$paragraph, &$out) {
                if ($paragraph) {
                    $out[] = '<p>'.implode('<br>', $paragraph).'</p>';
                    $paragraph = [];
                }
            };
            $flushList = function () use (&$list, &$out) {
                if ($list) {
                    $out[] = "<{$list['tag']}>".implode('', array_map(fn ($i) => "<li>{$i}</li>", $list['items']))."</{$list['tag']}>";
                    $list = null;
                }
            };
            foreach ($lines as $line) {
                if (preg_match('/^\s*(?:[-*•]|(\d+)[.)])\s+(.*)$/u', $line, $m)) {
                    $flushParagraph();
                    $tag = $m[1] !== '' ? 'ol' : 'ul';
                    if ($list && $list['tag'] !== $tag) {
                        $flushList();
                    }
                    $list ??= ['tag' => $tag, 'items' => []];
                    $list['items'][] = $this->inline($m[2]);
                } else {
                    $flushList();
                    $paragraph[] = $this->inline(trim($line));
                }
            }
            $flushParagraph();
            $flushList();
        }

        return implode("\n", $out);
    }

    private function inline(string $text): string
    {
        $links = [];
        // [label](url) first, kept aside so the address finder leaves it alone.
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) use (&$links) {
            $url = $this->safeUrl($m[2]);
            if (! $url) {
                return $m[1];
            }
            $links[] = '<a href="'.e($url).'"'.(Str::startsWith($url, 'http') ? ' target="_blank" rel="noopener"' : '').'>'.e($m[1]).'</a>';

            return "\u{0}".(count($links) - 1)."\u{0}";
        }, $text);
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*(?!\*)/u', '<strong>$1</strong>', $html);
        $html = preg_replace('/(?<![\w\/])__(.+?)__(?![\w])/u', '<u>$1</u>', $html);
        $html = preg_replace('/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/u', '<em>$1</em>', $html);
        $html = preg_replace_callback('#(?<![\w/"=])(https?://[^\s<]+[^\s<.,;:!?)\]])#u', fn ($m) => '<a href="'.$m[1].'" target="_blank" rel="noopener">'.$m[1].'</a>', $html);
        $html = preg_replace('/(?<![\w.\/:@-])([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})/u', '<a href="mailto:$1">$1</a>', $html);

        return preg_replace_callback("/\u{0}(\d+)\u{0}/u", fn ($m) => $links[(int) $m[1]] ?? '', $html);
    }

    // ---------------------------------------------------------------- saving

    /** The page as a plain array: for revisions, exports and the seed files. */
    public function export(ContentPage $page): array
    {
        $page->loadMissing('sections');

        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'group' => $page->group,
            'path' => $page->path,
            'summary' => $page->summary,
            'seo_title' => $page->seo_title,
            'seo_description' => $page->seo_description,
            'banner_key' => $page->banner_key,
            'is_published' => $page->is_published,
            'sort_order' => $page->sort_order,
            'sections' => $page->sections->map(fn ($s) => [
                'key' => $s->key,
                'type' => $s->type,
                'heading' => $s->heading,
                'subheading' => $s->subheading,
                'body' => $s->body,
                'image' => $s->image,
                'image_alt' => $s->image_alt,
                'items' => $s->items ?? [],
                'columns' => $s->columns ?? [],
                'button_label' => $s->button_label,
                'button_url' => $s->button_url,
                'note' => $s->note,
                'feed' => $s->feed,
                'show_on' => $s->show_on ?: 'both',
                'is_visible' => $s->is_visible,
            ])->values()->all(),
        ];
    }

    /**
     * Saves [data] (the export shape) as [page]'s content, keeping the page
     * as it was in its history. Returns the saved page.
     */
    public function save(ContentPage $page, array $data, ?int $userId = null, ?string $note = null): ContentPage
    {
        $clean = $this->clean($data);

        return DB::transaction(function () use ($page, $clean, $userId, $note) {
            $exists = $page->exists;
            if ($exists) {
                $before = $this->export($page);
                ContentPageRevision::create([
                    'content_page_id' => $page->id,
                    'version' => $page->version,
                    'snapshot' => $before,
                    'user_id' => $userId,
                    'note' => $note,
                ]);
            }
            $page->fill(collect($clean)->except('sections')->all());
            $page->version = $exists ? $page->version + 1 : 1;
            $page->updated_by = $userId;
            $page->save();

            $keep = [];
            foreach ($clean['sections'] as $position => $section) {
                $row = $page->sections()->updateOrCreate(['key' => $section['key']], $section + ['position' => $position]);
                $keep[] = $row->id;
            }
            $page->sections()->whereNotIn('id', $keep ?: [0])->delete();
            $page->touch();

            $old = $page->revisions()->skip(self::KEEP_REVISIONS)->take(PHP_INT_MAX)->pluck('id');
            if ($old->isNotEmpty()) {
                ContentPageRevision::whereIn('id', $old)->delete();
            }
            app(ServerLog::class)->log('info', 'content', "Page {$page->slug} saved as version {$page->version}", detail: [
                'slug' => $page->slug, 'version' => $page->version, 'user_id' => $userId,
                'sections' => count($clean['sections']), 'note' => $note,
            ]);

            return $page->unsetRelation('sections')->unsetRelation('revisions');
        });
    }

    /** Puts [page] back as it was at [revision] (itself kept in the history). */
    public function restore(ContentPage $page, ContentPageRevision $revision, ?int $userId = null): ContentPage
    {
        $snapshot = $revision->snapshot;
        $snapshot['slug'] = $page->slug;

        return $this->save($page, $snapshot, $userId, "Restored version {$revision->version}");
    }

    /** Admin-panel or seed input, trimmed to what a page can hold. */
    public function clean(array $data): array
    {
        // Kept as typed: everything is escaped when shown (body_html).
        $str = fn ($v, $max = null) => ($v = trim(str_replace(["\r\n", "\r"], "\n", (string) ($v ?? '')))) === '' ? null : ($max ? Str::limit($v, $max, '') : $v);
        $page = [
            'slug' => Str::slug((string) ($data['slug'] ?? '')),
            'title' => $str($data['title'] ?? null, 255) ?? 'Untitled page',
            'group' => array_key_exists($data['group'] ?? '', ContentPage::GROUPS) ? $data['group'] : 'pages',
            'path' => $str($data['path'] ?? null, 255),
            'summary' => $str($data['summary'] ?? null),
            'seo_title' => $str($data['seo_title'] ?? null, 255),
            'seo_description' => $str($data['seo_description'] ?? null, 500),
            'banner_key' => $str($data['banner_key'] ?? null, 120),
            'is_published' => filter_var($data['is_published'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        $sections = [];
        $keys = [];
        foreach (array_values((array) ($data['sections'] ?? [])) as $i => $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = array_key_exists($section['type'] ?? '', self::TYPES) ? $section['type'] : 'text';
            $key = Str::slug((string) ($section['key'] ?? '')) ?: Str::slug((string) ($section['heading'] ?? '')) ?: 'section-'.($i + 1);
            $base = $key;
            for ($n = 2; in_array($key, $keys, true); $n++) {
                $key = "{$base}-{$n}";
            }
            $keys[] = $key;
            $items = [];
            foreach (array_values((array) ($section['items'] ?? [])) as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $one = [];
                foreach (self::ITEM_FIELDS as $field) {
                    $value = $item[$field] ?? null;
                    if (in_array($field, ['list', 'cells', 'gallery'], true)) {
                        $value = is_string($value) ? preg_split('/\n/', $value) : (array) ($value ?? []);
                        $value = array_values(array_filter(array_map(fn ($v) => $str($v), $value), fn ($v) => $v !== null || $field === 'cells'));
                        if ($field === 'cells') {
                            $value = array_map(fn ($v) => $v ?? '', $value);
                        }
                        if ($value) {
                            $one[$field] = $value;
                        }
                    } elseif (($v = $str($value)) !== null) {
                        $one[$field] = $v;
                    }
                }
                if ($one) {
                    $items[] = $one;
                }
            }
            $columns = is_string($section['columns'] ?? null) ? explode('|', $section['columns']) : (array) ($section['columns'] ?? []);
            $sections[] = [
                'key' => Str::limit($key, 120, ''),
                'type' => $type,
                'heading' => $str($section['heading'] ?? null, 255),
                'subheading' => $str($section['subheading'] ?? null),
                'body' => $str($section['body'] ?? null),
                'image' => $str($section['image'] ?? null, 1000),
                'image_alt' => $str($section['image_alt'] ?? null, 255),
                'items' => $items,
                'columns' => array_values(array_filter(array_map(fn ($c) => $str($c), $columns))),
                'button_label' => $str($section['button_label'] ?? null, 255),
                'button_url' => $str($section['button_url'] ?? null, 1000),
                'note' => $str($section['note'] ?? null),
                'feed' => $type === 'feed' && array_key_exists($section['feed'] ?? '', self::FEEDS) ? $section['feed'] : null,
                'show_on' => array_key_exists($section['show_on'] ?? '', self::CHANNELS) ? $section['show_on'] : 'both',
                'is_visible' => filter_var($section['is_visible'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        $page['sections'] = $sections;

        return $page;
    }
}
