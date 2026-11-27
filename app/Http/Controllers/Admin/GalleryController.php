<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\GalleryItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GalleryController extends Controller
{
    /**
     * Display a listing of gallery items.
     */
    public function index(Request $request): View
    {
        $query = GalleryItem::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->input('category') !== '') {
            $query->where('category', $request->input('category'));
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('is_active', $request->boolean('status'));
        }

        $items = $query->ordered()->paginate(16)->withQueryString();

        $stats = [
            'total' => GalleryItem::count(),
            'active' => GalleryItem::where('is_active', true)->count(),
            'categories' => GalleryItem::distinct('category')->whereNotNull('category')->count('category'),
        ];

        $categories = DropdownOption::getValues('gallery_categories');

        return view('admin.gallery.index', compact('items', 'stats', 'categories'));
    }

    /**
     * Show the form for creating a new gallery item.
     */
    public function create(): View
    {
        $nextOrder = (GalleryItem::max('sort_order') ?? 0) + 1;
        $categories = DropdownOption::getValues('gallery_categories');

        return view('admin.gallery.create', compact('nextOrder', 'categories'));
    }

    /**
     * Store a newly created gallery item.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image_file')) {
            $imagePath = $request->file('image_file')->store('gallery', 'public');
        } elseif (! empty($validated['image_url'])) {
            $imagePath = $validated['image_url'];
        }

        $item = GalleryItem::create([
            'title' => $validated['title'],
            'destination' => $validated['destination'] ?? 'All',
            'category' => $validated['category'] ?? 'Destinations',
            'image' => $imagePath,
            'image_url' => $validated['image_url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'created',
            'gallery_items',
            "Added gallery photo '{$item->title}'",
            ['id' => $item->id]
        );

        return redirect()
            ->route('admin.gallery.index')
            ->with('success', "Gallery item '{$item->title}' created successfully.");
    }

    /**
     * Show the form for editing the specified gallery item.
     */
    public function edit(GalleryItem $gallery): View
    {
        $categories = DropdownOption::getValues('gallery_categories');

        return view('admin.gallery.edit', ['item' => $gallery, 'categories' => $categories]);
    }

    /**
     * Update the specified gallery item.
     */
    public function update(Request $request, GalleryItem $gallery): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'image_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $imagePath = $gallery->image;
        if ($request->hasFile('image_file')) {
            if ($imagePath && ! Str::startsWith($imagePath, ['http://', 'https://'])) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image_file')->store('gallery', 'public');
        } elseif ($request->filled('image_url')) {
            $imagePath = $validated['image_url'];
        }

        $gallery->update([
            'title' => $validated['title'],
            'destination' => $validated['destination'] ?? 'All',
            'category' => $validated['category'] ?? 'Destinations',
            'image' => $imagePath,
            'image_url' => $validated['image_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        ActivityLog::log(
            'updated',
            'gallery_items',
            "Updated gallery photo '{$gallery->title}'",
            ['id' => $gallery->id]
        );

        return redirect()
            ->route('admin.gallery.index')
            ->with('success', "Gallery item '{$gallery->title}' updated successfully.");
    }

    /**
     * Remove the specified gallery item.
     */
    public function destroy(GalleryItem $gallery): RedirectResponse
    {
        $title = $gallery->title;

        if ($gallery->image && ! Str::startsWith($gallery->image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($gallery->image);
        }

        $gallery->delete();

        ActivityLog::log(
            'deleted',
            'gallery_items',
            "Deleted gallery photo '{$title}'"
        );

        return redirect()
            ->route('admin.gallery.index')
            ->with('success', "Gallery item '{$title}' has been deleted.");
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(GalleryItem $gallery): JsonResponse|RedirectResponse
    {
        $gallery->is_active = ! $gallery->is_active;
        $gallery->save();

        ActivityLog::log(
            'updated',
            'gallery_items',
            "Changed status of '{$gallery->title}' to ".($gallery->is_active ? 'Active' : 'Inactive')
        );

        if (request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $gallery->is_active,
                'message' => 'Gallery item status updated.',
            ]);
        }

        return back()->with('success', "Status of '{$gallery->title}' updated.");
    }
}
