<?php

namespace App\Console\Commands;

use App\Models\ContentPage;
use App\Models\Destination;
use App\Models\LegalDocument;
use App\Models\Post;
use App\Models\WhyFlyFeature;
use App\Services\Content\ContentPages;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Adds the editable pages with the text the website had when they were
 * made editable (database/seeders/content/pages/*.json).
 *
 * Pages that do not exist yet are added. A page that exists is never
 * overwritten: when nobody has edited it yet it is refreshed to the current
 * original text once (SYNC), and when it was edited only what is missing is
 * added (new sections, pictures that were never uploaded, empty lists), once
 * per SYNC. --reset puts the named pages back to the original text (the
 * edited version stays in the page's history).
 *
 * Images named "seed:folder/file.jpg" are copied to the public disk under
 * content/seed/ the first time.
 */
class SeedContentPages extends Command
{
    /** Bump when the original pages in database/seeders/content/pages changed. */
    private const SYNC = 'sync-2026-10-30b';

    protected $signature = 'content:pages
        {--reset=* : Slugs of pages to put back to the original text}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Add the editable website and app pages that are missing';

    public function handle(ContentPages $pages): int
    {
        $dir = database_path('seeders/content/pages');
        $reset = array_map('strtolower', (array) $this->option('reset'));
        $dry = (bool) $this->option('dry-run');
        $added = $restored = $kept = $refreshed = $merged = 0;

        foreach (File::glob($dir.'/*.json') as $file) {
            $data = json_decode(File::get($file), true);
            if (! is_array($data) || empty($data['slug'])) {
                $this->error('Not a page: '.basename($file));

                return self::FAILURE;
            }
            $page = ContentPage::query()->where('slug', $data['slug'])->first();
            $resetting = $page && in_array($data['slug'], $reset, true);
            if ($page && ! $resetting) {
                $done = $page->revisions()->where('note', 'like', '%'.self::SYNC.'%')->exists();
                if ($done) {
                    $kept++;

                    continue;
                }
                $seeded = $this->withImages($data);
                if ((int) $page->version <= 1) {
                    $this->line('Refresh: '.$data['slug']);
                    if (! $dry) {
                        $pages->save($page, $seeded, null, 'Original text, '.self::SYNC);
                    }
                    $refreshed++;

                    continue;
                }
                [$combined, $changed] = $this->merge($pages->export($page), $seeded);
                if ($changed) {
                    $this->line('Add missing: '.$data['slug']);
                    if (! $dry) {
                        $pages->save($page, $combined, null, 'Added what was missing, '.self::SYNC);
                    }
                    $merged++;
                } else {
                    $kept++;
                }

                continue;
            }
            $this->line(($resetting ? 'Reset: ' : 'Add: ').$data['slug']);
            if ($dry) {
                continue;
            }
            $data = $this->withImages($data);
            $saved = $pages->save($page ?? new ContentPage, $data, null, ($resetting ? 'Reset to the original text, ' : 'Original text, ').self::SYNC);
            if (! $page) {
                // A new page has no earlier version to keep; this marks it as up to date.
                $saved->revisions()->create(['version' => 0, 'snapshot' => $pages->export($saved), 'note' => 'Original text, '.self::SYNC]);
            }
            $resetting ? $restored++ : $added++;
        }
        $this->info("Pages added: {$added}, reset: {$restored}, refreshed (never edited): {$refreshed}, completed (edited, kept): {$merged}, unchanged: {$kept}.");
        $this->fixConditionsDefinitions($dry);
        $this->fillDestinations($dry);
        $this->fillDestinationPictures($dry);
        $this->fillWhyFlyPictures($dry);
        $this->addNews($dry);

        return self::SUCCESS;
    }

    /**
     * Adds to an edited page only what the admin panel does not have yet:
     * sections that are missing (placed after the section before them), and
     * for sections that exist the picture, picture text, list, column names,
     * button and note when empty. Nothing that holds text is replaced.
     *
     * @return array{0: array, 1: bool}
     */
    private function merge(array $current, array $seed): array
    {
        $changed = false;
        $out = $current['sections'];
        $filled = fn ($v) => ! ($v === null || $v === '' || $v === []);
        $previous = null;

        foreach ($seed['sections'] as $section) {
            $keys = array_column($out, 'key');
            $at = array_search($section['key'], $keys, true);
            if ($at === false) {
                $after = $previous !== null ? array_search($previous, $keys, true) : false;
                array_splice($out, $after === false ? count($out) : $after + 1, 0, [$section]);
                $changed = true;
                $previous = $section['key'];

                continue;
            }
            foreach (['image', 'image_alt', 'columns', 'button_label', 'button_url', 'note'] as $field) {
                if (! $filled($out[$at][$field] ?? null) && $filled($section[$field] ?? null)) {
                    $out[$at][$field] = $section[$field];
                    $changed = true;
                }
            }
            $items = $out[$at]['items'] ?? [];
            if (! $filled($items) && $filled($section['items'] ?? null)) {
                $out[$at]['items'] = $section['items'];
                $changed = true;
            } else {
                foreach ($items as $j => $item) {
                    $original = $section['items'][$j] ?? null;
                    if (! $original || ($item['title'] ?? null) !== ($original['title'] ?? null)) {
                        continue;
                    }
                    foreach (['image', 'gallery', 'icon', 'link_url'] as $field) {
                        if (! $filled($item[$field] ?? null) && $filled($original[$field] ?? null)) {
                            $out[$at]['items'][$j][$field] = $original[$field];
                            $changed = true;
                        }
                    }
                }
            }
            $previous = $section['key'];
        }

        $current['sections'] = array_values($out);

        return [$current, $changed];
    }

    /**
     * The Conditions of Carriage kept the website's code in section 19 (the
     * list of terms): the definitions are put back as readable text, in the
     * list of sections and in the page's formatted version.
     */
    private function fixConditionsDefinitions(bool $dry): void
    {
        $file = database_path('seeders/content/conditions-definitions.json');
        $definitions = is_file($file) ? json_decode(File::get($file), true) : null;
        $doc = LegalDocument::findBySlug('conditions-of-carriage');
        if (! $doc || ! is_array($definitions)) {
            return;
        }

        $sections = $doc->sections ?? [];
        $fixed = false;
        $intro = 'The singular includes the plural and vice versa. The titles of Paragraphs and the marginal headings are inserted for convenient reference only and are not to be used in ascertaining the meaning of any of the provisions of these Conditions.';
        foreach ($sections as $i => $section) {
            if (str_starts_with((string) ($section['heading'] ?? ''), 'Section 19') && str_contains((string) ($section['content'] ?? ''), '], [')) {
                $lines = array_map(fn ($d) => $d[0].' - '.$d[1], $definitions);
                $sections[$i]['content'] = $intro."\n\n".implode("\n\n", $lines);
                $fixed = true;
            }
        }
        $html = (string) $doc->content;
        $start = strpos($html, '<h2>Section 19.');
        if ($start !== false && str_contains(substr($html, $start), '], [')) {
            $rows = '';
            foreach ($definitions as [$term, $meaning]) {
                $rows .= '<tr><td class="border border-slate-200 p-2 font-semibold">'.e($term).'</td><td class="border border-slate-200 p-2">'.e($meaning).'</td></tr>'."\n";
            }
            $html = substr($html, 0, $start)
                .'<h2>Section 19. Terms Used In These Conditions</h2>'."\n"
                .'<p>'.e($intro).'</p>'."\n"
                .'<table class="table-auto w-full border border-slate-200">'."\n"
                .'<thead><tr><th class="border border-slate-200 p-2 font-bold">Terms</th><th class="border border-slate-200 p-2 font-bold">Meaning</th></tr></thead>'."\n"
                .'<tbody>'."\n".$rows.'</tbody></table>';
            $fixed = true;
        }
        if (! $fixed) {
            return;
        }
        $this->line('Conditions of Carriage: definitions put back as text');
        if (! $dry) {
            $doc->update(['sections' => $sections, 'content' => $html]);
        }
    }

    /**
     * The destination detail text the website used to hold in a code file
     * (background sections, detailed information, reasons to visit): added
     * to a destination only where the admin panel has nothing yet.
     */
    private function fillDestinations(bool $dry): void
    {
        $file = database_path('seeders/content/destinations.json');
        $rows = is_file($file) ? json_decode(File::get($file), true) : null;
        $filled = 0;
        foreach (is_array($rows) ? $rows : [] as $slug => $fields) {
            $destination = Destination::query()->where('slug', $slug)->first();
            if (! $destination) {
                continue;
            }
            $missing = [];
            foreach ($fields as $field => $value) {
                if (empty($destination->{$field})) {
                    $missing[$field] = $value;
                }
            }
            if ($missing) {
                $this->line("Destination {$slug}: ".implode(', ', array_keys($missing)));
                if (! $dry) {
                    $destination->update($missing);
                }
                $filled++;
            }
        }
        $this->info("Destinations filled in: {$filled}.");
    }

    /**
     * The pictures of a destination's "Why visit" cards used to be built into
     * the website. They are put on each card that has none, so they are edited
     * with the destination; the card's place decides which picture (the same
     * order the website used).
     */
    private function fillDestinationPictures(bool $dry): void
    {
        $done = 0;
        foreach (Destination::query()->get() as $destination) {
            $cards = $destination->why_visit;
            if (! is_array($cards)) {
                continue;
            }
            $changed = false;
            foreach ($cards as $i => $card) {
                if (! is_array($card) || ! empty($card['sectionBreak']) || ! empty($card['image'])) {
                    continue;
                }
                $stored = $dry ? null : $this->copySeed('seed:destinations/why-visit-'.(($i % 7) + 1).'.png');
                if ($stored) {
                    $cards[$i]['image'] = url('storage/'.$stored);
                    $changed = true;
                } elseif ($dry) {
                    $changed = true;
                }
            }
            if ($changed) {
                $this->line("Destination {$destination->slug}: pictures for the Why visit cards");
                if (! $dry) {
                    $destination->update(['why_visit' => $cards]);
                }
                $done++;
            }
        }
        $this->info("Destinations with picture added to the Why visit cards: {$done}.");
    }

    /**
     * The photos of the About page's Why Fly cards were built into the website.
     * They are put on the entries that have no picture, so they can be seen
     * and replaced. Screens that show the icon instead are not affected.
     */
    private function fillWhyFlyPictures(bool $dry): void
    {
        $done = 0;
        foreach (WhyFlyFeature::query()->orderBy('sort_order')->orderBy('id')->take(4)->get()->values() as $i => $feature) {
            if ($feature->icon_image) {
                continue;
            }
            if (! $dry) {
                $stored = $this->copySeed('seed:about-new/flywithus'.($i + 1).'.png');
                if ($stored) {
                    $feature->update(['icon_image' => '/storage/'.$stored]);
                }
            }
            $done++;
        }
        $this->info("Why Fly entries given their picture: {$done}.");
    }

    /**
     * The News Hub "Media Release" posts the website held in a code file
     * (facebookPosts.ts): added as ordinary news posts, edited under Posts.
     * A post whose address already exists is left alone.
     */
    private function addNews(bool $dry): void
    {
        $file = database_path('seeders/content/news.json');
        $rows = is_file($file) ? json_decode(File::get($file), true) : null;
        $added = 0;
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (Post::query()->where('slug', $row['slug'])->exists()) {
                continue;
            }
            $this->line("News: {$row['slug']}");
            if (! $dry) {
                $image = $this->withImages(['sections' => [['image' => $row['image'] ?? null]]])['sections'][0]['image'];
                Post::create([
                    'type' => 'news',
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'category' => $row['category'] ?? 'Media Release',
                    'excerpt' => $row['excerpt'] ?? null,
                    'content' => $row['content'] ?? null,
                    'image' => $image,
                    'author' => $row['author'] ?? 'Naoero Airlines',
                    'published_at' => now()->subDays((int) ($row['days_ago'] ?? 1)),
                    'is_published' => true,
                ]);
            }
            $added++;
        }
        $this->info("News posts added: {$added}.");
    }

    /** "seed:folder/file.jpg" becomes a file on the public disk; other text is returned as it is. */
    private function copySeed(?string $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, 'seed:')) {
            return $value;
        }
        $relative = ltrim(substr($value, 5), '/');
        $source = database_path('seeders/content/images/'.$relative);
        $target = 'content/seed/'.$relative;
        if (! Storage::disk('public')->exists($target)) {
            if (! is_file($source)) {
                $this->warn("Missing seed image {$relative}; the page keeps its built-in picture.");

                return null;
            }
            Storage::disk('public')->put($target, File::get($source));
        }

        return $target;
    }

    /** "seed:charter/hero.jpg" becomes a file on the public disk. */
    private function withImages(array $data): array
    {
        $copy = fn (?string $value) => $this->copySeed($value);
        foreach ($data['sections'] ?? [] as $i => $section) {
            $data['sections'][$i]['image'] = $copy($section['image'] ?? null);
            foreach ($section['items'] ?? [] as $j => $item) {
                if (isset($item['image'])) {
                    $data['sections'][$i]['items'][$j]['image'] = $copy($item['image']);
                }
                if (isset($item['gallery'])) {
                    $data['sections'][$i]['items'][$j]['gallery'] = array_values(array_filter(array_map($copy, (array) $item['gallery'])));
                }
                if (isset($item['link_url']) && str_starts_with((string) $item['link_url'], 'seed:')) {
                    $stored = $copy($item['link_url']);
                    $data['sections'][$i]['items'][$j]['link_url'] = $stored ? url('storage/'.$stored) : null;
                }
            }
        }

        return $data;
    }
}
