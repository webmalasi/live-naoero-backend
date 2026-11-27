<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PageBanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageBannerController extends Controller
{
    /**
     * Display a listing of all page banners.
     */
    public function index(Request $request): View
    {
        $query = PageBanner::query()->ordered();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('page_name', 'like', "%{$search}%")
                  ->orWhere('page_key', 'like', "%{$search}%")
                  ->orWhere('banner_heading', 'like', "%{$search}%")
                  ->orWhere('banner_description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $banners = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => PageBanner::count(),
            'active' => PageBanner::where('is_active', true)->count(),
            'inactive' => PageBanner::where('is_active', false)->count(),
        ];

        return view('admin.banners.index', compact('banners', 'stats'));
    }

    /**
     * Show the form for creating a new page banner.
     */
    public function create(): View
    {
        return view('admin.banners.create');
    }

    /**
     * Store a newly created page banner in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_key' => ['required', 'string', 'max:100', 'unique:page_banners,page_key'],
            'page_name' => ['required', 'string', 'max:150'],
            'page_route' => ['nullable', 'string', 'max:255'],
            'banner_badge_text' => ['nullable', 'string', 'max:100'],
            'banner_image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
            'banner_image' => ['nullable', 'string', 'max:1000'],
            'banner_heading' => ['required', 'string', 'max:255'],
            'banner_description' => ['nullable', 'string', 'max:2000'],
            'banner_button_text' => ['nullable', 'string', 'max:100'],
            'banner_button_link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? 0);

        if ($request->hasFile('banner_image_file')) {
            $path = $request->file('banner_image_file')->store('banners', 'public');
            $validated['banner_image'] = '/storage/' . $path;
        } elseif (empty($validated['banner_image'])) {
            $validated['banner_image'] = 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=1600&auto=format&fit=crop&q=80';
        }

        unset($validated['banner_image_file']);

        $banner = PageBanner::create($validated);

        ActivityLog::log('created', 'banners', "Created page banner for '{$banner->page_name}' ({$banner->page_key})");

        return redirect()->route('admin.banners.index')->with('success', "Page banner for '{$banner->page_name}' created successfully.");
    }

    /**
     * Show the form for editing the specified page banner.
     */
    public function edit(PageBanner $banner): View
    {
        return view('admin.banners.edit', compact('banner'));
    }

    /**
     * Update the specified page banner in storage.
     */
    public function update(Request $request, PageBanner $banner): RedirectResponse
    {
        $validated = $request->validate([
            'page_key' => ['required', 'string', 'max:100', 'unique:page_banners,page_key,' . $banner->id],
            'page_name' => ['required', 'string', 'max:150'],
            'page_route' => ['nullable', 'string', 'max:255'],
            'banner_badge_text' => ['nullable', 'string', 'max:100'],
            'banner_image_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:5120'],
            'banner_image' => ['nullable', 'string', 'max:1000'],
            'banner_heading' => ['required', 'string', 'max:255'],
            'banner_description' => ['nullable', 'string', 'max:2000'],
            'banner_button_text' => ['nullable', 'string', 'max:100'],
            'banner_button_link' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = (int)($validated['sort_order'] ?? $banner->sort_order);

        if ($request->hasFile('banner_image_file')) {
            $path = $request->file('banner_image_file')->store('banners', 'public');
            $validated['banner_image'] = '/storage/' . $path;
        } elseif (empty($validated['banner_image'])) {
            $validated['banner_image'] = $banner->banner_image;
        }

        unset($validated['banner_image_file']);

        $banner->update($validated);

        ActivityLog::log('updated', 'banners', "Updated page banner for '{$banner->page_name}' ({$banner->page_key})");

        return redirect()->route('admin.banners.index')->with('success', "Page banner for '{$banner->page_name}' updated successfully.");
    }

    /**
     * Toggle the active status of the page banner.
     */
    public function toggleActive(PageBanner $banner): RedirectResponse
    {
        $banner->update(['is_active' => !$banner->is_active]);

        $status = $banner->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'banners', "Banner '{$banner->page_name}' was {$status}");

        return back()->with('success', "Banner for '{$banner->page_name}' {$status} successfully.");
    }

    /**
     * Remove the specified page banner from storage.
     */
    public function destroy(PageBanner $banner): RedirectResponse
    {
        $name = $banner->page_name;
        $banner->delete();

        ActivityLog::log('deleted', 'banners', "Deleted page banner '{$name}'");

        return redirect()->route('admin.banners.index')->with('success', "Page banner '{$name}' deleted successfully.");
    }
}
