@extends('layouts.admin')

@section('title', 'Media & Photo Gallery')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Media & Photo Gallery</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $items->total() }} Photos
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage destination highlights, aircraft livery showcase, onboard experience, and cultural photos.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.gallery.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Photo</span>
            </a>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Total Gallery Photos</p>
                <p class="text-2xl font-extrabold text-navy mt-0.5">{{ $stats['total'] }}</p>
            </div>
        </div>

        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Active & Visible</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-0.5">{{ $stats['active'] }}</p>
            </div>
        </div>

        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-accent/20 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Photo Categories</p>
                <p class="text-2xl font-extrabold text-navy mt-0.5">{{ $stats['categories'] }}</p>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.gallery.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            
            <div class="lg:col-span-2 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search title, destination, category..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="category" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category', 'status']))
                    <a href="{{ route('admin.gallery.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors" title="Clear Filters">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        @forelse($items as $item)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col group">
                <div class="aspect-4/3 relative bg-slate-100 overflow-hidden">
                    <img src="{{ $item->src }}" alt="{{ $item->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <div class="absolute top-2.5 right-2.5 flex items-center gap-1.5">
                        <form method="POST" action="{{ route('admin.gallery.toggle-active', $item) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="px-2 py-0.5 rounded-full text-[11px] font-bold backdrop-blur-md {{ $item->is_active ? 'bg-emerald-500/90 text-white' : 'bg-slate-800/80 text-slate-300' }}">
                                {{ $item->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </form>
                    </div>
                    @if($item->category)
                        <div class="absolute bottom-2.5 left-2.5">
                            <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-navy/85 text-white backdrop-blur-sm">
                                {{ $item->category }}
                            </span>
                        </div>
                    @endif
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between">
                    <div>
                        <h3 class="font-bold text-navy text-sm line-clamp-1">{{ $item->title }}</h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ $item->destination ?: 'General Fleet & Pacific' }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-3 mt-3 border-t border-slate-100">
                        <span class="text-xs font-mono font-bold text-slate-400">#{{ $item->sort_order }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.gallery.edit', $item) }}" 
                               class="p-1.5 text-slate-500 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.gallery.destroy', $item) }}" 
                                  onsubmit="return confirm('Delete this gallery photo?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" title="Delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full naoero-card p-12 text-center bg-white border border-slate-200">
                <p class="text-base font-bold text-slate-600">No gallery photos found</p>
                <p class="text-xs text-slate-400 mt-1">Upload photos to showcase destinations, cabins, and flight experiences.</p>
            </div>
        @endforelse
    </div>

    @if($items->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $items->links() }}
        </div>
    @endif

</div>
@endsection
