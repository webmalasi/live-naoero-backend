@extends('layouts.admin')

@section('title', 'Edit Vacancy - ' . $career->title)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    title: '{{ addslashes(old('title', $career->title)) }}',
    slug: '{{ old('slug', $career->slug) }}'
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.careers.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.careers.index') }}" class="hover:text-navy transition-colors">Careers & Recruitment</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit Vacancy</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit Job Vacancy</h1>
                <p class="text-xs text-ink-muted line-clamp-1 max-w-xl">{{ $career->title }} ({{ $career->location }})</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.careers.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('career-edit-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </div>

    <!-- Error Alert Box -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs shadow-sm">
            <div class="flex items-center gap-2 font-bold mb-1 text-sm text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please correct the form errors:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="career-edit-form" method="POST" action="{{ route('admin.careers.update', $career) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- CARD 1: Position Details & Taxonomy -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Position Identity & Taxonomy</h2>
                        <p class="text-xs text-ink-muted">Update role title, department classification, work station base, and employment type.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Title -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Position Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           value="{{ old('title', $career->title) }}" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        URL Slug
                    </label>
                    <input type="text" 
                           name="slug" 
                           x-model="slug"
                           value="{{ old('slug', $career->slug) }}" 
                           class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">Live URL: /careers/<span x-text="slug || 'slug'"></span></p>
                </div>

                <!-- Department -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Department <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'career_departments']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Departments</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="department" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="">Select Department...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ old('department', $career->department) === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Location -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Base / Station Location <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'career_locations']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Locations</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="location" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="">Select Base Station...</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc }}" {{ old('location', $career->location) === $loc ? 'selected' : '' }}>{{ $loc }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Employment Type -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Employment Type <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'career_types']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Types</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="employment_type" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        @foreach($employmentTypes as $empType)
                            <option value="{{ $empType }}" {{ old('employment_type', $career->employment_type) === $empType ? 'selected' : '' }}>{{ $empType }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Application Deadline -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Application Closing Deadline
                    </label>
                    <input type="date" 
                           name="deadline" 
                           value="{{ old('deadline', $career->deadline ? $career->deadline->format('Y-m-d') : '') }}" 
                           class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                </div>

                <!-- Seek / External Link -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Seek.com / External Application URL
                    </label>
                    <input type="url" 
                           name="seek_url" 
                           value="{{ old('seek_url', $career->seek_url) }}" 
                           placeholder="https://www.seek.com.au/job/..." 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Role Summary -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Short Summary & Role Pitch</h2>
                    <p class="text-xs text-ink-muted">A concise teaser displayed on career cards and search result snippets.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Short Description
                </label>
                <textarea name="short_description" 
                          rows="3" 
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent leading-relaxed">{{ old('short_description', $career->short_description) }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Full Job Description -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Full Job Description</h2>
                    <p class="text-xs text-ink-muted">Detailed responsibilities, duty roster specifications, and role scope.</p>
                </div>
            </div>

            <div>
                <textarea name="description" 
                          rows="8" 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('description', $career->description) }}</textarea>
            </div>
        </div>

        <!-- CARD 4: Requirements & Qualifications -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Role Requirements & Qualifications</h2>
                    <p class="text-xs text-ink-muted">Aviation licenses (CASA ATPL/CPL), medical class, total flight hours, and citizenship requirements.</p>
                </div>
            </div>

            <div>
                <textarea name="requirements" 
                          rows="8" 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('requirements', $career->requirements) }}</textarea>
            </div>
        </div>

        <!-- CARD 5: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">5</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Order</h2>
                    <p class="text-xs text-ink-muted">Control opening status and sequence on the careers portal.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', $career->sort_order) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors">
                    <div class="relative flex items-center mt-0.5">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $career->is_active) ? 'checked' : '' }}>
                        <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </div>
                    <div>
                        <span class="text-sm font-extrabold text-navy block">Open for Applications</span>
                        <span class="text-xs text-slate-500">Enable candidate application submission on the portal.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.careers.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </form>

</div>
@endsection
