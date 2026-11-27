<?php

namespace Database\Seeders;

use App\Models\DropdownOption;
use Illuminate\Database\Seeder;

class DropdownOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $registry = DropdownOption::getGroupMeta();

        foreach ($registry as $groupKey => $groupInfo) {
            if (empty($groupInfo['defaults'])) {
                continue;
            }

            foreach ($groupInfo['defaults'] as $item) {
                DropdownOption::updateOrCreate(
                    [
                        'group' => $groupKey,
                        'value' => $item['value'],
                    ],
                    [
                        'name' => $item['name'],
                        'badge_color' => $item['badge_color'] ?? 'blue',
                        'icon' => $item['icon'] ?? null,
                        'description' => $item['description'] ?? null,
                        'sort_order' => $item['sort_order'] ?? 0,
                        'is_active' => true,
                        'is_system' => true,
                    ]
                );
            }
        }
    }
}
