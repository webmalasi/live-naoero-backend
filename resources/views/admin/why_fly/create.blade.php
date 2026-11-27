@extends('layouts.admin')

@section('title', 'Add Why Fly With Us Card')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.why-fly.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy flex items-center gap-1">
                    &larr; Back to Why Fly Cards
                </a>
            </div>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Add Why Fly Feature Card</h1>
            <p class="text-sm text-ink-muted">Create a key selling point card for the Homepage (e.g., Trusted Routes, No Hidden Fees, Certified Safety).</p>
        </div>
    </div>

    <!-- Form Card -->
    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.why-fly.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- 1. Title & Icon -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    1. Card Title & Icon
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Card Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" 
                               name="title" 
                               value="{{ old('title') }}" 
                               required 
                               placeholder="e.g. Trusted Routes, Certified Safety"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Lucide Icon Name
                        </label>
                        <input type="text" 
                               name="icon" 
                               value="{{ old('icon', 'Plane') }}" 
                               placeholder="Plane, Package, UtensilsCrossed, ShieldCheck, HeartHandshake"
                               class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                        <span class="text-[11px] text-slate-400 mt-1 block">Supported: Plane, Package, UtensilsCrossed, ShieldCheck, HeartHandshake, Luggage, Clock, Award</span>
                    </div>
                </div>
            </div>

            <!-- 2. Description -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    2. Card Description Text
                </h3>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Description / Selling Point <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="description" 
                              rows="3" 
                              required 
                              placeholder="e.g. The official national carrier connecting the Pacific for over 50 years."
                              class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">{{ old('description') }}</textarea>
                </div>
            </div>

            <!-- 3. Optional Upload & Custom Order -->
            <div class="space-y-4">
                <h3 class="text-sm font-extrabold text-navy uppercase tracking-wider border-b border-slate-100 pb-2">
                    3. Optional Custom Icon & Sort Order
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Upload Custom Icon / Badge (Optional)
                        </label>
                        <input type="file" 
                               name="icon_image_file" 
                               accept="image/png,image/jpeg,image/svg+xml,image/webp"
                               class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy hover:file:bg-navy/20">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Display Sort Order
                        </label>
                        <input type="number" 
                               name="sort_order" 
                               value="{{ old('sort_order', 0) }}" 
                               min="0"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    </div>
                </div>
            </div>

            <!-- 4. Active Status -->
            <div class="p-4 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200">
                <div>
                    <span class="text-sm font-bold text-navy block">Active Status</span>
                    <span class="text-xs text-slate-500">Enable this card immediately on the public Homepage and API.</span>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.why-fly.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-navy">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 bg-navy hover:bg-navy-900 text-white font-bold text-sm rounded-xl shadow-md transition-all">
                    Create Card
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
