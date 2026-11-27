<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\TeamMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamMemberController extends Controller
{
    public function index(Request $request): View
    {
        $query = TeamMember::query();

        if ($dept = $request->input('department')) {
            $query->where('department', $dept);
        }

        $members = $query->ordered()->paginate(15)->withQueryString();
        $departments = DropdownOption::getValues('team_departments');

        return view('admin.team.index', compact('members', 'departments'));
    }

    public function create(): View
    {
        $departments = DropdownOption::getValues('team_departments');
        return view('admin.team.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string'],
            'photo_file' => ['nullable', 'image', 'max:3072'],
            'photo_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('photo_file')) {
            $data['photo'] = $request->file('photo_file')->store('team', 'public');
        } elseif (! empty($validated['photo_url'])) {
            $data['photo'] = $validated['photo_url'];
        }

        $member = TeamMember::create($data);

        ActivityLog::log('created', 'team', "Added team member {$member->name}");

        return redirect()->route('admin.team-members.index')->with('success', "Team member '{$member->name}' added.");
    }

    public function edit(TeamMember $teamMember): View
    {
        $departments = DropdownOption::getValues('team_departments');
        return view('admin.team.edit', compact('teamMember', 'departments'));
    }

    public function update(Request $request, TeamMember $teamMember): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'role' => ['required', 'string', 'max:100'],
            'department' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string'],
            'photo_file' => ['nullable', 'image', 'max:3072'],
            'photo_url' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        if ($request->hasFile('photo_file')) {
            if ($teamMember->photo && ! Str::startsWith($teamMember->photo, ['http://', 'https://'])) {
                Storage::disk('public')->delete($teamMember->photo);
            }
            $data['photo'] = $request->file('photo_file')->store('team', 'public');
        } elseif ($request->filled('photo_url')) {
            $data['photo'] = $validated['photo_url'];
        }

        $teamMember->update($data);

        ActivityLog::log('updated', 'team', "Updated team member {$teamMember->name}");

        return redirect()->route('admin.team-members.index')->with('success', "Team member '{$teamMember->name}' updated.");
    }

    public function destroy(TeamMember $teamMember): RedirectResponse
    {
        $name = $teamMember->name;
        if ($teamMember->photo && ! Str::startsWith($teamMember->photo, ['http://', 'https://'])) {
            Storage::disk('public')->delete($teamMember->photo);
        }
        $teamMember->delete();

        ActivityLog::log('deleted', 'team', "Deleted team member {$name}");

        return redirect()->route('admin.team-members.index')->with('success', "Team member '{$name}' deleted.");
    }
}
