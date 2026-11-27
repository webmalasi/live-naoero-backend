<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\SpecialOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SpecialOfferController extends Controller
{
    public function index(): View
    {
        $specials = SpecialOffer::ordered()->paginate(15);

        return view('admin.specials.index', compact('specials'));
    }

    public function create(): View
    {
        $currencies = DropdownOption::getGroup('currencies');
        return view('admin.specials.create', compact('currencies'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:special_offers,slug'],
            'origin' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'badge' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['currency'] = $validated['currency'] ?? 'AUD';
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('specials', 'public');
        } elseif (! empty($validated['image_url'])) {
            $data['image'] = $validated['image_url'];
        }

        $special = SpecialOffer::create($data);

        ActivityLog::log('created', 'specials', "Created special offer '{$special->title}'");

        return redirect()->route('admin.specials.index')->with('success', "Special offer '{$special->title}' created.");
    }

    public function edit(SpecialOffer $special): View
    {
        $currencies = DropdownOption::getGroup('currencies');
        return view('admin.specials.edit', compact('special', 'currencies'));
    }

    public function update(Request $request, SpecialOffer $special): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('special_offers', 'slug')->ignore($special->id)],
            'origin' => ['nullable', 'string', 'max:100'],
            'destination' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'badge' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            if ($special->image && ! Str::startsWith($special->image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($special->image);
            }
            $data['image'] = $request->file('image_file')->store('specials', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image'] = $validated['image_url'];
        }

        $special->update($data);

        ActivityLog::log('updated', 'specials', "Updated special offer '{$special->title}'");

        return redirect()->route('admin.specials.index')->with('success', "Special offer '{$special->title}' updated.");
    }

    public function destroy(SpecialOffer $special): RedirectResponse
    {
        $title = $special->title;
        if ($special->image && ! Str::startsWith($special->image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($special->image);
        }
        $special->delete();

        ActivityLog::log('deleted', 'specials', "Deleted special offer '{$title}'");

        return redirect()->route('admin.specials.index')->with('success', "Special offer '{$title}' deleted.");
    }
}
