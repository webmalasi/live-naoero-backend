@extends('layouts.admin')

@section('title', 'Travel Agency Resources & Policies')

@section('content')
<div class="space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Agency Resources & Policies</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total'] }} Policies &bull; {{ $stats['active'] }} Active
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage downloadable trade policies, GDS guides, ticketing rules, and portal instructions for travel agents.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.agency-resources.create') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-bold text-xs shadow transition-all">
                <span class="text-accent">+</span>
                <span>Add Agency Policy</span>
            </a>
        </div>
    </div>

    <!-- Page Header & Intro Editor -->
    <div class="naoero-card p-5 bg-white border border-slate-200">
        <h3 class="text-sm font-extrabold text-navy mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Agency Resources Page Header & Introduction</span>
        </h3>
        <form method="POST" action="{{ route('admin.agency-resources.update-headings') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Page Title Heading</label>
                <input type="text" name="agency_resources_heading" value="{{ old('agency_resources_heading', $heading) }}" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 font-bold focus:ring-2 focus:ring-navy">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Introduction & GDS Ticketing Text</label>
                <textarea name="agency_resources_intro" rows="3" class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 focus:ring-2 focus:ring-navy">{{ old('agency_resources_intro', $intro) }}</textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow transition-all">
                    Save Header Text
                </button>
            </div>
        </form>
    </div>

    <!-- Policies Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        @forelse($resources as $item)
            <div class="naoero-card bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-mono font-bold text-navy bg-slate-100 px-2 py-0.5 rounded">{{ ucfirst($item->category ?? 'Policy') }}</span>
                        <form method="POST" action="{{ route('admin.agency-resources.toggle-active', $item) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-2 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all {{ $item->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </div>

                    <div class="w-10 h-10 rounded-xl bg-navy/10 text-navy flex items-center justify-center mb-3">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>

                    <h3 class="font-bold text-navy text-sm mb-1 line-clamp-2">{{ $item->title }}</h3>
                    <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">{{ $item->description }}</p>
                </div>

                <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-[11px] font-mono text-slate-400">#{{ $item->sort_order }}</span>
                        @if($item->has_pdf)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-bold border rounded-lg bg-emerald-50 text-emerald-700 border-emerald-200" title="This card has a PDF">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4"/></svg>
                                PDF
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-bold border rounded-lg bg-rose-50 text-rose-700 border-rose-200" title="This card has no PDF">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 12l4 4m0-4l-4 4"/></svg>
                                No PDF
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center gap-1">
                        @if($item->file_url)
                            <a href="{{ $item->download_url }}" target="_blank" class="p-1.5 text-slate-500 hover:text-navy rounded-lg" title="View Document">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        @endif
                        <a href="{{ route('admin.agency-resources.edit', $item) }}" class="p-1.5 text-navy hover:bg-navy/10 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </a>
                        <form method="POST" action="{{ route('admin.agency-resources.destroy', $item) }}" onsubmit="return confirm('Delete this policy?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 text-center bg-white rounded-2xl border border-slate-200">
                <p class="text-sm text-slate-500 font-bold">No agency policies configured yet.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $resources->links() }}
    </div>

</div>
@endsection
