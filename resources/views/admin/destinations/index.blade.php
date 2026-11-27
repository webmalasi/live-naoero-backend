@extends('layouts.admin')

@section('title', 'Destinations Management')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Flight Destinations</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $destinations->total() }} Destinations
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage flight network destinations, home explore cards, and destination detail pages.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.destinations.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Destination</span>
            </a>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Total Destinations</p>
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
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Active & Published</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-0.5">{{ $stats['active'] }}</p>
            </div>
        </div>

        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-accent/20 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Home Featured</p>
                <p class="text-2xl font-extrabold text-navy mt-0.5">{{ $stats['featured'] }}</p>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.destinations.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            
            <!-- Search -->
            <div class="lg:col-span-2 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search destination, country, slug..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Status Filter -->
            <div>
                <select name="status" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <!-- Featured Filter -->
            <div>
                <select name="featured" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Featured States</option>
                    <option value="1" {{ request('featured') === '1' ? 'selected' : '' }}>Home Featured</option>
                    <option value="0" {{ request('featured') === '0' ? 'selected' : '' }}>Standard Listing</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status', 'featured', 'sort_by']))
                    <a href="{{ route('admin.destinations.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors" title="Clear Filters">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Destinations Table & Cards -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Destination</th>
                        <th class="px-5 py-3.5">Country</th>
                        <th class="px-5 py-3.5">Slug</th>
                        <th class="px-5 py-3.5 text-center">Featured (Home)</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Order</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($destinations as $destination)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <!-- Destination Image & Name -->
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="w-14 h-10 rounded-lg overflow-hidden bg-slate-100 shrink-0 border border-slate-200">
                                        @if($destination->thumbnail_url || $destination->thumbnail)
                                            <img src="{{ $destination->thumbnail_url ?: $destination->thumbnail }}" 
                                                 alt="{{ $destination->name }}" 
                                                 class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.destinations.show', $destination) }}" class="font-bold text-navy hover:text-accent-hover transition-colors">
                                            {{ $destination->name }}
                                        </a>
                                        <p class="text-xs text-slate-500 line-clamp-1 max-w-xs">{{ $destination->short_description ?: 'No short description provided.' }}</p>
                                    </div>
                                </div>
                            </td>

                            <!-- Country & Flag -->
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center gap-1.5 font-medium text-slate-700">
                                    @if($destination->flag)
                                        <span class="text-base">{{ $destination->flag }}</span>
                                    @endif
                                    <span>{{ $destination->country }}</span>
                                </span>
                            </td>

                            <!-- Slug -->
                            <td class="px-5 py-3.5">
                                <code class="px-2 py-0.5 text-xs font-mono rounded bg-slate-100 text-slate-600 border border-slate-200">
                                    /destinations/{{ $destination->slug }}
                                </code>
                            </td>

                            <!-- Featured Toggle -->
                            <td class="px-5 py-3.5 text-center">
                                <form method="POST" action="{{ route('admin.destinations.toggle-featured', $destination) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold transition-all {{ $destination->is_featured ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-slate-100 text-slate-500 hover:bg-slate-200' }}"
                                            title="Click to toggle featured status">
                                        @if($destination->is_featured)
                                            <svg class="w-3.5 h-3.5 text-amber-600 fill-amber-500" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                            <span>Featured</span>
                                        @else
                                            <span>Standard</span>
                                        @endif
                                    </button>
                                </form>
                            </td>

                            <!-- Active Status Toggle -->
                            <td class="px-5 py-3.5 text-center">
                                <form method="POST" action="{{ route('admin.destinations.toggle-active', $destination) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold transition-all {{ $destination->is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                                            title="Click to toggle active status">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $destination->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        <span>{{ $destination->is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </form>
                            </td>

                            <!-- Order -->
                            <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-600">
                                {{ $destination->sort_order }}
                            </td>

                            <!-- Actions -->
                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.destinations.show', $destination) }}" 
                                       class="p-1.5 text-slate-500 hover:text-navy hover:bg-slate-100 rounded-lg transition-colors" 
                                       title="View Details">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>

                                    <a href="{{ route('admin.destinations.edit', $destination) }}" 
                                       class="p-1.5 text-slate-500 hover:text-accent-hover hover:bg-amber-50 rounded-lg transition-colors" 
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.destinations.destroy', $destination) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete destination \'{{ $destination->name }}\'?');" 
                                          class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors" 
                                                title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-base font-bold text-slate-600">No destinations found</p>
                                <p class="text-xs text-slate-400 mt-1">Try changing your search filters or click "Add Destination" to create one.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($destinations->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $destinations->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
