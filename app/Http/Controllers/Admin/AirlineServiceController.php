<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AirlineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AirlineServiceController extends Controller
{
    /**
     * Display a listing of airline services.
     */
    public function index(Request $request): View
    {
        $query = AirlineService::query()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $services = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => AirlineService::count(),
            'active' => AirlineService::where('is_active', true)->count(),
            'passenger' => AirlineService::where('type', 'passenger')->count(),
            'cargo' => AirlineService::where('type', 'cargo')->count(),
        ];

        return view('admin.services.index', compact('services', 'stats'));
    }

    /**
     * Show the form for creating a new service.
     */
    public function create(): View
    {
        return view('admin.services.create');
    }

    /**
     * Store a newly created service in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:airline_services,slug'],
            'type' => ['required', 'string', 'in:passenger,cargo,charter,acmi,special'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string'],
            'features' => ['nullable', 'string'], // Newline-separated feature bullet points
            'cta_text' => ['nullable', 'string', 'max:100'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        // Convert newline features into clean array
        if (!empty($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        } else {
            $validated['features'] = [];
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('services', 'public');
            $validated['image'] = '/storage/' . $path;
        }

        unset($validated['image_file']);

        $service = AirlineService::create($validated);

        ActivityLog::log('created', 'services', "Created airline service '{$service->title}'");

        return redirect()->route('admin.services.index')->with('success', "Service '{$service->title}' created successfully.");
    }

    /**
     * Show the form for editing the specified service.
     */
    public function edit(AirlineService $service): View
    {
        return view('admin.services.edit', compact('service'));
    }

    /**
     * Update the specified service in storage.
     */
    public function update(Request $request, AirlineService $service): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:airline_services,slug,' . $service->id],
            'type' => ['required', 'string', 'in:passenger,cargo,charter,acmi,special'],
            'icon' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'image' => ['nullable', 'string', 'max:1000'],
            'description' => ['required', 'string'],
            'features' => ['nullable', 'string'],
            'cta_text' => ['nullable', 'string', 'max:100'],
            'cta_link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['slug'] = !empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $service->sort_order);

        if (!empty($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        } else {
            $validated['features'] = [];
        }

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('services', 'public');
            $validated['image'] = '/storage/' . $path;
        } elseif (empty($validated['image'])) {
            $validated['image'] = $service->image;
        }

        unset($validated['image_file']);

        $service->update($validated);

        ActivityLog::log('updated', 'services', "Updated airline service '{$service->title}'");

        return redirect()->route('admin.services.index')->with('success', "Service '{$service->title}' updated successfully.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(AirlineService $service): RedirectResponse
    {
        $service->update(['is_active' => !$service->is_active]);

        $status = $service->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'services', "Service '{$service->title}' was {$status}");

        return back()->with('success', "Service '{$service->title}' {$status} successfully.");
    }

    /**
     * Remove the specified service from storage.
     */
    public function destroy(AirlineService $service): RedirectResponse
    {
        $title = $service->title;
        $service->delete();

        ActivityLog::log('deleted', 'services', "Deleted airline service '{$title}'");

        return redirect()->route('admin.services.index')->with('success', "Service '{$title}' deleted successfully.");
    }
}
