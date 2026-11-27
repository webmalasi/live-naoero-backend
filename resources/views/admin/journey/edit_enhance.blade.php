@extends('layouts.admin')

@section('title', 'Edit Enhance Journey Card - ' . $item->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.journey.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Journey Hub</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit Enhance Card</h1>
            <p class="text-sm text-ink-muted">Modify {{ $item->title }} essential card details.</p>
        </div>
        <form method="POST" action="{{ route('admin.journey.enhance.destroy', $item) }}" onsubmit="return confirm('Are you sure you want to delete this card?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl border border-rose-200">Delete Card</button>
        </form>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.journey.enhance.update', $item) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Card Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $item->title) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Lucide Icon Key
                    </label>
                    <input type="text" name="icon" value="{{ old('icon', $item->icon) }}" placeholder="e.g. Hotel, Car, ShieldCheck, Compass" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                    <p class="text-[11px] text-slate-400 mt-1">Lucide icon identifier (e.g. Hotel, Car, ShieldCheck, Compass)</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Description <span class="text-rose-500">*</span>
                </label>
                <textarea name="description" rows="3" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('description', $item->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload New Photo</label>
                    <input type="file" name="image_file" accept="image/*" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy">
                    @if($item->image)
                        <div class="mt-2 flex items-center space-x-2">
                            <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-16 h-12 object-cover rounded-lg border border-slate-200">
                            <span class="text-xs text-slate-500 font-mono truncate max-w-[200px]">{{ $item->image }}</span>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Or Image URL</label>
                    <input type="text" name="image" value="{{ old('image', $item->image) }}" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Button / CTA Text</label>
                    <input type="text" name="link_text" value="{{ old('link_text', $item->link_text) }}" placeholder="Know More" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Target Link URL</label>
                    <input type="text" name="link_url" value="{{ old('link_url', $item->link_url) }}" placeholder="e.g. /hotels or https://..." class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $item->sort_order) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Card Visibility</div>
                        <div class="text-[11px] text-slate-500">Show card on homepage</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $item->is_active) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.journey.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-sm hover:shadow transition-all">Update Card</button>
            </div>
        </form>
    </div>
</div>
@endsection
