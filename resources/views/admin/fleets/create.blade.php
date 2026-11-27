@extends('layouts.admin')

@section('title', 'Add Aircraft to Fleet')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    type: '{{ old('type', '') }}',
    registration: '{{ old('registration', '') }}',
    imageUrl: '{{ old('image_url', '') }}',
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

    <!-- Top Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.fleets.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.fleets.index') }}" class="hover:text-navy transition-colors">Fleet Management</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Add Aircraft</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Add Aircraft to Fleet</h1>
                <p class="text-xs text-ink-muted">Register a Boeing passenger jet or dedicated air freighter with technical specs and seating plan.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.fleets.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('fleet-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Aircraft</span>
            </button>
        </div>
    </div>

    <!-- Error Summary (if any) -->
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

    <form id="fleet-create-form" method="POST" action="{{ route('admin.fleets.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- SECTION 1: Aircraft Identity & Specifications -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Aircraft Identity & Technical Specs</h2>
                        <p class="text-xs text-ink-muted">Select aircraft Boeing model, civil registration code, and seating arrangement.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Aircraft Model -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Aircraft Model <span class="text-rose-500">*</span></label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'fleet_types']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent">+ Manage Models</a>
                    </div>
                    <select name="type" required 
                            x-model="type"
                            class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="">-- Select Aircraft Model --</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ old('type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Registration Code -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Registration Code <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="registration" 
                           x-model="registration"
                           value="{{ old('registration') }}" 
                           placeholder="e.g. VH-INU" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm font-mono font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Category -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">Service Category <span class="text-rose-500">*</span></label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'fleet_categories']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent">+ Manage</a>
                    </div>
                    <select name="category" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Seating / Payload -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Seating / Payload Capacity
                    </label>
                    <input type="text" 
                           name="seating" 
                           value="{{ old('seating') }}" 
                           placeholder="e.g. 128 Seats (Economy & Business) or 20t Cargo" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Cabin Configuration -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Cabin / Hold Configuration
                    </label>
                    <input type="text" 
                           name="configuration" 
                           value="{{ old('configuration') }}" 
                           placeholder="e.g. 12 Business + 116 Economy Class" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Caption / Nickname -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Livery Caption / Aircraft Name
                    </label>
                    <input type="text" 
                           name="caption" 
                           value="{{ old('caption') }}" 
                           placeholder="e.g. VH-INU (Naoero Flagship Aircraft)" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- SECTION 2: Technical Description & Overview -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Technical Overview & Service History</h2>
                        <p class="text-xs text-ink-muted">Detailed background on engine specs, avionics, inflight features, and operational roles.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Description & Specifications (WYSIWYG Rich Editor)
                </label>
                <textarea name="description" 
                          rows="5" 
                          placeholder="Overview of this aircraft in the Naoero Airlines fleet..." 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('description') }}</textarea>
            </div>
        </div>

        <!-- SECTION 3: Livery Photography & Live Preview -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Aircraft Photography & Liveries</h2>
                        <p class="text-xs text-ink-muted">Upload high-resolution aircraft livery photography or provide external image URL.</p>
                    </div>
                </div>
            </div>

            <!-- Live Image Preview Card -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col md:flex-row items-center gap-5">
                <div class="w-full md:w-64 h-36 rounded-xl bg-navy-950 overflow-hidden relative shadow-md shrink-0 flex items-center justify-center border border-slate-200">
                    <template x-if="localImagePreview">
                        <img :src="localImagePreview" alt="Upload Preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!localImagePreview && imageUrl">
                        <img :src="imageUrl" alt="URL Preview" class="w-full h-full object-cover" onerror="this.style.display='none'">
                    </template>
                    <template x-if="!localImagePreview && !imageUrl">
                        <div class="text-center p-3 text-slate-400">
                            <span class="text-2xl block mb-1">✈️</span>
                            <span class="text-[11px] font-bold">No Aircraft Photo</span>
                        </div>
                    </template>

                    <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-white text-[10px] font-bold bg-navy-950/80 px-2 py-1 rounded-lg backdrop-blur-sm">
                        <span class="truncate" x-text="type || 'Boeing Aircraft'"></span>
                        <span class="text-accent shrink-0 font-mono" x-text="registration || ''"></span>
                    </div>
                </div>

                <div class="flex-1 space-y-3 w-full">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Upload File (PNG, JPG, WebP up to 5MB)
                        </label>
                        <input type="file" 
                               name="image_file" 
                               accept="image/*"
                               @change="handleFileUpload($event)"
                               class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy-900 cursor-pointer">
                    </div>

                    <div class="pt-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Or External Image URL
                        </label>
                        <input type="url" 
                               name="image_url" 
                               x-model="imageUrl"
                               value="{{ old('image_url') }}" 
                               placeholder="https://..." 
                               class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Status & Sorting -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Display & Fleet Status</h2>
                        <p class="text-xs text-ink-muted">Set listing priority and whether this aircraft is active in flight operations.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Priority / Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', 0) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold">
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-navy">Active in Fleet</span>
                        <span class="text-[11px] text-slate-500">Enable to display this aircraft on the public fleet page.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', true) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Bottom Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.fleets.index') }}" 
               class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-sm shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Aircraft</span>
            </button>
        </div>
    </form>

</div>
@endsection
