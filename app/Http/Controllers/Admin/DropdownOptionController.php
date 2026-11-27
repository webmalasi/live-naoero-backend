<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DropdownOptionController extends Controller
{
    /**
     * Display listing of dropdown options with dynamic group filter & search.
     */
    public function index(Request $request): View
    {
        $allGroupsMeta = DropdownOption::getGroupMeta();
        $selectedGroup = $request->input('group', array_key_first($allGroupsMeta) ?? 'shop_categories');

        $query = DropdownOption::query();

        if ($selectedGroup !== 'all') {
            $query->where('group', $selectedGroup);
        }

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('group', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $sort = $request->input('sort', 'order_asc');
        match ($sort) {
            'order_asc' => $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            'newest' => $query->latest('id'),
            default => $query->orderBy('sort_order', 'asc'),
        };

        $perPage = (int) $request->input('per_page', 20);
        $options = $query->paginate($perPage)->withQueryString();

        // Count per group for sidebar pills (database-agnostic via collection)
        $groupCounts = DropdownOption::all()
            ->groupBy('group')
            ->map(fn ($items, $grp) => (object) [
                'group' => $grp,
                'total' => $items->count(),
                'active_total' => $items->where('is_active', true)->count(),
            ]);

        $totalOptionsCount = DropdownOption::count();
        $activeOptionsCount = DropdownOption::where('is_active', true)->count();
        $totalGroupsCount = count($allGroupsMeta);

        return view('admin.dropdowns.index', compact(
            'options',
            'allGroupsMeta',
            'selectedGroup',
            'groupCounts',
            'totalOptionsCount',
            'activeOptionsCount',
            'totalGroupsCount'
        ));
    }

    /**
     * Store a newly created dropdown option in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $value = !empty($validated['value']) ? trim($validated['value']) : trim($validated['name']);
        
        $option = DropdownOption::create([
            'group' => $validated['group'],
            'name' => trim($validated['name']),
            'value' => $value,
            'badge_color' => $validated['badge_color'] ?: 'blue',
            'icon' => $validated['icon'] ?: null,
            'description' => $validated['description'] ?: null,
            'sort_order' => isset($validated['sort_order']) ? (int) $validated['sort_order'] : (DropdownOption::where('group', $validated['group'])->max('sort_order') + 1),
            'is_active' => $request->boolean('is_active', true),
            'is_system' => false,
        ]);

        ActivityLog::log('created', 'dropdowns', "Added dropdown option '{$option->name}' to group '{$option->group}'");

        return redirect()->route('admin.dropdowns.index', ['group' => $option->group])
            ->with('success', "Dropdown option '{$option->name}' created successfully in '{$option->group}'.");
    }

    /**
     * Update an existing dropdown option.
     */
    public function update(Request $request, DropdownOption $dropdownOption): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $dropdownOption->update([
            'name' => trim($validated['name']),
            'value' => trim($validated['value']),
            'badge_color' => $validated['badge_color'] ?: $dropdownOption->badge_color,
            'icon' => $validated['icon'] ?: null,
            'description' => $validated['description'] ?: null,
            'sort_order' => (int) ($validated['sort_order'] ?? $dropdownOption->sort_order),
            'is_active' => $request->boolean('is_active'),
        ]);

        ActivityLog::log('updated', 'dropdowns', "Updated dropdown option #{$dropdownOption->id}: '{$dropdownOption->name}' ({$dropdownOption->group})");

        return redirect()->route('admin.dropdowns.index', ['group' => $dropdownOption->group])
            ->with('success', "Dropdown option '{$dropdownOption->name}' updated successfully.");
    }

    /**
     * Delete an existing dropdown option.
     */
    public function destroy(DropdownOption $dropdownOption): RedirectResponse
    {
        $group = $dropdownOption->group;
        $name = $dropdownOption->name;

        if ($dropdownOption->is_system) {
            return redirect()->route('admin.dropdowns.index', ['group' => $group])
                ->with('error', "System option '{$name}' is protected and cannot be deleted.");
        }

        $dropdownOption->delete();

        ActivityLog::log('deleted', 'dropdowns', "Deleted dropdown option '{$name}' from group '{$group}'");

        return redirect()->route('admin.dropdowns.index', ['group' => $group])
            ->with('success', "Option '{$name}' deleted successfully.");
    }

    /**
     * 1-Click AJAX status toggle.
     */
    public function toggleActive(DropdownOption $dropdownOption): JsonResponse
    {
        $dropdownOption->update([
            'is_active' => ! $dropdownOption->is_active
        ]);

        ActivityLog::log(
            'updated',
            'dropdowns',
            "Toggled active status for dropdown #{$dropdownOption->id} to " . ($dropdownOption->is_active ? 'Active' : 'Inactive')
        );

        return response()->json([
            'success' => true,
            'is_active' => $dropdownOption->is_active,
            'message' => "Status updated to " . ($dropdownOption->is_active ? 'Active' : 'Inactive')
        ]);
    }

    /**
     * Quick AJAX store endpoint for creating a new option on-the-fly from modals inside product/fleet/post forms.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'string', 'max:255'],
            'badge_color' => ['nullable', 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        $value = !empty($validated['value']) ? trim($validated['value']) : trim($validated['name']);
        $badgeColor = !empty($validated['badge_color']) ? $validated['badge_color'] : 'blue';
        $icon = !empty($validated['icon']) ? $validated['icon'] : null;

        $option = DropdownOption::createOrGet(
            $validated['group'],
            trim($validated['name']),
            $value,
            [
                'badge_color' => $badgeColor,
                'icon' => $icon,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $option->id,
                'group' => $option->group,
                'name' => $option->name,
                'value' => $option->value,
                'badge_color' => $option->badge_color,
                'icon' => $option->icon,
            ],
            'option' => [
                'id' => $option->id,
                'group' => $option->group,
                'name' => $option->name,
                'value' => $option->value,
                'badge_color' => $option->badge_color,
                'icon' => $option->icon,
            ],
            'message' => "Option '{$option->name}' added to {$option->group} successfully!"
        ], 201);
    }

    /**
     * AJAX endpoint to fetch active options for a group in JSON format.
     */
    public function getGroupJson(string $group): JsonResponse
    {
        $options = DropdownOption::getGroup($group, true);
        return response()->json([
            'group' => $group,
            'count' => $options->count(),
            'options' => $options->map(fn ($opt) => [
                'id' => $opt->id,
                'name' => $opt->name,
                'value' => $opt->value,
                'badge_color' => $opt->badge_color,
                'icon' => $opt->icon,
            ])
        ]);
    }
}
