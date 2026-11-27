<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $documents = LegalDocument::query()->get();

        return view('admin.legal_documents.index', compact('documents'));
    }

    public function edit(LegalDocument $legalDocument): View
    {
        return view('admin.legal_documents.edit', compact('legalDocument'));
    }

    public function update(Request $request, LegalDocument $legalDocument): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'heading' => ['nullable', 'string', 'max:255'],
            'subheading' => ['nullable', 'string', 'max:1000'],
            'badge_text' => ['nullable', 'string', 'max:255'],
            'banner_image' => ['nullable', 'string', 'max:1000'],
            'pdf_file' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:20480'],
            'pdf_url' => ['nullable', 'string', 'max:1000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'content' => ['nullable', 'string'],
            'last_reviewed_at' => ['nullable', 'date'],
            'next_review_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('pdf_file')) {
            $path = $request->file('pdf_file')->store('legal', 'public');
            $validated['pdf_url'] = '/storage/' . $path;
        }

        unset($validated['pdf_file']);

        // Handle visual dynamic sections array
        if ($request->has('sections') && is_array($request->input('sections'))) {
            $cleanedSections = [];
            foreach ($request->input('sections') as $sec) {
                if (!empty($sec['heading']) || !empty($sec['content'])) {
                    $cleanedSections[] = [
                        'heading' => $sec['heading'] ?? '',
                        'content' => $sec['content'] ?? '',
                    ];
                }
            }
            $validated['sections'] = $cleanedSections;
        } elseif ($request->filled('sections_json')) {
            $decoded = json_decode($request->input('sections_json'), true);
            if (is_array($decoded)) {
                $validated['sections'] = $decoded;
            }
        }

        $legalDocument->update($validated);

        ActivityLog::log('updated', 'legal_documents', "Updated Legal Document '{$legalDocument->title}'");

        return redirect()->route('admin.legal-documents.index')->with('success', "Document '{$legalDocument->title}' updated successfully.");
    }
}
