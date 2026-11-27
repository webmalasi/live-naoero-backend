@extends('layouts.admin')

@section('title', 'Add History Milestone')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('admin.journey.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to Journey Hub</a>
            <h1 class="text-2xl font-extrabold text-navy mt-1">Add Timeline Milestone</h1>
            <p class="text-sm text-ink-muted">Create a historical milestone for the About Us animated timeline.</p>
        </div>
    </div>

    <div class="naoero-card p-6 bg-white border border-slate-200 shadow-sm">
        <form method="POST" action="{{ route('admin.journey.milestones.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Year / Period <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="year" value="{{ old('year') }}" required placeholder="e.g. 1970, 1985, 2024+" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Milestone Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Inception & First Flight" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Description <span class="text-rose-500">*</span>
                </label>
                <textarea name="description" rows="3" required placeholder="Describe this milestone in company history..." class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Upload Photo</label>
                    <input type="file" name="image_file" accept="image/*" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy/10 file:text-navy">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Or Image URL</label>
                    <input type="text" name="image" value="{{ old('image') }}" placeholder="/assets/about/ourjourney1.png" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200">
                </div>

                <div class="p-3 bg-slate-50 rounded-xl flex items-center justify-between border border-slate-200 mt-5">
                    <span class="text-xs font-bold text-navy">Active Status</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.journey.index') }}" class="px-5 py-2.5 text-sm font-bold text-slate-600 hover:text-navy">Cancel</a>
                <button type="submit" class="px-6 py-2.5 bg-navy hover:bg-navy-900 text-white font-bold text-sm rounded-xl shadow transition-all">Create Milestone</button>
            </div>
        </form>
    </div>
</div>
@endsection
