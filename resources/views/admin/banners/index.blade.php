@extends('layouts.admin')

@section('title', 'Page Banners & Hero Images')

@section('content')
<div class="space-y-6">

    <!-- Header & Statistics -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Frontend Page Banners & Hero Manager</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total'] }} Pages
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Configure high-resolution banner images, hero headings, descriptions, and CTA button text/links for all frontend pages.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.banners.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Page Banner</span>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Configured Pages</span>
                <span class="text-2xl font-extrabold text-navy mt-1 block">{{ $stats['total'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-navy/10 flex items-center justify-center text-navy font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider block">Active Live Banners</span>
                <span class="text-2xl font-extrabold text-emerald-700 mt-1 block">{{ $stats['active'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Inactive / Draft Banners</span>
                <span class="text-2xl font-extrabold text-slate-600 mt-1 block">{{ $stats['inactive'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.banners.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-8 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search by page name, key, heading, description..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div class="sm:col-span-3">
                <select name="status" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <div class="sm:col-span-1 flex items-center gap-1">
                <button type="submit" class="w-full py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-navy/90 transition-all flex items-center justify-center">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Banners Grid / List -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($banners as $banner)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col justify-between shadow-sm hover:shadow-md transition-all">
                <div>
                    <!-- Banner Image with Overlay Preview -->
                    <div class="relative h-44 bg-navy-950 overflow-hidden group">
                        <img src="{{ $banner->banner_image_url }}" 
                             alt="{{ $banner->page_name }}" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/90 via-navy-950/40 to-transparent"></div>

                        <!-- Page Key & Badge -->
                        <div class="absolute top-3 left-3 flex flex-wrap gap-1">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase tracking-wider bg-navy/80 text-accent border border-accent/30 shadow-md backdrop-blur-sm">
                                {{ $banner->page_key }}
                            </span>
                            @if($banner->banner_badge_text)
                                <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-white/20 text-white backdrop-blur-sm">
                                    {{ $banner->banner_badge_text }}
                                </span>
                            @endif
                        </div>

                        <!-- Live Status Badge -->
                        <div class="absolute top-3 right-3">
                            <form method="POST" action="{{ route('admin.banners.toggle-active', $banner) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="px-2.5 py-1 rounded-full text-[11px] font-bold shadow-sm transition-all {{ $banner->is_active ? 'bg-emerald-500 hover:bg-emerald-600 text-white' : 'bg-slate-500 hover:bg-slate-600 text-white' }}">
                                    {{ $banner->is_active ? 'Live Active' : 'Hidden / Off' }}
                                </button>
                            </form>
                        </div>

                        <!-- Banner Heading & Route Preview -->
                        <div class="absolute bottom-3 left-3 right-3 text-white">
                            <span class="text-[11px] text-accent font-mono font-bold block truncate">
                                Route: {{ $banner->page_route ?: '/' . $banner->page_key }}
                            </span>
                            <h3 class="text-sm font-extrabold line-clamp-1 text-white mt-0.5">
                                {{ $banner->banner_heading }}
                            </h3>
                        </div>
                    </div>

                    <!-- Details Body -->
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <span class="text-xs font-bold text-navy">{{ $banner->page_name }}</span>
                            <span class="text-[11px] text-slate-400 font-mono">Order #{{ $banner->sort_order }}</span>
                        </div>

                        <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">
                            {{ $banner->banner_description ?: 'No description provided.' }}
                        </p>

                        <!-- CTA Button Preview -->
                        @if($banner->banner_button_text)
                            <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                                <span class="text-slate-500 font-medium">Button Text:</span>
                                <span class="font-bold text-navy px-2 py-0.5 rounded bg-accent/30 text-[11px] flex items-center gap-1">
                                    {{ $banner->banner_button_text }} &rarr;
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-4 bg-slate-50/70 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[10px] text-slate-400 font-mono truncate max-w-[140px]">
                        Updated {{ $banner->updated_at->diffForHumans() }}
                    </span>

                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.banners.edit', $banner) }}" 
                           class="px-3 py-1.5 rounded-lg bg-navy text-white hover:bg-navy/90 font-bold text-xs transition-all flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            <span>Edit Banner</span>
                        </a>

                        <form method="POST" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('Are you sure you want to delete banner for {{ $banner->page_name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-all" title="Delete">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 naoero-card p-12 text-center bg-white border border-slate-200">
                <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="text-base font-bold text-navy">No Page Banners Found</h3>
                <p class="text-xs text-slate-500 mt-1">Run seeds or add a new custom banner for frontend pages.</p>
                <a href="{{ route('admin.banners.create') }}" class="inline-block mt-4 px-4 py-2 rounded-xl bg-accent text-navy font-bold text-xs">
                    Create First Banner
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $banners->links() }}
    </div>

</div>
@endsection
