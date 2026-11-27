<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TestimonialController extends Controller
{
    public function index(): View
    {
        $testimonials = Testimonial::ordered()->paginate(15);

        return view('admin.testimonials.index', compact('testimonials'));
    }

    public function create(): View
    {
        return view('admin.testimonials.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'quote' => ['required', 'string', 'max:1000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'image_file' => ['nullable', 'image', 'max:2048'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['rating'] = (int) ($validated['rating'] ?? 5);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('testimonials', 'public');
        } elseif (! empty($validated['image_url'])) {
            $data['image'] = $validated['image_url'];
        }

        $testimonial = Testimonial::create($data);

        ActivityLog::log('created', 'testimonials', "Created testimonial from {$testimonial->name}");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial added successfully.');
    }

    public function edit(Testimonial $testimonial): View
    {
        return view('admin.testimonials.edit', compact('testimonial'));
    }

    public function update(Request $request, Testimonial $testimonial): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'quote' => ['required', 'string', 'max:1000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'image_file' => ['nullable', 'image', 'max:2048'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['rating'] = (int) ($validated['rating'] ?? 5);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            if ($testimonial->image && ! Str::startsWith($testimonial->image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($testimonial->image);
            }
            $data['image'] = $request->file('image_file')->store('testimonials', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image'] = $validated['image_url'];
        }

        $testimonial->update($data);

        ActivityLog::log('updated', 'testimonials', "Updated testimonial from {$testimonial->name}");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated successfully.');
    }

    public function toggleActive(Testimonial $testimonial): RedirectResponse
    {
        $testimonial->update(['is_active' => !$testimonial->is_active]);

        $status = $testimonial->is_active ? 'activated' : 'deactivated';
        ActivityLog::log('updated', 'testimonials', "Testimonial from '{$testimonial->name}' was {$status}");

        return back()->with('success', "Testimonial {$status} successfully.");
    }

    public function destroy(Testimonial $testimonial): RedirectResponse
    {
        $name = $testimonial->name;
        if ($testimonial->image && ! Str::startsWith($testimonial->image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($testimonial->image);
        }
        $testimonial->delete();

        ActivityLog::log('deleted', 'testimonials', "Deleted testimonial from {$name}");

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial deleted.');
    }
}
