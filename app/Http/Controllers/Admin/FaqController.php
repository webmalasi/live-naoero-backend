<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DropdownOption;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(Request $request): View
    {
        $query = Faq::query();

        if ($cat = $request->input('category')) {
            $query->where('category', $cat);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('question', 'like', "%{$search}%")
                    ->orWhere('answer', 'like', "%{$search}%");
            });
        }

        $faqs = $query->ordered()->paginate(20)->withQueryString();
        $categories = DropdownOption::getValues('faq_categories');

        return view('admin.faqs.index', compact('faqs', 'categories'));
    }

    public function create(): View
    {
        $categories = DropdownOption::getValues('faq_categories');

        return view('admin.faqs.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $faq = Faq::create($data);

        ActivityLog::log('created', 'faqs', "Created FAQ: {$faq->question}");

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ added successfully.');
    }

    public function edit(Faq $faq): View
    {
        $categories = DropdownOption::getValues('faq_categories');

        return view('admin.faqs.edit', compact('faq', 'categories'));
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'question' => ['required', 'string', 'max:500'],
            'answer' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data = $validated;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $faq->update($data);

        ActivityLog::log('updated', 'faqs', "Updated FAQ: {$faq->question}");

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ updated successfully.');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $q = $faq->question;
        $faq->delete();

        ActivityLog::log('deleted', 'faqs', "Deleted FAQ: {$q}");

        return redirect()->route('admin.faqs.index')->with('success', 'FAQ deleted.');
    }
}
