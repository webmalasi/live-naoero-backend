<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Career;
use App\Models\DropdownOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CareerController extends Controller
{
    public function index(): View
    {
        $careers = Career::withCount('applications')->ordered()->paginate(15);

        return view('admin.careers.index', compact('careers'));
    }

    public function create(): View
    {
        $departments = DropdownOption::getValues('career_departments');
        $employmentTypes = DropdownOption::getValues('career_types');
        $locations = DropdownOption::getValues('career_locations');

        return view('admin.careers.create', compact('departments', 'employmentTypes', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:careers,slug'],
            'department' => ['nullable', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'employment_type' => ['required', 'string', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'seek_url' => ['nullable', 'url', 'max:500'],
            'deadline' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $career = Career::create($data);

        ActivityLog::log('created', 'careers', "Created job posting {$career->title}");

        return redirect()->route('admin.careers.index')->with('success', "Job posting '{$career->title}' created.");
    }

    public function edit(Career $career): View
    {
        $departments = DropdownOption::getValues('career_departments');
        $employmentTypes = DropdownOption::getValues('career_types');
        $locations = DropdownOption::getValues('career_locations');

        return view('admin.careers.edit', compact('career', 'departments', 'employmentTypes', 'locations'));
    }

    public function update(Request $request, Career $career): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('careers', 'slug')->ignore($career->id)],
            'department' => ['nullable', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:100'],
            'employment_type' => ['required', 'string', 'max:50'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'seek_url' => ['nullable', 'url', 'max:500'],
            'deadline' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['slug'] = ! empty($validated['slug']) ? Str::slug($validated['slug']) : Str::slug($validated['title']);
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $career->update($data);

        ActivityLog::log('updated', 'careers', "Updated job posting {$career->title}");

        return redirect()->route('admin.careers.index')->with('success', "Job posting '{$career->title}' updated.");
    }

    public function destroy(Career $career): RedirectResponse
    {
        $title = $career->title;
        $career->delete();

        ActivityLog::log('deleted', 'careers', "Deleted job posting {$title}");

        return redirect()->route('admin.careers.index')->with('success', "Job posting '{$title}' deleted.");
    }
}
