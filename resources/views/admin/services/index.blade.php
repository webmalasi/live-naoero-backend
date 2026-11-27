@extends('layouts.admin')

@section('title', 'Airline Services Management')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Airline Services & Offerings</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total'] }} Services
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage core flight solutions displayed on the Services page (Passenger, Cargo & Freight, Private Charter, ACMI Wet Lease).</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.services.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add New Service</span>
            </a>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Services</span>
                <span class="text-2xl font-extrabold text-navy mt-1 block">{{ $stats['total'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-navy/10 flex items-center justify-center text-navy font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-emerald-600 uppercase tracking-wider block">Active Live</span>
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
                <span class="text-xs font-bold text-blue-600 uppercase tracking-wider block">Passenger Services</span>
                <span class="text-2xl font-extrabold text-blue-700 mt-1 block">{{ $stats['passenger'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center text-blue-600 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
            </div>
        </div>

        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-amber-600 uppercase tracking-wider block">Cargo & Logistics</span>
                <span class="text-2xl font-extrabold text-amber-700 mt-1 block">{{ $stats['cargo'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Search and Filter Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.services.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
            <div class="sm:col-span-8 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search services by title, description..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div class="sm:col-span-3">
                <select name="type" class="w-full px-3 py-2 text-xs font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
                    <option value="">All Categories</option>
                    <option value="passenger" {{ request('type') === 'passenger' ? 'selected' : '' }}>Passenger</option>
                    <option value="cargo" {{ request('type') === 'cargo' ? 'selected' : '' }}>Cargo & Freight</option>
                    <option value="charter" {{ request('type') === 'charter' ? 'selected' : '' }}>Private Charter</option>
                    <option value="acmi" {{ request('type') === 'acmi' ? 'selected' : '' }}>ACMI Lease</option>
                    <option value="special" {{ request('type') === 'special' ? 'selected' : '' }}>Special Services</option>
                </select>
            </div>

            <div class="sm:col-span-1 flex items-center gap-1">
                <button type="submit" class="w-full py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-navy/90 transition-all flex items-center justify-center">
                    Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Services Table -->
    <div class="naoero-card bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-700 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4">Service</th>
                        <th class="px-6 py-4">Category</th>
                        <th class="px-6 py-4">Key Features</th>
                        <th class="px-6 py-4">Action / Link</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Order</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($services as $service)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <div class="w-16 h-12 rounded-lg bg-slate-100 border border-slate-200 overflow-hidden shrink-0 flex items-center justify-center">
                                        @if($service->image_url)
                                            <img src="{{ $service->image_url }}" alt="{{ $service->title }}" class="w-full h-full object-cover">
                                        @else
                                            <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-navy text-sm">{{ $service->title }}</h3>
                                        <span class="text-xs text-slate-400 font-mono">/services/{{ $service->slug }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold uppercase tracking-wider
                                    {{ $service->type === 'passenger' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $service->type === 'cargo' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $service->type === 'charter' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $service->type === 'acmi' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $service->type === 'special' ? 'bg-slate-100 text-slate-800' : '' }}
                                ">
                                    {{ ucfirst($service->type) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 max-w-xs">
                                @if(!empty($service->features) && count($service->features) > 0)
                                    <ul class="text-xs text-slate-500 list-disc list-inside space-y-0.5">
                                        @foreach(array_slice($service->features, 0, 2) as $feature)
                                            <li class="truncate">{{ $feature }}</li>
                                        @endforeach
                                        @if(count($service->features) > 2)
                                            <li class="text-slate-400 italic">+{{ count($service->features) - 2 }} more</li>
                                        @endif
                                    </ul>
                                @else
                                    <span class="text-xs text-slate-400 italic">No features listed</span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if($service->cta_text)
                                    <span class="text-xs font-semibold text-navy bg-slate-100 px-2.5 py-1 rounded border border-slate-200 inline-block">
                                        {{ $service->cta_text }} &rarr; <span class="text-slate-400 font-mono text-[10px]">{{ $service->cta_link }}</span>
                                    </span>
                                @else
                                    <span class="text-xs text-slate-400 italic">None</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-center">
                                <form method="POST" action="{{ route('admin.services.toggle-active', $service) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="px-2.5 py-1 rounded-full text-xs font-bold transition-all cursor-pointer {{ $service->is_active ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-rose-100 text-rose-800 hover:bg-rose-200' }}">
                                        {{ $service->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-6 py-4 text-center font-bold text-xs text-slate-500">
                                {{ $service->sort_order }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.services.edit', $service) }}" 
                                       class="p-1.5 text-navy hover:bg-navy/10 rounded-lg transition-all" 
                                       title="Edit Service">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                    </a>
                                    <form method="POST" action="{{ route('admin.services.destroy', $service) }}" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-all" 
                                                title="Delete Service">
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
                            <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-sm font-semibold text-slate-600">No airline services found.</p>
                                <p class="text-xs text-slate-400 mt-1">Get started by creating your first service offering.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($services->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $services->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
