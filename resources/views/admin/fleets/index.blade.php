@extends('layouts.admin')

@section('title', 'Aircraft Fleet Management')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Aircraft Fleet</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $fleets->total() }} Aircraft
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage Boeing 737 passenger aircraft and dedicated freighters in the Naoero Airlines fleet.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.fleets.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Aircraft</span>
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.fleets.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search aircraft type, registration..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="category" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Categories</option>
                    <option value="passenger" {{ request('category') === 'passenger' ? 'selected' : '' }}>Passenger Aircraft</option>
                    <option value="freight" {{ request('category') === 'freight' ? 'selected' : '' }}>Dedicated Freighter</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'category']))
                    <a href="{{ route('admin.fleets.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Fleet Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($fleets as $fleet)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col justify-between">
                <div>
                    <!-- Aircraft Image Preview -->
                    <div class="relative h-48 bg-slate-900 overflow-hidden group">
                        <img src="{{ $fleet->image_url }}" 
                             alt="{{ $fleet->type }}" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             onerror="this.src='https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=800&q=80'">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-transparent to-transparent"></div>
                        
                        <div class="absolute top-3 left-3 flex gap-2">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold uppercase tracking-wider {{ $fleet->category === 'freight' ? 'bg-amber-500 text-white' : 'bg-navy text-white' }}">
                                {{ ucfirst($fleet->category) }}
                            </span>
                        </div>

                        <div class="absolute top-3 right-3">
                            <form method="POST" action="{{ route('admin.fleets.toggle-active', $fleet) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="px-2.5 py-1 rounded-full text-xs font-bold transition-all {{ $fleet->is_active ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }}"
                                        title="Click to toggle status">
                                    {{ $fleet->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </div>

                        <div class="absolute bottom-3 left-3 right-3 text-white">
                            <p class="text-xs font-mono font-bold text-accent tracking-wider">{{ $fleet->registration }}</p>
                            <h3 class="text-lg font-extrabold leading-tight">{{ $fleet->type }}</h3>
                        </div>
                    </div>

                    <!-- Specs -->
                    <div class="p-5 space-y-3">
                        <div class="grid grid-cols-2 gap-2 text-xs py-2 px-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <span class="text-slate-400 block font-semibold">Capacity</span>
                                <span class="font-bold text-navy">{{ $fleet->seating ?: 'N/A' }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-semibold">Configuration</span>
                                <span class="font-bold text-navy truncate block">{{ $fleet->configuration ?: 'Standard' }}</span>
                            </div>
                        </div>

                        @if($fleet->caption)
                            <p class="text-xs text-slate-600 italic">"{{ $fleet->caption }}"</p>
                        @endif

                        @if($fleet->description)
                            <p class="text-xs text-slate-500 line-clamp-3">{{ $fleet->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-mono text-slate-400">Order: #{{ $fleet->sort_order }}</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.fleets.edit', $fleet) }}" 
                           class="px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-navy font-bold text-xs hover:border-accent hover:text-accent-hover transition-colors shadow-sm">
                            Edit Specs
                        </a>
                        <form method="POST" action="{{ route('admin.fleets.destroy', $fleet) }}" 
                              onsubmit="return confirm('Are you sure you want to delete aircraft {{ $fleet->registration }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors"
                                    title="Delete Aircraft">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full naoero-card p-12 text-center text-slate-400 bg-white border border-slate-200">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No aircraft found</p>
                <p class="text-xs text-slate-400 mt-1">Add your fleet aircraft to display them on the fleet information page.</p>
            </div>
        @endforelse
    </div>

    @if($fleets->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $fleets->links() }}
        </div>
    @endif

</div>
@endsection
