@extends('layouts.admin')

@section('title', 'Edit Gallery Photo - ' . $item->title)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    title: '{{ addslashes(old('title', $item->title)) }}',
    imageUrl: '{{ old('image_url', $item->image_url ?? (filter_var($item->image, FILTER_VALIDATE_URL) ? $item->image : '')) }}',
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
            <a href="{{ route('admin.gallery.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.gallery.index') }}" class="hover:text-navy transition-colors">Media Gallery</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit Photo</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit Gallery Photo</h1>
                <p class="text-xs text-ink-muted line-clamp-1 max-w-xl">{{ $item->title }} &bull; {{ $item->category }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.gallery.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('gallery-edit-form').submit()" 
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

    <form id="gallery-edit-form" method="POST" action="{{ route('admin.gallery.update', $item) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- CARD 1: Photo Details & Categorization -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Photo Details & Classification</h2>
                        <p class="text-xs text-ink-muted">Update photo caption, destination tag, category grouping, and display order.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Title / Caption -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Photo Title / Caption <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           value="{{ old('title', $item->title) }}" 
                           required 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Destination -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Destination or Station
                    </label>
                    <input type="text" 
                           name="destination" 
                           value="{{ old('destination', $item->destination) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Category -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'gallery_categories']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Categories</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="category" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        @foreach($categories as $cat)
                            <option value="{{ $cat }}" {{ old('category', $item->category) === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
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
                           value="{{ old('sort_order', $item->sort_order) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Photo Media Upload & Preview -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Photo Media & Source</h2>
                    <p class="text-xs text-ink-muted">Replace image file or update remote photo link.</p>
                </div>
            </div>

            <!-- Current Image Preview -->
            <div class="flex items-center gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                <img src="{{ $item->src }}" alt="{{ $item->title }}" class="w-28 h-20 rounded-lg object-cover border border-slate-300 shadow-sm shrink-0">
                <div class="overflow-hidden">
                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-navy-100 text-navy uppercase tracking-wider mb-1">Current Photo</span>
                    <p class="text-xs font-semibold text-slate-700 truncate">{{ $item->image_url ?: $item->image }}</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Upload a replacement below to overwrite.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Replace Image File
                    </label>
                    <input type="file" 
                           name="image_file" 
                           accept="image/*"
                           @change="handleFileUpload($event)"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy-50 file:text-navy hover:file:bg-navy-100 cursor-pointer border border-slate-200 rounded-xl p-1 bg-slate-50/50">
                    <p class="text-[11px] text-slate-400 mt-1">High-res JPG, PNG, or WebP up to 10MB.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Or External Image URL
                    </label>
                    <input type="url" 
                           name="image_url" 
                           x-model="imageUrl"
                           value="{{ old('image_url', $item->image_url) }}" 
                           placeholder="https://images.unsplash.com/..." 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>

            <!-- Live Image Preview Box -->
            <div x-show="localImagePreview" class="p-4 rounded-xl bg-amber-50 border border-amber-200" x-cloak>
                <p class="text-xs font-bold text-amber-900 uppercase tracking-wider mb-2">New Image Preview (Unsaved)</p>
                <div class="relative rounded-xl overflow-hidden max-h-72 bg-slate-200 border border-slate-300">
                    <img :src="localImagePreview" alt="Preview" class="w-full h-64 object-cover">
                </div>
            </div>
        </div>

        <!-- CARD 3: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Visibility</h2>
                    <p class="text-xs text-ink-muted">Control whether this image is visible in the public media gallery.</p>
                </div>
            </div>

            <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors max-w-md">
                <div class="relative flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $item->is_active) ? 'checked' : '' }}>
                    <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-navy block">Active & Visible</span>
                    <span class="text-xs text-slate-500">Display this photo in gallery grids and category filters.</span>
                </div>
            </label>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.gallery.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
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
