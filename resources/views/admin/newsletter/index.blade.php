@extends('layouts.admin')

@section('title', 'Newsletter Subscribers')

@section('content')
<div class="space-y-6">

    <!-- Page Header & Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Newsletter Subscribers</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $subscribers->total() }} Subscribers
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage marketing newsletter leads, promotional email opt-ins, and export audience lists.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.newsletter.export') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-navy hover:bg-navy-700 text-white font-bold text-sm shadow-md transition-all">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export CSV</span>
            </a>
        </div>
    </div>

    <!-- Stats Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-navy/10 text-navy flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Total Subscriptions</p>
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
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Active Subscribers</p>
                <p class="text-2xl font-extrabold text-emerald-600 mt-0.5">{{ $stats['active'] }}</p>
            </div>
        </div>

        <div class="naoero-card p-5 flex items-center gap-4 bg-white border border-slate-200">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider">Unsubscribed</p>
                <p class="text-2xl font-extrabold text-rose-600 mt-0.5">{{ $stats['unsubscribed'] }}</p>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200">
        <form method="GET" action="{{ route('admin.newsletter.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            
            <div class="lg:col-span-2 relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search subscriber email address..." 
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="status" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <option value="">All Subscribers</option>
                    <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active Only</option>
                    <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Unsubscribed Only</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                    Filter
                </button>
                @if(request()->hasAny(['search', 'status']))
                    <a href="{{ route('admin.newsletter.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 text-slate-600 text-sm font-semibold hover:bg-slate-200 transition-colors" title="Clear Filters">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Subscribers Table -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase font-extrabold text-slate-500 border-b border-slate-200 tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Email Address</th>
                        <th class="px-5 py-3.5">Acquisition Source</th>
                        <th class="px-5 py-3.5">Subscribed Date</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($subscribers as $sub)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-5 py-3.5 font-bold text-navy">
                                {{ $sub->email }}
                            </td>

                            <td class="px-5 py-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-mono bg-slate-100 text-slate-700 border border-slate-200">
                                    {{ $sub->source ?? 'website_footer' }}
                                </span>
                            </td>

                            <td class="px-5 py-3.5 text-xs text-slate-500">
                                {{ $sub->subscribed_at ? $sub->subscribed_at->format('M d, Y h:i A') : $sub->created_at->format('M d, Y') }}
                            </td>

                            <td class="px-5 py-3.5 text-center">
                                <form method="POST" action="{{ route('admin.newsletter.toggle-status', $sub) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold transition-all {{ $sub->is_active ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                                            title="Click to toggle status">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $sub->is_active ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                                        <span>{{ $sub->is_active ? 'Active' : 'Unsubscribed' }}</span>
                                    </button>
                                </form>
                            </td>

                            <td class="px-5 py-3.5 text-right">
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $sub) }}" 
                                      onsubmit="return confirm('Remove subscriber \'{{ $sub->email }}\'?');" 
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
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center text-slate-400">
                                <p class="text-base font-bold text-slate-600">No newsletter subscribers yet</p>
                                <p class="text-xs text-slate-400 mt-1">Visitors subscribing via the website newsletter form will be collected here.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($subscribers->hasPages())
            <div class="px-5 py-4 border-t border-slate-100">
                {{ $subscribers->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
