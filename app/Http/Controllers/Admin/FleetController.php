<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\Fleet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        $query = Fleet::query();

        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                    ->orWhere('registration', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        $fleets = $query->ordered()->paginate(15)->withQueryString();
        $categories = DropdownOption::getValues('fleet_categories');

        return view('admin.fleets.index', compact('fleets', 'categories'));
    }

    public function create(): View
    {
        $types = DropdownOption::getValues('fleet_types');
        $categories = DropdownOption::getValues('fleet_categories');
        return view('admin.fleets.create', compact('types', 'categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'registration' => ['required', 'string', 'max:50', 'unique:fleets,registration'],
            'category' => ['required', 'in:passenger,freight'],
            'seating' => ['nullable', 'string', 'max:100'],
            'configuration' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            $data['image'] = $request->file('image_file')->store('fleets', 'public');
        } elseif (! empty($validated['image_url'])) {
            $data['image'] = $validated['image_url'];
        }

        $fleet = Fleet::create($data);

        ActivityLog::log('created', 'fleet', "Created fleet aircraft {$fleet->registration} ({$fleet->type})");

        return redirect()->route('admin.fleets.index')->with('success', "Aircraft '{$fleet->registration}' added successfully.");
    }

    public function edit(Fleet $fleet): View
    {
        $types = DropdownOption::getValues('fleet_types');
        $categories = DropdownOption::getValues('fleet_categories');
        return view('admin.fleets.edit', compact('fleet', 'types', 'categories'));
    }

    public function update(Request $request, Fleet $fleet): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'registration' => ['required', 'string', 'max:50', Rule::unique('fleets', 'registration')->ignore($fleet->id)],
            'category' => ['required', 'in:passenger,freight'],
            'seating' => ['nullable', 'string', 'max:100'],
            'configuration' => ['nullable', 'string', 'max:255'],
            'image_file' => ['nullable', 'image', 'max:5120'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'caption' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            if ($fleet->image && ! Str::startsWith($fleet->image, ['http://', 'https://'])) {
                Storage::disk('public')->delete($fleet->image);
            }
            $data['image'] = $request->file('image_file')->store('fleets', 'public');
        } elseif ($request->filled('image_url')) {
            $data['image'] = $validated['image_url'];
        }

        $fleet->update($data);

        ActivityLog::log('updated', 'fleet', "Updated fleet aircraft {$fleet->registration}");

        return redirect()->route('admin.fleets.index')->with('success', "Aircraft '{$fleet->registration}' updated successfully.");
    }

    public function destroy(Fleet $fleet): RedirectResponse
    {
        $reg = $fleet->registration;
        if ($fleet->image && ! Str::startsWith($fleet->image, ['http://', 'https://'])) {
            Storage::disk('public')->delete($fleet->image);
        }
        $fleet->delete();

        ActivityLog::log('deleted', 'fleet', "Deleted fleet aircraft {$reg}");

        return redirect()->route('admin.fleets.index')->with('success', "Aircraft '{$reg}' deleted successfully.");
    }

    public function toggleActive(Fleet $fleet): RedirectResponse
    {
        $fleet->is_active = ! $fleet->is_active;
        $fleet->save();

        ActivityLog::log('updated', 'fleet', "Toggled status of aircraft {$fleet->registration}");

        return back()->with('success', "Aircraft {$fleet->registration} status updated.");
    }
}
