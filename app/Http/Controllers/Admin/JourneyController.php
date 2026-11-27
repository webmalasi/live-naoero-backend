<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\EnhanceJourneyItem;
use App\Models\OurJourneyMilestone;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JourneyController extends Controller
{
    /**
     * Display unified Journey management dashboard.
     */
    public function index(Request $request): View
    {
        $milestones = OurJourneyMilestone::query()->ordered()->get();
        $enhanceItems = EnhanceJourneyItem::query()->ordered()->get();

        $stats = [
            'total_milestones' => $milestones->count(),
            'active_milestones' => $milestones->where('is_active', true)->count(),
            'total_enhance' => $enhanceItems->count(),
            'active_enhance' => $enhanceItems->where('is_active', true)->count(),
        ];

        $ourJourneyHeading = SiteSetting::where('key', 'our_journey_heading')->value('value') ?? 'Our Journey';
        $ourJourneySubheading = SiteSetting::where('key', 'our_journey_subheading')->value('value') ?? 'Connecting the Pacific for over 50 years with proud aviation heritage.';

        $enhanceHeading = SiteSetting::where('key', 'enhance_journey_heading')->value('value') ?? 'Enhance Your Journey';
        $enhanceSubheading = SiteSetting::where('key', 'enhance_journey_subheading')->value('value') ?? 'From stays to adventures, we\'ve got everything you need to make your trip unforgettable';

        return view('admin.journey.index', compact(
            'milestones',
            'enhanceItems',
            'stats',
            'ourJourneyHeading',
            'ourJourneySubheading',
            'enhanceHeading',
            'enhanceSubheading'
        ));
    }

    /**
     * Update Journey Section headings.
     */
    public function updateHeadings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'our_journey_heading' => ['nullable', 'string', 'max:255'],
            'our_journey_subheading' => ['nullable', 'string', 'max:500'],
            'enhance_journey_heading' => ['nullable', 'string', 'max:255'],
            'enhance_journey_subheading' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $val) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $val ?? '']);
        }

        ActivityLog::log('updated', 'journey', 'Updated Journey and Enhance Journey section headings');

        return back()->with('success', 'Section headings updated successfully.');
    }

    // ----------------------------------------------------
    // MILESTONES (Our Journey Timeline) CRUD
    // ----------------------------------------------------

    public function createMilestone(): View
    {
        return view('admin.journey.create_milestone');
    }

    public function storeMilestone(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('journey', 'public');
            $validated['image'] = '/storage/' . $path;
        }

        unset($validated['image_file']);

        $milestone = OurJourneyMilestone::create($validated);

        ActivityLog::log('created', 'journey', "Created timeline milestone '{$milestone->year}: {$milestone->title}'");

        return redirect()->route('admin.journey.index')->with('success', "Milestone '{$milestone->year}' created successfully.");
    }

    public function editMilestone(OurJourneyMilestone $milestone): View
    {
        return view('admin.journey.edit_milestone', compact('milestone'));
    }

    public function updateMilestone(Request $request, OurJourneyMilestone $milestone): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $milestone->sort_order);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('journey', 'public');
            $validated['image'] = '/storage/' . $path;
        } elseif (empty($validated['image'])) {
            $validated['image'] = $milestone->image;
        }

        unset($validated['image_file']);

        $milestone->update($validated);

        ActivityLog::log('updated', 'journey', "Updated timeline milestone '{$milestone->year}: {$milestone->title}'");

        return redirect()->route('admin.journey.index')->with('success', "Milestone '{$milestone->year}' updated successfully.");
    }

    public function toggleActiveMilestone(OurJourneyMilestone $milestone): RedirectResponse
    {
        $milestone->update(['is_active' => !$milestone->is_active]);
        $status = $milestone->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'journey', "Milestone '{$milestone->year}' was {$status}");

        return back()->with('success', "Milestone '{$milestone->year}' {$status}.");
    }

    public function destroyMilestone(OurJourneyMilestone $milestone): RedirectResponse
    {
        $year = $milestone->year;
        $milestone->delete();
        ActivityLog::log('deleted', 'journey', "Deleted timeline milestone '{$year}'");

        return redirect()->route('admin.journey.index')->with('success', "Milestone '{$year}' deleted successfully.");
    }

    // ----------------------------------------------------
    // ENHANCE YOUR JOURNEY (Homepage Essentials) CRUD
    // ----------------------------------------------------

    public function createEnhance(): View
    {
        return view('admin.journey.create_enhance');
    }

    public function storeEnhance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);
        $validated['link_text'] = $validated['link_text'] ?? 'Know More';

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('enhance_journey', 'public');
            $validated['image'] = '/storage/' . $path;
        }

        unset($validated['image_file']);

        $item = EnhanceJourneyItem::create($validated);

        ActivityLog::log('created', 'journey', "Created Enhance Journey card '{$item->title}'");

        return redirect()->route('admin.journey.index')->with('success', "Card '{$item->title}' created successfully.");
    }

    public function editEnhance(EnhanceJourneyItem $item): View
    {
        return view('admin.journey.edit_enhance', compact('item'));
    }

    public function updateEnhance(Request $request, EnhanceJourneyItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $item->sort_order);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('enhance_journey', 'public');
            $validated['image'] = '/storage/' . $path;
        } elseif (empty($validated['image'])) {
            $validated['image'] = $item->image;
        }

        unset($validated['image_file']);

        $item->update($validated);

        ActivityLog::log('updated', 'journey', "Updated Enhance Journey card '{$item->title}'");

        return redirect()->route('admin.journey.index')->with('success', "Card '{$item->title}' updated successfully.");
    }

    public function toggleActiveEnhance(EnhanceJourneyItem $item): RedirectResponse
    {
        $item->update(['is_active' => !$item->is_active]);
        $status = $item->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'journey', "Enhance card '{$item->title}' was {$status}");

        return back()->with('success', "Card '{$item->title}' {$status}.");
    }

    public function destroyEnhance(EnhanceJourneyItem $item): RedirectResponse
    {
        $title = $item->title;
        $item->delete();
        ActivityLog::log('deleted', 'journey', "Deleted Enhance Journey card '{$title}'");

        return redirect()->route('admin.journey.index')->with('success', "Card '{$title}' deleted successfully.");
    }
}
