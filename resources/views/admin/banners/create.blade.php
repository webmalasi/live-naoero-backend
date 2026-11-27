@extends('layouts.admin')

@section('title', 'Add New Page Banner')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.banners.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy flex items-center gap-1">
                    &larr; Back to Banners
                </a>
            </div>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Add New Frontend Page Banner</h1>
            <p class="text-sm text-ink-muted">Configure hero banner image, title heading, description text, and action button for a frontend page.</p>
        </div>
    </div>

    <!-- Form Card -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- 1. Page Details -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    1. Page Association & Target Route
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Page Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="page_name" 
                               value="{{ old('page_name') }}" 
                               required 
                               placeholder="e.g. Destinations Hub"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Page Key / Slug <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="page_key" 
                               value="{{ old('page_key') }}" 
                               required 
                               placeholder="e.g. destinations"
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Page Route URL
                        </label>
                        <input type="text" 
                               name="page_route" 
                               value="{{ old('page_route') }}" 
                               placeholder="e.g. /destinations"
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 2. Hero Content -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    2. Banner Typography & Hero Text
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Top Badge / Sub-heading Text
                        </label>
                        <input type="text" 
                               name="banner_badge_text" 
                               value="{{ old('banner_badge_text') }}" 
                               placeholder="e.g. Pacific Route Network"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold text-accent-dark">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Main Banner Heading <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="banner_heading" 
                               value="{{ old('banner_heading') }}" 
                               required 
                               placeholder="e.g. Explore Our Pacific Destinations"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-extrabold text-navy">
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Banner Description / Subtitle
                        </label>
                        <textarea name="banner_description" 
                                  rows="3" 
                                  placeholder="Enter engaging hero banner description for passengers..."
                                  class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">{{ old('banner_description') }}</textarea>
                    </div>
                </div>
            </div>

            <!-- 3. Visual Banner Image -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    3. Banner Background Image
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Upload High-Res Banner Image
                            <span class="text-[10px] text-slate-400 font-normal">(PNG, JPG, WebP up to 5MB, recommended 1920x800)</span>
                        </label>
                        <input type="file" 
                               name="banner_image_file" 
                               accept="image/*"
                               class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy/90 file:cursor-pointer border border-slate-200 rounded-xl p-1.5">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Or Specify Image URL / CDN Link
                        </label>
                        <input type="text" 
                               name="banner_image" 
                               value="{{ old('banner_image') }}" 
                               placeholder="https://images.unsplash.com/... or /assets/..."
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 4. CTA Button & Controls -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    4. Call To Action (CTA) Button & Display Settings
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-center">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Button Text
                        </label>
                        <input type="text" 
                               name="banner_button_text" 
                               value="{{ old('banner_button_text') }}" 
                               placeholder="e.g. Explore Routes"
                               class="w-full px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Button Link / URL
                        </label>
                        <input type="text" 
                               name="banner_button_link" 
                               value="{{ old('banner_button_link') }}" 
                               placeholder="e.g. /travel-info/flight-schedule"
                               class="w-full px-3.5 py-2 text-xs font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Sort Priority Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               value="{{ old('sort_order', 0) }}" 
                               min="0"
                               class="w-full px-3.5 py-2 text-xs font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div class="flex items-center pt-4">
                        <label class="inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" class="sr-only peer" checked>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                            <span class="ml-3 text-xs font-bold text-slate-700">Display Live</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.banners.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-all">
                    Cancel
                </a>
                <button type="submit" class="px-7 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-sm shadow-md shadow-accent/20 transition-all">
                    Create Page Banner
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
