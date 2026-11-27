<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SiteSetting;
use App\Models\WhyFlyFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhyFlyController extends Controller
{
    /**
     * Display a listing of Why Fly With Us features.
     */
    public function index(Request $request): View
    {
        $query = WhyFlyFeature::query()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $features = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => WhyFlyFeature::count(),
            'active' => WhyFlyFeature::where('is_active', true)->count(),
            'inactive' => WhyFlyFeature::where('is_active', false)->count(),
        ];

        $sectionHeading = SiteSetting::where('key', 'why_fly_heading')->value('value') ?? 'Why Fly with us';
        $sectionSubheading = SiteSetting::where('key', 'why_fly_subheading')->value('value') ?? 'Connecting the Pacific with safety, hospitality, and pride for over 50 years.';

        return view('admin.why_fly.index', compact('features', 'stats', 'sectionHeading', 'sectionSubheading'));
    }

    /**
     * Update section typography (heading and subtitle).
     */
    public function updateHeadings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'why_fly_heading' => ['required', 'string', 'max:255'],
            'why_fly_subheading' => ['nullable', 'string', 'max:500'],
        ]);

        SiteSetting::updateOrCreate(['key' => 'why_fly_heading'], ['value' => $validated['why_fly_heading']]);
        SiteSetting::updateOrCreate(['key' => 'why_fly_subheading'], ['value' => $validated['why_fly_subheading'] ?? '']);

        ActivityLog::log('updated', 'why_fly', 'Updated Why Fly With Us section headings');

        return back()->with('success', 'Section headings updated successfully.');
    }

    /**
     * Show the form for creating a new feature.
     */
    public function create(): View
    {
        return view('admin.why_fly.create');
    }

    /**
     * Store a newly created feature in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'icon_image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'icon_image' => ['nullable', 'string', 'max:1000'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);
        $validated['icon'] = $validated['icon'] ?? 'Plane';

        if ($request->hasFile('icon_image_file')) {
            $path = $request->file('icon_image_file')->store('why_fly', 'public');
            $validated['icon_image'] = '/storage/' . $path;
        }

        unset($validated['icon_image_file']);

        $feature = WhyFlyFeature::create($validated);

        ActivityLog::log('created', 'why_fly', "Created Why Fly feature card '{$feature->title}'");

        return redirect()->route('admin.why-fly.index')->with('success', "Card '{$feature->title}' created successfully.");
    }

    /**
     * Show the form for editing the specified feature.
     */
    public function edit(WhyFlyFeature $whyFly): View
    {
        $feature = $whyFly;
        return view('admin.why_fly.edit', compact('feature'));
    }

    /**
     * Update the specified feature in storage.
     */
    public function update(Request $request, WhyFlyFeature $whyFly): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:100'],
            'icon_image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'icon_image' => ['nullable', 'string', 'max:1000'],
            'link_url' => ['nullable', 'string', 'max:255'],
            'link_text' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $whyFly->sort_order);
        $validated['icon'] = $validated['icon'] ?? $whyFly->icon;

        if ($request->hasFile('icon_image_file')) {
            $path = $request->file('icon_image_file')->store('why_fly', 'public');
            $validated['icon_image'] = '/storage/' . $path;
        } elseif (empty($validated['icon_image'])) {
            $validated['icon_image'] = $whyFly->icon_image;
        }

        unset($validated['icon_image_file']);

        $whyFly->update($validated);

        ActivityLog::log('updated', 'why_fly', "Updated Why Fly feature card '{$whyFly->title}'");

        return redirect()->route('admin.why-fly.index')->with('success', "Card '{$whyFly->title}' updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(WhyFlyFeature $whyFly): RedirectResponse
    {
        $whyFly->update(['is_active' => !$whyFly->is_active]);

        $status = $whyFly->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'why_fly', "Why Fly card '{$whyFly->title}' was {$status}");

        return back()->with('success', "Card '{$whyFly->title}' {$status} successfully.");
    }

    /**
     * Remove the specified feature from storage.
     */
    public function destroy(WhyFlyFeature $whyFly): RedirectResponse
    {
        $title = $whyFly->title;
        $whyFly->delete();

        ActivityLog::log('deleted', 'why_fly', "Deleted Why Fly feature card '{$title}'");

        return redirect()->route('admin.why-fly.index')->with('success', "Card '{$title}' deleted successfully.");
    }
}
