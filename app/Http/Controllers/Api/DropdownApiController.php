<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DropdownOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DropdownApiController extends Controller
{
    /**
     * Get all active options grouped by category.
     */
    public function all(): JsonResponse
    {
        $allGroups = DropdownOption::getGroupMeta();
        $activeOptions = DropdownOption::active()->ordered()->get();

        $grouped = [];
        foreach ($allGroups as $groupKey => $meta) {
            $options = $activeOptions->where('group', $groupKey)->values();
            $grouped[$groupKey] = [
                'meta' => [
                    'title' => $meta['title'],
                    'module' => $meta['module'],
                    'icon' => $meta['icon'] ?? '',
                    'description' => $meta['description'] ?? '',
                ],
                'count' => $options->count(),
                'options' => $options->map(fn ($opt) => [
                    'id' => $opt->id,
                    'name' => $opt->name,
                    'value' => $opt->value,
                    'badge_color' => $opt->badge_color,
                    'icon' => $opt->icon,
                    'description' => $opt->description,
                    'sort_order' => $opt->sort_order,
                ]),
            ];
        }

        return response()->json([
            'success' => true,
            'groups_count' => count($grouped),
            'total_options' => $activeOptions->count(),
            'data' => $grouped,
        ]);
    }

    /**
     * Get active options for a specific dropdown group.
     */
    public function show(string $group): JsonResponse
    {
        $meta = DropdownOption::getGroupMeta()[$group] ?? null;
        $options = DropdownOption::getGroup($group, true);

        return response()->json([
            'success' => true,
            'group' => $group,
            'title' => $meta['title'] ?? ucfirst(str_replace('_', ' ', $group)),
            'module' => $meta['module'] ?? 'System',
            'count' => $options->count(),
            'values' => $options->pluck('value')->all(),
            'options' => $options->map(fn ($opt) => [
                'id' => $opt->id,
                'name' => $opt->name,
                'value' => $opt->value,
                'badge_color' => $opt->badge_color,
                'icon' => $opt->icon,
                'description' => $opt->description,
                'sort_order' => $opt->sort_order,
            ]),
        ]);
    }
}
