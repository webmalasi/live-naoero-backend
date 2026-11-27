<?php

namespace App\Services\Content;

use App\Models\AgencyContact;
use App\Models\AgencyResource;
use App\Models\AirlineService;
use App\Models\GalleryItem;
use App\Models\Testimonial;
use App\Models\TravelInfoCard;
use App\Models\Career;
use App\Models\CheckInTime;
use App\Models\DangerousGoodsItem;
use App\Models\Destination;
use App\Models\EnhanceJourneyItem;
use App\Models\Faq;
use App\Models\Fleet;
use App\Models\FlightSchedule;
use App\Models\LegalDocument;
use App\Models\OurJourneyMilestone;
use App\Models\SpecialOffer;
use App\Models\TeamMember;
use App\Models\WhyFlyFeature;

/**
 * What a "live list" section of a page holds, for the page editor: the
 * entries with their pictures and where each one is edited. The entries
 * stay in their own admin screens, so one change shows everywhere they do.
 */
class FeedEntries
{
    /** @return array<string, array{manage: string, add: ?string, screen: string, rows: list<array>}> */
    public function all(): array
    {
        $feeds = [];
        foreach ($this->sources() as $feed => $source) {
            $rows = $source['rows']()->values()->all();
            $feeds[$feed] = [
                'screen' => $source['screen'],
                'manage' => route($source['manage'], isset($source['only']) ? ['only' => $source['only']] : []),
                'add' => isset($source['add']) ? route($source['add']) : null,
                'rows' => $rows,
            ];
            // A single legal document opens on its own, not among all the others.
            if (in_array($feed, ['terms-of-use', 'conditions-of-carriage'], true) && $rows) {
                $feeds[$feed]['manage'] = $rows[0]['edit'];
            }
        }

        return $feeds;
    }

    /** @return array{label: string, url: string}|null the admin screen where a live list is edited */
    public function screenFor(string $feed): ?array
    {
        $source = $this->sources()[$feed] ?? null;

        return $source ? ['label' => $source['screen'], 'url' => route($source['manage'])] : null;
    }

    private function sources(): array
    {
        $row = fn (string $title, ?string $image, string $edit, ?string $note = null, bool $active = true) => [
            'title' => $title, 'note' => $note, 'image' => $this->url($image), 'edit' => $edit, 'active' => $active,
        ];

        return [
            'team' => ['screen' => 'Team Members', 'manage' => 'admin.team-members.index', 'add' => 'admin.team-members.create', 'rows' => fn () => TeamMember::query()->orderBy('sort_order')->get()
                ->map(fn ($m) => $row($m->name, $m->photo_url, route('admin.team-members.edit', $m), $m->role, (bool) $m->is_active))],
            'careers' => ['screen' => 'Careers', 'manage' => 'admin.careers.index', 'add' => 'admin.careers.create', 'rows' => fn () => Career::query()->orderBy('sort_order')->get()
                ->map(fn ($c) => $row($c->title, null, route('admin.careers.edit', $c), trim($c->department.' · '.$c->location, ' ·'), (bool) $c->is_active))],
            'our-journey' => ['screen' => 'Our Journey', 'only' => 'milestones', 'manage' => 'admin.journey.index', 'add' => 'admin.journey.milestones.create', 'rows' => fn () => OurJourneyMilestone::query()->orderBy('sort_order')->get()
                ->map(fn ($m) => $row($m->title, $m->image_url, route('admin.journey.milestones.edit', $m), $m->year, (bool) $m->is_active))],
            'why-fly' => ['screen' => 'Why Fly With Us', 'manage' => 'admin.why-fly.index', 'add' => 'admin.why-fly.create', 'rows' => fn () => WhyFlyFeature::query()->orderBy('sort_order')->get()
                ->map(fn ($f) => $row($f->title, $f->icon_image_url, route('admin.why-fly.edit', $f), strip_tags((string) $f->description), (bool) $f->is_active))],
            'enhance-journey' => ['screen' => 'Enhance Your Journey', 'only' => 'enhance', 'manage' => 'admin.journey.index', 'add' => 'admin.journey.enhance.create', 'rows' => fn () => EnhanceJourneyItem::query()->orderBy('sort_order')->get()
                ->map(fn ($i) => $row($i->title, $i->image_url, route('admin.journey.enhance.edit', $i), null, (bool) $i->is_active))],
            'specials' => ['screen' => 'Special Offers', 'manage' => 'admin.specials.index', 'add' => 'admin.specials.create', 'rows' => fn () => SpecialOffer::query()->orderBy('sort_order')->get()
                ->map(fn ($s) => $row($s->title, $s->image_url, route('admin.specials.edit', $s), null, (bool) $s->is_active))],
            'faqs' => ['screen' => 'FAQs', 'manage' => 'admin.faqs.index', 'add' => 'admin.faqs.create', 'rows' => fn () => Faq::query()->orderBy('sort_order')->get()
                ->map(fn ($f) => $row($f->question, null, route('admin.faqs.edit', $f), null, (bool) $f->is_active))],
            'fleet' => ['screen' => 'Our Fleet', 'manage' => 'admin.fleets.index', 'add' => 'admin.fleets.create', 'rows' => fn () => Fleet::query()->orderBy('sort_order')->get()
                ->map(fn ($f) => $row($f->registration.' '.$f->type, $f->image_url, route('admin.fleets.edit', $f), null, (bool) $f->is_active))],
            'flight-schedules' => ['screen' => 'Flight Schedules', 'manage' => 'admin.schedules.index', 'add' => 'admin.schedules.create', 'rows' => fn () => FlightSchedule::query()->orderBy('sort_order')->get()
                ->map(fn ($s) => $row($s->title, null, route('admin.schedules.edit', $s), $s->period, (bool) $s->is_active))],
            'destinations' => ['screen' => 'Destinations', 'manage' => 'admin.destinations.index', 'add' => 'admin.destinations.create', 'rows' => fn () => Destination::query()->orderBy('sort_order')->get()
                ->map(fn ($d) => $row($d->name, null, route('admin.destinations.edit', $d), $d->country, (bool) $d->is_active))],
            'agency-contacts' => ['screen' => 'Agency Contacts', 'manage' => 'admin.agencies.index', 'add' => 'admin.agencies.create', 'rows' => fn () => AgencyContact::query()->orderBy('sort_order')->get()
                ->map(fn ($a) => $row((string) $a->name, null, route('admin.agencies.index'), $a->country, (bool) ($a->is_active ?? true)))],
            'check-in-times' => ['screen' => 'Check-in times', 'only' => 'check-in', 'manage' => 'admin.travel-info.index', 'add' => 'admin.travel-info.check-in.create', 'rows' => fn () => CheckInTime::query()->orderBy('sort_order')->get()
                ->map(fn ($c) => $row($c->port, null, route('admin.travel-info.check-in.edit', $c), $c->opens.' to '.$c->closes, (bool) $c->is_active))],
            'dangerous-goods' => ['screen' => 'Dangerous goods', 'only' => 'dangerous-goods', 'manage' => 'admin.travel-info.index', 'add' => 'admin.travel-info.dangerous-goods.create', 'rows' => fn () => DangerousGoodsItem::query()->orderBy('sort_order')->get()
                ->map(fn ($d) => $row((string) ($d->item_name ?? $d->item), null, route('admin.travel-info.dangerous-goods.edit', $d), null, (bool) $d->is_active))],
            'services' => ['screen' => 'Services', 'manage' => 'admin.services.index', 'add' => 'admin.services.create', 'rows' => fn () => AirlineService::query()->orderBy('sort_order')->get()
                ->map(fn ($s) => $row($s->title, $s->image, route('admin.services.edit', $s), $s->type, (bool) $s->is_active))],
            'gallery' => ['screen' => 'Gallery', 'manage' => 'admin.gallery.index', 'add' => 'admin.gallery.create', 'rows' => fn () => GalleryItem::query()->orderBy('sort_order')->get()
                ->map(fn ($g) => $row((string) $g->title, $g->image_url ?: $g->image, route('admin.gallery.edit', $g), trim($g->category.' · '.$g->destination, ' ·'), (bool) $g->is_active))],
            'agency-resources' => ['screen' => 'Agency Resources', 'manage' => 'admin.agency-resources.index', 'add' => 'admin.agency-resources.create', 'rows' => fn () => AgencyResource::query()->orderBy('sort_order')->get()
                ->map(fn ($a) => $row($a->title, null, route('admin.agency-resources.edit', $a), $a->category, (bool) $a->is_active) + ['pdf' => $a->has_pdf])],
            'testimonials' => ['screen' => 'Testimonials', 'manage' => 'admin.testimonials.index', 'add' => 'admin.testimonials.create', 'rows' => fn () => Testimonial::query()->orderBy('sort_order')->get()
                ->map(fn ($t) => $row($t->name, $t->image, route('admin.testimonials.edit', $t), $t->location, (bool) $t->is_active))],
            'travel-cards' => ['screen' => 'Travel Info cards', 'only' => 'cards', 'manage' => 'admin.travel-info.index', 'add' => 'admin.travel-info.cards.create', 'rows' => fn () => TravelInfoCard::query()->orderBy('sort_order')->get()
                ->map(fn ($c) => $row($c->title, null, route('admin.travel-info.cards.edit', $c), null, (bool) $c->is_active))],
            'legal-documents' => ['screen' => 'Legal & Policies', 'manage' => 'admin.legal-documents.index', 'rows' => fn () => LegalDocument::query()->orderBy('id')->get()
                ->map(fn ($d) => $row($d->title, null, route('admin.legal-documents.edit', $d), count($d->sections ?? []).' parts', (bool) ($d->is_active ?? true)))],
            'terms-of-use' => ['screen' => 'Legal & Policies', 'manage' => 'admin.legal-documents.index', 'rows' => fn () => $this->legal('terms-of-use', $row)],
            'conditions-of-carriage' => ['screen' => 'Legal & Policies', 'manage' => 'admin.legal-documents.index', 'rows' => fn () => $this->legal('conditions-of-carriage', $row)],
        ];
    }

    private function legal(string $slug, callable $row)
    {
        return collect([LegalDocument::findBySlug($slug)])->filter()
            ->map(fn ($d) => $row($d->title, null, route('admin.legal-documents.edit', $d), count($d->sections ?? []).' parts'));
    }

    private function url(?string $image): ?string
    {
        if (! $image) {
            return null;
        }

        return str_starts_with($image, 'http') ? $image : url($image);
    }
}
