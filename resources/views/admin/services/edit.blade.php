@extends('layouts.admin')

@section('title', 'Edit Airline Service - ' . $service->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.services.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy flex items-center gap-1">
                    &larr; Back to Services
                </a>
            </div>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit Service: {{ $service->title }}</h1>
            <p class="text-sm text-ink-muted">Modify service details, features, images, and destination link.</p>
        </div>
    </div>

    <!-- Form Card -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.services.update', $service) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- 1. Service Title & Identification -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    1. Basic Information & Type
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Service Title / Heading <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="title" 
                               value="{{ old('title', $service->title) }}" 
                               required 
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            URL Slug
                        </label>
                        <input type="text" 
                               name="slug" 
                               value="{{ old('slug', $service->slug) }}" 
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Service Category / Type <span class="text-rose-500">*</span>
                        </label>
                        <select name="type" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
                            <option value="passenger" {{ old('type', $service->type) === 'passenger' ? 'selected' : '' }}>Passenger Services</option>
                            <option value="cargo" {{ old('type', $service->type) === 'cargo' ? 'selected' : '' }}>Cargo & Freight Services</option>
                            <option value="charter" {{ old('type', $service->type) === 'charter' ? 'selected' : '' }}>Private Aircraft Charter</option>
                            <option value="acmi" {{ old('type', $service->type) === 'acmi' ? 'selected' : '' }}>ACMI & Wet Lease Solutions</option>
                            <option value="special" {{ old('type', $service->type) === 'special' ? 'selected' : '' }}>Special Services</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Icon Identifier
                        </label>
                        <input type="text" 
                               name="icon" 
                               value="{{ old('icon', $service->icon) }}" 
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Sort / Display Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               value="{{ old('sort_order', $service->sort_order) }}" 
                               min="0"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 2. Description & Key Features -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    2. Description & Bullet Points
                </h3>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Service Description <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="description" 
                              rows="4" 
                              required 
                              class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">{{ old('description', $service->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Key Features / Bullet Points (One per line)
                    </label>
                    @php
                        $featuresText = is_array($service->features) ? implode("\n", $service->features) : $service->features;
                    @endphp
                    <textarea name="features" 
                              rows="4" 
                              data-no-editor="true"
                              class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">{{ old('features', $featuresText) }}</textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Enter each bullet point feature on a separate new line.</p>
                </div>
            </div>

            <!-- 3. Call-to-Action (CTA) -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    3. Call to Action (CTA Button)
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Button Label Text
                        </label>
                        <input type="text" 
                               name="cta_text" 
                               value="{{ old('cta_text', $service->cta_text) }}" 
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Button Target Link
                        </label>
                        <input type="text" 
                               name="cta_link" 
                               value="{{ old('cta_link', $service->cta_link) }}" 
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 4. Media & Image Upload -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    4. Card / Showcase Image
                </h3>

                @if($service->image_url)
                    <div class="mb-3">
                        <span class="block text-xs font-bold text-slate-500 mb-1">Current Image Preview:</span>
                        <div class="h-32 w-56 rounded-xl border border-slate-200 overflow-hidden bg-slate-100">
                            <img src="{{ $service->image_url }}" alt="{{ $service->title }}" class="w-full h-full object-cover">
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Upload New Service Photo
                        </label>
                        <input type="file" 
                               name="image_file" 
                               accept="image/png,image/jpeg,image/webp"
                               class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy hover:file:bg-navy/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Or Image URL / Path
                        </label>
                        <input type="text" 
                               name="image" 
                               value="{{ old('image', $service->image) }}" 
                               placeholder="https://images.unsplash.com/... or /assets/services/service1.jpg"
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 5. Active Status -->
            <div class="p-4 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200">
                <div>
                    <span class="text-sm font-bold text-navy block">Active Status</span>
                    <span class="text-xs text-slate-500">Make this service immediately visible in the public API and services page.</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $service->is_active) ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.services.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-navy">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-navy hover:bg-navy-900 text-white font-bold text-sm rounded-xl shadow-md transition-all">
                    Update Service
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
