@extends('layouts.admin')

@section('title', 'Edit Testimonial - ' . $testimonial->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    name: '{{ addslashes(old('name', $testimonial->name)) }}',
    location: '{{ addslashes(old('location', $testimonial->location ?? '')) }}',
    imageUrl: '{{ old('image_url', filter_var($testimonial->image, FILTER_VALIDATE_URL) ? $testimonial->image : '') }}',
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
            <a href="{{ route('admin.testimonials.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.testimonials.index') }}" class="hover:text-navy transition-colors">Passenger Reviews</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit Review</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit Passenger Testimonial</h1>
                <p class="text-xs text-ink-muted line-clamp-1 max-w-xl">Feedback from {{ $testimonial->name }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.testimonials.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('testimonial-edit-form').submit()" 
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

    <form id="testimonial-edit-form" method="POST" action="{{ route('admin.testimonials.update', $testimonial) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- CARD 1: Passenger Details -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Passenger Identity & Flight Details</h2>
                        <p class="text-xs text-ink-muted">Set traveler name, traveled route, star rating score, and display order.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Passenger Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           x-model="name"
                           value="{{ old('name', $testimonial->name) }}" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Location / Route -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Location / Route Flown
                    </label>
                    <input type="text" 
                           name="location" 
                           x-model="location"
                           value="{{ old('location', $testimonial->location) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Star Rating -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Star Rating (1 to 5 Stars)
                    </label>
                    <select name="rating" class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        <option value="5" {{ old('rating', $testimonial->rating) == 5 ? 'selected' : '' }}>⭐⭐⭐⭐⭐ 5 Stars - Exceptional</option>
                        <option value="4" {{ old('rating', $testimonial->rating) == 4 ? 'selected' : '' }}>⭐⭐⭐⭐ 4 Stars - Very Good</option>
                        <option value="3" {{ old('rating', $testimonial->rating) == 3 ? 'selected' : '' }}>⭐⭐⭐ 3 Stars - Average / Good</option>
                        <option value="2" {{ old('rating', $testimonial->rating) == 2 ? 'selected' : '' }}>⭐⭐ 2 Stars - Fair</option>
                        <option value="1" {{ old('rating', $testimonial->rating) == 1 ? 'selected' : '' }}>⭐ 1 Star - Poor</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', $testimonial->sort_order) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Quote & Review Body -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Passenger Review & Endorsement</h2>
                    <p class="text-xs text-ink-muted">The exact testimonial feedback detailing comfort, in-flight service, and overall travel experience.</p>
                </div>
            </div>

            <div>
                <textarea name="quote" 
                          rows="6" 
                          required
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-sans">{{ old('quote', $testimonial->quote) }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Avatar Photo -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Passenger Photo / Avatar</h2>
                    <p class="text-xs text-ink-muted">Replace current portrait photography.</p>
                </div>
            </div>

            @if($testimonial->image)
                <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <img src="{{ $testimonial->image_url }}" alt="{{ $testimonial->name }}" class="w-16 h-16 rounded-full object-cover border-2 border-accent shadow-sm shrink-0">
                    <div class="overflow-hidden">
                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-navy-100 text-navy uppercase tracking-wider mb-1">Current Avatar</span>
                        <p class="text-xs font-semibold text-slate-700 truncate">{{ $testimonial->image }}</p>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Replace Avatar File
                    </label>
                    <input type="file" 
                           name="image_file" 
                           accept="image/*"
                           @change="handleFileUpload($event)"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">Square ratio JPG, PNG up to 2MB.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Or Direct Avatar URL
                    </label>
                    <input type="url" 
                           name="image_url" 
                           x-model="imageUrl"
                           value="{{ old('image_url', filter_var($testimonial->image, FILTER_VALIDATE_URL) ? $testimonial->image : '') }}" 
                           placeholder="https://images.unsplash.com/..." 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>

            <!-- Live Preview -->
            <div x-show="localImagePreview" class="p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-center gap-4" x-cloak>
                <div class="w-16 h-16 rounded-full overflow-hidden border-2 border-amber-400 shrink-0 shadow-sm">
                    <img :src="localImagePreview" alt="Avatar" class="w-full h-full object-cover">
                </div>
                <div>
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-200 text-amber-900 uppercase tracking-wider mb-1">New Photo (Unsaved)</span>
                    <p class="text-xs font-bold text-navy" x-text="name || 'Passenger Name'"></p>
                </div>
            </div>
        </div>

        <!-- CARD 4: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Visibility</h2>
                    <p class="text-xs text-ink-muted">Control whether this review appears on customer reviews carousels.</p>
                </div>
            </div>

            <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors max-w-md">
                <div class="relative flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $testimonial->is_active) ? 'checked' : '' }}>
                    <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-navy block">Active & Published</span>
                    <span class="text-xs text-slate-500">Featured in homepage and reviews testimonial carousels.</span>
                </div>
            </label>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.testimonials.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
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
