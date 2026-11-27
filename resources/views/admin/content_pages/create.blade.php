@extends('layouts.admin')

@section('title', 'New Page')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <a href="{{ route('admin.content-pages.index') }}" class="text-xs font-bold text-slate-500 hover:text-navy">&larr; Back to pages</a>
        <h1 class="text-2xl font-extrabold text-navy mt-1">New page</h1>
        <p class="text-sm text-ink-muted">The page starts hidden. Add its sections, then publish it. The website and the app read it from <span class="font-mono">/api/pages/&lt;address&gt;</span>.</p>
    </div>

    <form method="POST" action="{{ route('admin.content-pages.store') }}" class="naoero-card p-6 bg-white border border-slate-200 shadow-sm space-y-4">
        @csrf
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Title <span class="text-rose-500">*</span></label>
            <input type="text" name="title" value="{{ old('title') }}" required class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Address (optional)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="made from the title" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                <p class="text-[11px] text-slate-400 mt-1">Lower-case letters, numbers and dashes.</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Group</label>
                <select name="group" class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
                    @foreach(\App\Models\ContentPage::GROUPS as $value => $label)
                        <option value="{{ $value }}" @selected(old('group') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Website address (optional)</label>
            <input type="text" name="path" value="{{ old('path') }}" placeholder="/my-page" class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">
        </div>
        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2.5 text-sm font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm">Create page</button>
        </div>
    </form>
</div>
@endsection
