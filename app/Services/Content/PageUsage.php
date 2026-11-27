<?php

namespace App\Services\Content;

use App\Models\ContentPage;

/**
 * Which website and app pages show a list that is edited on its own admin
 * screen (Fleet, Team, FAQs …), so each of those screens can say so and link
 * to the pages, and the pages overview can link to the lists.
 */
class PageUsage
{
    /** Admin route prefix → the live lists (see ContentPages::FEEDS) edited on that screen. */
    public const SCREENS = [
        'admin.team-members' => ['team'],
        'admin.careers' => ['careers'],
        'admin.journey' => ['our-journey', 'enhance-journey'],
        'admin.why-fly' => ['why-fly'],
        'admin.specials' => ['specials'],
        'admin.faqs' => ['faqs'],
        'admin.fleets' => ['fleet'],
        'admin.schedules' => ['flight-schedules'],
        'admin.destinations' => ['destinations'],
        'admin.agencies' => ['agency-contacts'],
        'admin.travel-info' => ['check-in-times', 'dangerous-goods'],
        'admin.legal-documents' => ['terms-of-use', 'conditions-of-carriage'],
    ];

    /** Other admin screens that are part of "Website & App Pages" (their lists show on pages without a live-list section). */
    public const OTHER_SCREENS = ['admin.services', 'admin.gallery', 'admin.testimonials', 'admin.agency-resources'];

    /**
     * Lists a page shows that are not a live-list section of it: page slug → [label, manage route, add route].
     * They are managed from the page, in a panel.
     */
    public const PAGE_LISTS = [
        'destinations' => [['Destinations', 'admin.destinations.index', 'admin.destinations.create']],
        'our-fleet' => [['Aircraft Fleet', 'admin.fleets.index', 'admin.fleets.create']],
        'travel-agency-contacts' => [['Travel Agency Contacts', 'admin.agencies.index', 'admin.agencies.create']],
        'home' => [
            ['Testimonials', 'admin.testimonials.index', 'admin.testimonials.create'],
            ['Enhance Your Journey cards', 'admin.journey.index', 'admin.journey.enhance.create', ['only' => 'enhance']],
        ],
    ];

    /**
     * Website pages that are only a list: title, address, manage route, add route, what it holds.
     * They sit in the pages overview next to the other pages.
     */
    public const LIST_PAGES = [
        'services' => ['Services', '/services', 'Passenger, Cargo, Charter and ACMI service cards', ['services']],
        'gallery' => ['Gallery', '/gallery', 'Photos and videos', ['gallery']],
        'faqs' => ['FAQs', '/travel-info/faqs', 'Questions and answers', ['faqs']],
        'agency-resources' => ['Agency Resources', '/agency-resources', 'Documents and policies for travel agents', ['agency-resources']],
        'legal' => ['Legal & Policies', '/privacy-policy and more', 'Privacy policy, terms and conditions', ['legal-documents']],
        'travel-info' => ['Travel Info Hub', '/travel-info', 'Check-in times, dangerous goods and helpful cards', ['check-in-times', 'dangerous-goods', 'travel-cards']],
        'testimonials' => ['Testimonials', '/ (home page)', 'Guest quotes shown on the home page', ['testimonials']],
    ];

    /**
     * The website's header menu, so the pages overview reads like the site: heading → pages.
     * ['page', slug, label] is a page edited section by section; ['list', key, label] a page made of a list;
     * a plain string starts a sub-heading.
     */
    public const MENU = [
        'About Us' => [
            ['page', 'about-new', 'About Us'], ['page', 'blog', 'Blog'], ['page', 'career', 'Careers'], ['list', 'gallery', 'Gallery'],
            ['page', 'news-hub', 'News Hub'], ['page', 'our-team', 'Our Team'], ['page', 'our-fleet', 'Our Fleet'],
            ['list', 'legal', 'Privacy Policy'], ['page', 'specials', 'Specials'], ['page', 'terms-of-use', 'Terms of Use'],
        ],
        'Agents' => [['page', 'palm-employers', 'PALM Employers'], ['page', 'travel-agency-contacts', 'Travel Agency Contacts'], ['list', 'agency-resources', 'Agency Resources']],
        'Destinations' => [['page', 'destinations', 'Destinations (and each destination)']],
        'Freight' => [['page', 'freight', 'Freight'], ['page', 'freight-packaging', 'Freight Packaging']],
        'Services' => [
            ['list', 'services', 'Services'], ['page', 'charter', 'Charters'], ['page', 'freight', 'Freight'],
            ['page', 'group-bookings', 'Group Bookings'], ['page', 'on-board', 'On Board'], ['page', 'contact-us', 'Contact'],
        ],
        'Travel Info' => [
            ['list', 'travel-info', 'Travel Info'], ['page', 'flight-schedule', 'Flight Schedule'],
            'Before You Fly',
            ['page', 'check-in-times', 'Check-in Times'], ['page', 'unaccompanied-minors', 'Unaccompanied Minors'], ['page', 'flying-while-pregnant', 'Flying While Pregnant'],
            ['page', 'travelling-with-children', 'Travelling With Children'], ['page', 'mobility-assistance', 'Mobility Assistance'],
            ['page', 'special-requests-and-assistance', 'Special Requests And Assistance'],
            'More travel info',
            ['page', 'baggage', 'Baggage'], ['page', 'additional-baggage', 'Additional Baggage'], ['page', 'dangerous-goods', 'Dangerous Goods'],
            ['page', 'conditions-of-carriage', 'Conditions of Carriage'], ['list', 'faqs', 'FAQs'], ['page', 'contact-us', 'Contact Us'],
        ],
    ];

    /** @return list<string> route patterns of every admin screen that is part of Website & App Pages */
    public function routePatterns(): array
    {
        return array_merge(['admin.content-pages.*'], array_map(fn ($p) => $p.'.*', array_merge(array_keys(self::SCREENS), self::OTHER_SCREENS)));
    }

    /** @return list<array{label: string, url: string, add: ?string}> */
    public function extraLists(string $slug): array
    {
        return array_map(fn ($l) => ['label' => $l[0], 'url' => route($l[1], $l[3] ?? []), 'add' => $l[2] ? route($l[2]) : null], self::PAGE_LISTS[$slug] ?? []);
    }

    /** @return list<array{title: string, edit: string}> pages that show the lists of an admin route */
    public function pagesForRoute(?string $routeName): array
    {
        foreach (self::SCREENS as $prefix => $feeds) {
            if ($routeName && str_starts_with($routeName, $prefix.'.')) {
                return $this->pagesShowing($feeds);
            }
        }

        return [];
    }

    /** @param list<string> $feeds */
    public function pagesShowing(array $feeds): array
    {
        return ContentPage::query()
            ->whereHas('sections', fn ($q) => $q->where('type', 'feed')->whereIn('feed', $feeds))
            ->orderBy('title')->get(['id', 'slug', 'title'])
            ->map(fn ($p) => ['title' => $p->title, 'edit' => route('admin.content-pages.edit', $p)])
            ->all();
    }

    /** @return array<string, list<array{label: string, url: string}>> page id → the lists its sections show */
    public function listsByPage(): array
    {
        $feeds = app(FeedEntries::class);
        $rows = \App\Models\ContentPageSection::query()->where('type', 'feed')->get(['content_page_id', 'feed']);
        $out = [];
        foreach ($rows as $row) {
            $entry = $feeds->screenFor($row->feed);
            if ($entry) {
                $out[$row->content_page_id][$entry['url']] = $entry;
            }
        }
        foreach (ContentPage::query()->whereIn('slug', array_keys(self::PAGE_LISTS))->get(['id', 'slug']) as $page) {
            foreach ($this->extraLists($page->slug) as $list) {
                $out[$page->id][$list['url']] = ['label' => $list['label'], 'url' => $list['url']];
            }
        }

        return $out;
    }
}
