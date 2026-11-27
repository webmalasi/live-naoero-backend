@extends('layouts.admin')

@section('title', 'Edit Legal Document - ' . $legalDocument->title)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    sections: {{ json_encode(old('sections', $legalDocument->sections ?? [])) }},
    addSection() {
        this.sections.push({
            heading: '',
            content: ''
        });
    },
    removeSection(index) {
        if (confirm('Are you sure you want to remove this section?')) {
            this.sections.splice(index, 1);
        }
    },
    moveUp(index) {
        if (index > 0) {
            const temp = this.sections[index];
            this.sections.splice(index, 1);
            this.sections.splice(index - 1, 0, temp);
        }
    },
    moveDown(index) {
        if (index < this.sections.length - 1) {
            const temp = this.sections[index];
            this.sections.splice(index, 1);
            this.sections.splice(index + 1, 0, temp);
        }
    }
}">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.legal-documents.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy flex items-center gap-1">
                <span>&larr;</span> Back to Legal Documents
            </a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit {{ $legalDocument->title }}</h1>
            <p class="text-sm text-ink-muted">Configure document typography, summary, downloadable PDF, contact email, and visual policy clauses.</p>
        </div>
        <div>
            <span class="text-xs font-mono font-bold text-navy bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200">
                /{{ $legalDocument->slug }}
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.legal-documents.update', $legalDocument) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Card 1: Core Document Settings -->
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-5">
            <h2 class="text-base font-bold text-navy border-b border-slate-100 pb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-navy"></span>
                <span>1. General Information & Banner Headers</span>
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Document Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $legalDocument->title) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        URL Slug Identifier
                    </label>
                    <input type="text" value="{{ $legalDocument->slug }}" disabled class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 font-mono text-slate-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Banner Main Heading
                    </label>
                    <input type="text" name="heading" value="{{ old('heading', $legalDocument->heading) }}" placeholder="e.g. Privacy Policy" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Breadcrumb / Badge Text
                    </label>
                    <input type="text" name="badge_text" value="{{ old('badge_text', $legalDocument->badge_text) }}" placeholder="e.g. Updated 2026 / Official Policy" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Banner Subheading / Intro Line
                </label>
                <textarea name="subheading" rows="2" placeholder="Brief introductory summary shown on the top hero banner..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('subheading', $legalDocument->subheading) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Inquiries Contact Email</label>
                    <input type="email" name="contact_email" value="{{ old('contact_email', $legalDocument->contact_email) }}" placeholder="privacy@naoeroairlines.com.au" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Last Reviewed Date</label>
                    <input type="date" name="last_reviewed_at" value="{{ old('last_reviewed_at', $legalDocument->last_reviewed_at?->format('Y-m-d')) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Next Review Date</label>
                    <input type="date" name="next_review_at" value="{{ old('next_review_at', $legalDocument->next_review_at?->format('Y-m-d')) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload Official PDF Document</label>
                    <input type="file" name="pdf_file" accept=".pdf,.doc,.docx" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy">
                    @if($legalDocument->pdf_url)
                        <p class="text-xs text-slate-500 font-mono mt-2 truncate">Current File: {{ $legalDocument->pdf_url }}</p>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Or Direct PDF URL</label>
                    <input type="text" name="pdf_url" value="{{ old('pdf_url', $legalDocument->pdf_url) }}" placeholder="https://..." class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Document Short Summary</label>
                <textarea name="summary" rows="2" placeholder="Short description of this legal policy..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('summary', $legalDocument->summary) }}</textarea>
            </div>
        </div>

        <!-- Card 2: Interactive Visual Policy Clauses & Sections Builder -->
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-base font-bold text-navy flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-accent"></span>
                        <span>2. Policy Sections & Structured Clauses (Visual Editor)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Add, edit, reorder, or delete policy sections. Each section will render with clean headings and structured text on the frontend.</p>
                </div>

                <button type="button" 
                        @click="addSection()" 
                        class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-accent text-navy font-bold text-xs shadow-md shadow-accent/20 hover:bg-accent-hover transition-all self-start sm:self-auto">
                    <span class="text-sm leading-none">+</span>
                    <span>Add New Section</span>
                </button>
            </div>

            <!-- Empty State -->
            <template x-if="sections.length === 0">
                <div class="p-8 text-center border-2 border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
                    <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center mx-auto mb-3 text-lg font-bold">
                        §
                    </div>
                    <h3 class="text-sm font-bold text-navy">No structured sections added yet</h3>
                    <p class="text-xs text-slate-500 mt-1 mb-4">Click below to add your first policy section or clause.</p>
                    <button type="button" @click="addSection()" class="px-4 py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-navy-900 transition-colors">
                        + Add First Section
                    </button>
                </div>
            </template>

            <!-- Sections List -->
            <div class="space-y-4">
                <template x-for="(sec, idx) in sections" :key="idx">
                    <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/60 hover:bg-white hover:border-slate-300 transition-all shadow-sm space-y-3">
                        <!-- Section Header Bar -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-6 h-6 rounded-lg bg-navy text-white font-bold text-xs flex items-center justify-center" x-text="idx + 1"></span>
                                <span class="text-xs font-bold text-navy">Clause / Section #<span x-text="idx + 1"></span></span>
                            </div>

                            <div class="flex items-center gap-1">
                                <!-- Move Up -->
                                <button type="button" 
                                        @click="moveUp(idx)" 
                                        :disabled="idx === 0"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                        title="Move Up">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                </button>
                                <!-- Move Down -->
                                <button type="button" 
                                        @click="moveDown(idx)" 
                                        :disabled="idx === sections.length - 1"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-navy hover:bg-slate-200 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                                        title="Move Down">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <!-- Delete -->
                                <button type="button" 
                                        @click="removeSection(idx)" 
                                        class="p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition-colors ml-1"
                                        title="Remove Section">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>
                        </div>

                        <!-- Section Title / Heading Input -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Section Heading / Title
                            </label>
                            <input type="text" 
                                   :name="'sections[' + idx + '][heading]'" 
                                   x-model="sec.heading" 
                                   placeholder="e.g. 1. What Information We Collect" 
                                   class="w-full px-3.5 py-2 text-sm font-bold rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-navy">
                        </div>

                        <!-- Section Content / Clause Details -->
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Section Content / Clause Body
                            </label>
                            <textarea :name="'sections[' + idx + '][content]'" 
                                      x-model="sec.content" 
                                      rows="4" 
                                      placeholder="Write the detailed policy explanation, clauses, sub-points, or guidelines for this section..." 
                                      class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-white leading-relaxed focus:ring-2 focus:ring-navy"></textarea>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Bottom Add Section Button -->
            <div class="pt-2" x-show="sections.length > 0">
                <button type="button" 
                        @click="addSection()" 
                        class="w-full py-2.5 rounded-xl border-2 border-dashed border-slate-200 text-xs font-bold text-navy hover:border-accent hover:bg-accent/5 transition-all flex items-center justify-center gap-1.5">
                    <span class="text-sm font-extrabold text-accent">+</span>
                    <span>Add Another Clause / Section</span>
                </button>
            </div>
        </div>

        <!-- Card 3: Full Document Body (WYSIWYG Rich Editor) -->
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-navy border-b border-slate-100 pb-2 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>3. Complete Document Body (WYSIWYG Rich Editor)</span>
            </h2>
            <p class="text-xs text-slate-500">You can also provide the full formatted HTML document text with bold, lists, and links below.</p>

            <div>
                <textarea name="content" class="wysiwyg-editor" rows="8" placeholder="Enter complete formatted policy content here...">{{ old('content', $legalDocument->content) }}</textarea>
            </div>
        </div>

        <!-- Card 4: Publication & Submit -->
        <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-5">
            <div class="p-4 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200">
                <div>
                    <div class="text-sm font-bold text-navy">Publication Status</div>
                    <div class="text-xs text-slate-500">When published, this legal policy is active and served via public REST APIs.</div>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $legalDocument->is_active) ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.legal-documents.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-md hover:shadow-lg transition-all flex items-center gap-2">
                    <span>Save Legal Document</span>
                    <span>&rarr;</span>
                </button>
            </div>
        </div>
    </form>
</div>
@endsection
