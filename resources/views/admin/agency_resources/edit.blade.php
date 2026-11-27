@extends('layouts.admin')

@section('title', 'Edit Agency Policy - ' . $agencyResource->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.agency-resources.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Agency Resources</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Edit Agency Policy</h1>
            <p class="text-sm text-ink-muted">Modify {{ $agencyResource->title }} details and file attachment.</p>
        </div>
        <form method="POST" action="{{ route('admin.agency-resources.destroy', $agencyResource) }}" onsubmit="return confirm('Are you sure you want to delete this policy?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3.5 py-2 text-xs font-bold text-rose-600 hover:bg-rose-50 rounded-xl border border-rose-200">Delete Policy</button>
        </form>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.agency-resources.update', $agencyResource) }}" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Policy Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title', $agencyResource->title) }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Category
                    </label>
                    <input type="text" name="category" value="{{ old('category', $agencyResource->category) }}" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Description
                </label>
                <textarea name="description" rows="3" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('description', $agencyResource->description) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload New PDF Document</label>
                    <input type="file" name="file" accept=".pdf,.doc,.docx" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy">
                    @if($agencyResource->file_url)
                        <p class="text-xs text-slate-500 font-mono mt-2 truncate">Current: {{ $agencyResource->file_url }}</p>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Or PDF / Document URL</label>
                    <input type="text" name="file_url" value="{{ old('file_url', $agencyResource->file_url) }}" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $agencyResource->sort_order) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <div>
                        <div class="text-xs font-bold text-navy">Visibility Status</div>
                        <div class="text-[11px] text-slate-500">Show on agency resources page</div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $agencyResource->is_active) ? 'checked' : '' }} class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.agency-resources.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancel</a>
                <button type="submit" class="px-6 py-2.5 text-sm font-bold text-white bg-navy hover:bg-navy-dark rounded-xl shadow-sm hover:shadow transition-all">Update Policy</button>
            </div>
        </form>
    </div>
</div>
@endsection
