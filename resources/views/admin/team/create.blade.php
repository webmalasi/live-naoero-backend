@extends('layouts.admin')

@section('title', 'Add Team Member')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    name: '{{ old('name', '') }}',
    role: '{{ old('role', '') }}',
    imageUrl: '{{ old('photo_url', '') }}',
    localImagePreview: null,
    handleFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.localImagePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.team-members.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.team-members.index') }}" class="hover:text-navy transition-colors">Team & Leadership</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Member</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Add Team Member</h1>
                <p class="text-xs text-ink-muted">Create profiles for Board of Directors, Executive Leadership, Flight Operations and Management.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.team-members.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('team-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Team Member</span>
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

    <form id="team-create-form" method="POST" action="{{ route('admin.team-members.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- CARD 1: Executive Profile & Designation -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Member Details & Hierarchy</h2>
                        <p class="text-xs text-ink-muted">Set full name, official leadership title, department classification, and display priority.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           x-model="name"
                           value="{{ old('name') }}" 
                           placeholder="e.g. Brett Gebers" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Role -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Official Designation / Role <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="role" 
                           x-model="role"
                           value="{{ old('role') }}" 
                           placeholder="e.g. Chief Executive Officer" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Department -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Department <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'team_departments']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Departments</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="department" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="">Select Department...</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept }}" {{ old('department') === $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', 0) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Bio & Career Profile -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Biography & Leadership Background</h2>
                    <p class="text-xs text-ink-muted">Aviation career highlights, achievements, and board responsibilities.</p>
                </div>
            </div>

            <div>
                <textarea name="bio" 
                          rows="6" 
                          placeholder="Provide executive biography and background summary..." 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('bio') }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Portrait Photo -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Executive Portrait Photo</h2>
                    <p class="text-xs text-ink-muted">Upload high quality portrait photography (3:4 or 1:1 ratio recommended).</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Upload Photo File
                    </label>
                    <input type="file" 
                           name="photo_file" 
                           accept="image/*"
                           @change="handleFileUpload($event)"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">Supports JPG, PNG, WebP up to 3MB.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Or Direct Photo URL
                    </label>
                    <input type="url" 
                           name="photo_url" 
                           x-model="imageUrl"
                           value="{{ old('photo_url') }}" 
                           placeholder="https://images.unsplash.com/..." 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>

            <!-- Live Preview -->
            <div x-show="localImagePreview || imageUrl" class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-4" x-cloak>
                <div class="w-20 h-24 rounded-xl overflow-hidden border border-slate-300 shrink-0 shadow-sm">
                    <img :src="localImagePreview || imageUrl" alt="Portrait" class="w-full h-full object-cover">
                </div>
                <div>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-navy-100 text-navy uppercase tracking-wider mb-1">Portrait Preview</span>
                    <p class="text-sm font-extrabold text-navy" x-text="name || 'Full Name'"></p>
                    <p class="text-xs text-slate-500" x-text="role || 'Designation'"></p>
                </div>
            </div>
        </div>

        <!-- CARD 4: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Visibility</h2>
                    <p class="text-xs text-ink-muted">Control whether this profile appears on the public About Us leadership grid.</p>
                </div>
            </div>

            <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors max-w-md">
                <div class="relative flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', true) ? 'checked' : '' }}>
                    <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-navy block">Active & Published</span>
                    <span class="text-xs text-slate-500">Visible on the About Us leadership & management directory.</span>
                </div>
            </label>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.team-members.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Team Member</span>
            </button>
        </div>
    </form>

</div>
@endsection
