<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AgencyResource;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AgencyResourceController extends Controller
{
    public function index(Request $request): View
    {
        $query = AgencyResource::query()->ordered();

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
        }

        $resources = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => AgencyResource::count(),
            'active' => AgencyResource::where('is_active', true)->count(),
        ];

        $heading = SiteSetting::where('key', 'agency_resources_heading')->value('value') ?? 'Agency Resources';
        $intro = SiteSetting::where('key', 'agency_resources_intro')->value('value') ?? '';

        return view('admin.agency_resources.index', compact('resources', 'stats', 'heading', 'intro'));
    }

    public function updateHeadings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'agency_resources_heading' => ['nullable', 'string', 'max:255'],
            'agency_resources_intro' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($validated as $key => $val) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $val ?? '']);
        }

        ActivityLog::log('updated', 'agency_resources', 'Updated Agency Resources page headings and intro text');

        return back()->with('success', 'Agency resources header content updated successfully.');
    }

    public function create(): View
    {
        return view('admin.agency_resources.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'external_url' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);
        $validated['slug'] = Str::slug($validated['title']);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('policies', 'public');
            $validated['file_url'] = '/storage/' . $path;
        }

        unset($validated['file']);

        $resource = AgencyResource::create($validated);

        ActivityLog::log('created', 'agency_resources', "Created Agency Policy '{$resource->title}'");

        return redirect()->route('admin.agency-resources.index')->with('success', "Policy '{$resource->title}' created successfully.");
    }

    public function edit(AgencyResource $agencyResource): View
    {
        return view('admin.agency_resources.edit', compact('agencyResource'));
    }

    public function update(Request $request, AgencyResource $agencyResource): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'file_url' => ['nullable', 'string', 'max:1000'],
            'external_url' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $agencyResource->sort_order);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('policies', 'public');
            $validated['file_url'] = '/storage/' . $path;
        } elseif (empty($validated['file_url'])) {
            $validated['file_url'] = $agencyResource->file_url;
        }

        unset($validated['file']);

        $agencyResource->update($validated);

        ActivityLog::log('updated', 'agency_resources', "Updated Agency Policy '{$agencyResource->title}'");

        return redirect()->route('admin.agency-resources.index')->with('success', "Policy '{$agencyResource->title}' updated successfully.");
    }

    public function toggleActive(Request $request, AgencyResource $agencyResource)
    {
        $agencyResource->update(['is_active' => !$agencyResource->is_active]);
        $status = $agencyResource->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'agency_resources', "Policy '{$agencyResource->title}' was {$status}");

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'is_active' => (bool)$agencyResource->is_active,
                'message' => "Policy '{$agencyResource->title}' {$status}.",
            ]);
        }

        return back()->with('success', "Policy '{$agencyResource->title}' {$status}.");
    }

    public function destroy(AgencyResource $agencyResource): RedirectResponse
    {
        $title = $agencyResource->title;
        $agencyResource->delete();
        ActivityLog::log('deleted', 'agency_resources', "Deleted Agency Policy '{$title}'");

        return redirect()->route('admin.agency-resources.index')->with('success', "Policy '{$title}' deleted successfully.");
    }
}
