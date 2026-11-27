@extends('layouts.admin')

@section('title', 'Travel Agency Contacts')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Travel Agency Directory</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $agencies->total() }} Agencies
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage global passenger sales agents (PSA), ticketing offices, and booking partners.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.agencies.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Agency Contact</span>
            </a>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Total Agencies</p>
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
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Active Listings</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-0.5">{{ $stats['active'] }}</p>
            </div>
        </div>

        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-accent/20 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Countries Covered</p>
                <p class="text-2xl font-extrabold text-navy mt-0.5">{{ $stats['countries'] }}</p>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.agencies.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            
            <div class="lg:col-span-2 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search agency name, country, address..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="status" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Statuses</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive Only</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.agencies.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors" title="Clear Filters">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Agencies Table -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Country & Flag</th>
                        <th class="px-5 py-3.5">Agency / Office Name</th>
                        <th class="px-5 py-3.5">Address & Contacts</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-center">Order</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($agencies as $agency)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 font-bold text-navy">
                                    @if($agency->flag)
                                        <span class="text-lg">{{ $agency->flag }}</span>
                                    @endif
                                    <span>{{ $agency->country }}</span>
                                </span>
                            </td>

                            <td class="px-5 py-3.5">
                                <a href="{{ route('admin.agencies.edit', $agency) }}" class="font-bold text-navy hover:text-accent-hover transition-colors">
                                    {{ $agency->name }}
                                </a>
                                @if($agency->website)
                                    <div class="mt-0.5">
                                        <a href="{{ $agency->website }}" target="_blank" class="text-xs text-sky-600 hover:underline">
                                            {{ $agency->website }}
                                        </a>
                                    </div>
                                @endif
                            </td>

                            <td class="px-5 py-3.5">
                                @if($agency->address)
                                    <p class="text-xs text-slate-600 line-clamp-1 mb-1">{{ $agency->address }}</p>
                                @endif
                                <div class="flex flex-wrap gap-2 text-xs text-slate-500">
                                    @if(!empty($agency->phones))
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                            {{ implode(', ', (array) $agency->phones) }}
                                        </span>
                                    @endif
                                    @if(!empty($agency->emails))
                                        <span class="inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            {{ implode(', ', (array) $agency->emails) }}
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <form method="POST" action="{{ route('admin.agencies.toggle-active', $agency) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold transition-all {{ $agency->is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                                            title="Click to toggle status">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $agency->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        <span>{{ $agency->is_active ? 'Active' : 'Inactive' }}</span>
                                    </button>
                                </form>
                            </td>

                            <td class="px-5 py-3.5 text-center font-mono font-bold text-slate-600">
                                {{ $agency->sort_order }}
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.agencies.edit', $agency) }}" 
                                       class="p-1.5 text-slate-500 hover:text-accent-hover hover:bg-amber-50 rounded-lg transition-colors" 
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </a>

                                    <form method="POST" action="{{ route('admin.agencies.destroy', $agency) }}" 
                                          onsubmit="return confirm('Are you sure you want to delete agency \'{{ $agency->name }}\'?');" 
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
                            <td colspan="6" class="px-5 py-12 text-center text-slate-400">
                                <p class="text-base font-bold text-slate-600">No agency contacts found</p>
                                <p class="text-xs text-slate-400 mt-1">Add your PSA and ticket partner offices to display on the agency contacts page.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($agencies->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $agencies->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
