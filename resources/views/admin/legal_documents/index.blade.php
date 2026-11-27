@extends('layouts.admin')

@section('title', 'Legal & Policy Documents')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Legal & Policy Documents</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $documents->count() }} Managed Documents
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage Privacy Policy, Terms of Use, and Conditions of Carriage clauses, contact emails, and PDF downloads.</p>
        </div>
    </div>

    <!-- Documents Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($documents as $doc)
            <div class="naoero-card bg-white border border-slate-200 rounded-2xl p-6 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-xs font-mono font-bold text-navy bg-slate-100 px-2.5 py-1 rounded">/{{ $doc->slug }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $doc->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                            {{ $doc->is_active ? 'Published' : 'Draft' }}
                        </span>
                    </div>

                    <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>

                    <h3 class="font-extrabold text-navy text-lg mb-1">{{ $doc->title }}</h3>
                    <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed mb-4">{{ $doc->summary ?? $doc->subheading }}</p>

                    @if($doc->contact_email)
                        <div class="text-[11px] text-slate-500 flex items-center gap-1.5 mb-2">
                            <span>📧</span>
                            <span class="font-mono">{{ $doc->contact_email }}</span>
                        </div>
                    @endif

                    @if($doc->pdf_url)
                        <div class="text-[11px] text-blue-600 flex items-center gap-1.5 truncate">
                            <span>📎</span>
                            <a href="{{ $doc->download_pdf_url }}" target="_blank" class="hover:underline font-mono truncate">PDF Attachment</a>
                        </div>
                    @endif
                </div>

                <div class="pt-4 mt-6 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs text-slate-400 font-medium">
                        {{ is_array($doc->sections) ? count($doc->sections) . ' sections' : 'Rich text' }}
                    </span>
                    <a href="{{ route('admin.legal-documents.edit', $doc) }}" class="inline-flex items-center gap-1 px-3.5 py-1.5 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm transition-all">
                        <span>Edit Policy</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center bg-white rounded-2xl border border-slate-200">
                <p class="text-sm text-slate-500 font-bold">No legal documents initialized.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection
