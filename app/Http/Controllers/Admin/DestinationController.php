<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Destination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DestinationController extends Controller
{
    /**
     * Display a listing of destinations.
     */
    public function index(Request $request): View
    {
        $query = Destination::query();

        // Search Filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        }

        // Active Status Filter
        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        // Featured Filter
        if ($request->has('featured') && $request->input('featured') !== '') {
            $query->where('is_featured', $request->boolean('featured'));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $allowedSorts = ['name', 'country', 'sort_order', 'created_at', 'is_active', 'is_featured'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'desc' ? 'desc' : 'asc');
        } else {
            $query->ordered();
        }

        $destinations = $query->paginate(12)->withQueryString();

        $stats = [
            'total' => Destination::count(),
            'active' => Destination::where('is_active', true)->count(),
            'featured' => Destination::where('is_featured', true)->count(),
        ];

        return view('admin.destinations.index', compact('destinations', 'stats'));
    }

    /**
     * Show the form for creating a new destination.
     */
    public function create(): View
    {
        $nextOrder = (Destination::max('sort_order') ?? 0) + 1;

        return view('admin.destinations.create', compact('nextOrder'));
    }

    /**
     * Store a newly created destination in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:destinations,slug'],
            'country' => ['required', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:30'],
            'thumbnail_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'thumbnail_url' => ['nullable', 'string', 'max:1000'],
            'banner_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'banner_url' => ['nullable', 'string', 'max:1000'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'quote' => ['nullable', 'string', 'max:1000'],
            'discover_heading' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string'],
            'banner_text' => ['nullable', 'string'],
            'closing_paragraph' => ['nullable', 'string'],
            'things_to_do_intro' => ['nullable', 'string'],
            'stays_intro' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'why_visit_json' => ['nullable', 'string'],
            'things_to_do_json' => ['nullable', 'string'],
            'stays_json' => ['nullable', 'string'],
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
            'country' => $validated['country'],
            'flag' => $validated['flag'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'quote' => $validated['quote'] ?? null,
            'discover_heading' => $validated['discover_heading'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'banner_text' => $validated['banner_text'] ?? null,
            'closing_paragraph' => $validated['closing_paragraph'] ?? null,
            'things_to_do_intro' => $validated['things_to_do_intro'] ?? null,
            'stays_intro' => $validated['stays_intro'] ?? null,
            'is_featured' => $request->boolean('is_featured', true),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];

        // Handle Thumbnail
        if ($request->hasFile('thumbnail_file')) {
            $path = $request->file('thumbnail_file')->store('destinations/thumbnails', 'public');
            $data['thumbnail'] = $path;
        } elseif (! empty($validated['thumbnail_url'])) {
            $data['thumbnail'] = $validated['thumbnail_url'];
        }

        // Handle Banner
        if ($request->hasFile('banner_file')) {
            $path = $request->file('banner_file')->store('destinations/banners', 'public');
            $data['banner_image'] = $path;
        } elseif (! empty($validated['banner_url'])) {
            $data['banner_image'] = $validated['banner_url'];
        }

        // Handle JSON fields
        if (! empty($validated['why_visit_json'])) {
            $decoded = json_decode($validated['why_visit_json'], true);
            $data['why_visit'] = is_array($decoded) ? $decoded : null;
        }

        if (! empty($validated['things_to_do_json'])) {
            $decoded = json_decode($validated['things_to_do_json'], true);
            $data['things_to_do'] = is_array($decoded) ? $decoded : null;
        }

        if (! empty($validated['stays_json'])) {
            $decoded = json_decode($validated['stays_json'], true);
            $data['stays'] = is_array($decoded) ? $decoded : null;
        }

        $destination = Destination::create($data);

        ActivityLog::log(
            'created',
            'destinations',
            "Created destination '{$destination->name}' ({$destination->country})",
            ['id' => $destination->id, 'slug' => $destination->slug]
        );

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', "Destination '{$destination->name}' has been created successfully.");
    }

    /**
     * Display the specified destination.
     */
    public function show(Destination $destination): View
    {
        return view('admin.destinations.show', compact('destination'));
    }

    /**
     * Show the form for editing the specified destination.
     */
    public function edit(Destination $destination): View
    {
        return view('admin.destinations.edit', compact('destination'));
    }

    /**
     * Update the specified destination in storage.
     */
    public function update(Request $request, Destination $destination): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('destinations', 'slug')->ignore($destination->id)],
            'country' => ['required', 'string', 'max:255'],
            'flag' => ['nullable', 'string', 'max:30'],
            'thumbnail_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'thumbnail_url' => ['nullable', 'string', 'max:1000'],
            'banner_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:8192'],
            'banner_url' => ['nullable', 'string', 'max:1000'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'quote' => ['nullable', 'string', 'max:1000'],
            'discover_heading' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string'],
            'banner_text' => ['nullable', 'string'],
            'closing_paragraph' => ['nullable', 'string'],
            'things_to_do_intro' => ['nullable', 'string'],
            'stays_intro' => ['nullable', 'string'],
            'is_featured' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'why_visit_json' => ['nullable', 'string'],
            'things_to_do_json' => ['nullable', 'string'],
            'stays_json' => ['nullable', 'string'],
            'info_sections_json' => ['nullable', 'json'],
            'detailed_info_json' => ['nullable', 'json'],
        ]);

        $data = [
            'name' => $validated['name'],
            'slug' => ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['name']),
            'country' => $validated['country'],
            'flag' => $validated['flag'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'quote' => $validated['quote'] ?? null,
            'discover_heading' => $validated['discover_heading'] ?? null,
            'intro' => $validated['intro'] ?? null,
            'banner_text' => $validated['banner_text'] ?? null,
            'closing_paragraph' => $validated['closing_paragraph'] ?? null,
            'things_to_do_intro' => $validated['things_to_do_intro'] ?? null,
            'stays_intro' => $validated['stays_intro'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ];

        // Handle Thumbnail replacement
        if ($request->hasFile('thumbnail_file')) {
            if ($destination->thumbnail && ! Str::startsWith($destination->thumbnail, ['http://', 'https://'])) {
                Storage::disk('public')->delete($destination->thumbnail);
            }
            $data['thumbnail'] = $request->file('thumbnail_file')->store('destinations/thumbnails', 'public');
        } elseif ($request->filled('thumbnail_url')) {
            $data['thumbnail'] = $validated['thumbnail_url'];
        }

        // Handle Banner replacement
        if ($request->hasFile('banner_file')) {
            if ($destination->banner_image && ! Str::startsWith($destination->banner_image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($destination->banner_image);
            }
            $data['banner_image'] = $request->file('banner_file')->store('destinations/banners', 'public');
        } elseif ($request->filled('banner_url')) {
            $data['banner_image'] = $validated['banner_url'];
        }

        // Handle JSON fields
        if ($request->has('why_visit_json')) {
            $decoded = json_decode($validated['why_visit_json'] ?? '', true);
            $data['why_visit'] = is_array($decoded) ? $decoded : null;
        }

        if ($request->has('things_to_do_json')) {
            $decoded = json_decode($validated['things_to_do_json'] ?? '', true);
            $data['things_to_do'] = is_array($decoded) ? $decoded : null;
        }

        if ($request->has('stays_json')) {
            $decoded = json_decode($validated['stays_json'] ?? '', true);
            $data['stays'] = is_array($decoded) ? $decoded : null;
        }

        // Background sections and detailed information (the website's
        // destination page), edited as lists of {heading, paragraphs …}.
        foreach (['info_sections', 'detailed_info'] as $field) {
            if ($request->has($field.'_json')) {
                $decoded = json_decode($validated[$field.'_json'] ?? '', true);
                $data[$field] = is_array($decoded) && $decoded !== [] ? $decoded : null;
            }
        }

        $destination->update($data);

        ActivityLog::log(
            'updated',
            'destinations',
            "Updated destination '{$destination->name}'",
            ['id' => $destination->id, 'slug' => $destination->slug]
        );

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', "Destination '{$destination->name}' updated successfully.");
    }

    /**
     * Remove the specified destination from storage.
     */
    public function destroy(Destination $destination): RedirectResponse
    {
        $name = $destination->name;

        // Cleanup local files if any
        if ($destination->thumbnail && ! Str::startsWith($destination->thumbnail, ['http://', 'https://'])) {
            Storage::disk('public')->delete($destination->thumbnail);
        }
        if ($destination->banner_image && ! Str::startsWith($destination->banner_image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($destination->banner_image);
        }

        $destination->delete();

        ActivityLog::log(
            'deleted',
            'destinations',
            "Deleted destination '{$name}'",
            ['name' => $name]
        );

        return redirect()
            ->route('admin.destinations.index')
            ->with('success', "Destination '{$name}' has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Destination $destination): JsonResponse|RedirectResponse
    {
        $destination->is_active = ! $destination->is_active;
        $destination->save();

        ActivityLog::log(
            'updated',
            'destinations',
            "Changed status of '{$destination->name}' to ".($destination->is_active ? 'Active' : 'Inactive')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $destination->is_active,
                'message' => 'Destination status updated.',
            ]);
        }

        return back()->with('success', "Status of '{$destination->name}' updated.");
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Destination $destination): JsonResponse|RedirectResponse
    {
        $destination->is_featured = ! $destination->is_featured;
        $destination->save();

        ActivityLog::log(
            'updated',
            'destinations',
            "Changed featured status of '{$destination->name}' to ".($destination->is_featured ? 'Featured' : 'Standard')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_featured' => $destination->is_featured,
                'message' => 'Featured status updated.',
            ]);
        }

        return back()->with('success', "Featured status of '{$destination->name}' updated.");
    }

    /**
     * Upload an image asynchronously for Why Visit cards, Things To Do showcase, or Stays.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp,gif,svg', 'max:10240'],
            'folder' => ['nullable', 'string', 'in:why_visit,activities,stays,general'],
        ]);

        $folder = $request->input('folder', 'general');
        $path = $request->file('image')->store("destinations/{$folder}", 'public');
        $url = Destination::formatImageUrl($path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $url,
            'message' => 'Image uploaded successfully.',
        ]);
    }
}
