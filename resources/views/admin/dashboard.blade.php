@extends('layouts.admin')

@section('title', 'Executive Flight Operations Dashboard')

@section('content')
<div class="space-y-8">

    @include('admin.inventory._dashboard_alert')

    <!-- WELCOME BANNER -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#012169] via-[#0A1F44] to-[#06132D] text-white p-6 sm:p-8 shadow-xl border border-white/10">
        <!-- Background decorative vectors -->
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-accent/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute top-0 right-1/4 w-40 h-40 bg-blue-400/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white">
                    Welcome, {{ auth()->user()->name }}! 👋
                </h1>
                <p class="text-sm text-white/75 leading-relaxed">
                    Welcome to the Naoero Airlines executive administrative console.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @canroute('admin.users.create')
                <a href="{{ route('admin.users.create') }}" 
                   class="px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 hover:scale-105 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>+ Add User</span>
                </a>
                @endcanroute
            </div>
        </div>
    </div>

    <!-- KEY METRIC CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        
        @canroute('admin.users.index')
        <!-- Card 1: Users Metric -->
        <div class="naoero-card p-6 border-l-4 border-l-navy flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Total System Users</span>
                <div class="w-10 h-10 rounded-xl bg-navy-50 text-navy flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-navy">{{ $totalUsers }}</span>
                    <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">{{ $activeUsers }} Active</span>
                </div>
                <p class="text-xs text-gray-500 mt-2 flex items-center justify-between">
                    <span>Inactive accounts:</span>
                    <strong class="text-gray-700 font-mono">{{ $inactiveUsers }}</strong>
                </p>
            </div>
        </div>
        @endcanroute

        <!-- Card 2: Pacific Fleet Telemetry -->
        <div class="naoero-card p-6 border-l-4 border-l-blue-600 flex flex-col justify-between">
            <div class="flex items-center justify-between mb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">Fleet & Routes</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-extrabold text-navy">{{ $aviationStats['active_routes'] }}</span>
                    <span class="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">Pacific Routes</span>
                </div>
                <p class="text-xs text-gray-500 mt-2 flex items-center justify-between">
                    <span>On-Time Performance:</span>
                    <strong class="text-emerald-600 font-bold font-mono">{{ $aviationStats['on_time_performance'] }}</strong>
                </p>
            </div>
        </div>

    </div>

    <!-- CMS & FLIGHT OPERATIONS QUICK ACCESS -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-extrabold text-navy">Flight Network & Content Hub</h2>
                <p class="text-xs text-ink-muted">Quick shortcuts to manage public website modules and flight operations</p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <!-- Destinations -->
            @canroute('admin.destinations.index')
            <a href="{{ route('admin.destinations.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Destinations</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_destinations'] }} Places</span>
            </a>
            @endcanroute

            <!-- Fleet -->
            @canroute('admin.fleets.index')
            <a href="{{ route('admin.fleets.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Fleet Aircraft</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['fleet_aircraft'] }} Boeing Jets</span>
            </a>
            @endcanroute

            <!-- Timetables -->
            @canroute('admin.schedules.index')
            <a href="{{ route('admin.schedules.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Timetables</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_schedules'] }} Schedules</span>
            </a>
            @endcanroute

            <!-- Special Offers -->
            @canroute('admin.specials.index')
            <a href="{{ route('admin.specials.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-amber-50 text-amber-600 group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Flight Specials</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['active_specials'] }} Deals</span>
            </a>
            @endcanroute

            <!-- Media Gallery -->
            @canroute('admin.gallery.index')
            <a href="{{ route('admin.gallery.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Media Gallery</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_gallery'] }} Photos</span>
            </a>
            @endcanroute

            <!-- Agency Contacts -->
            @canroute('admin.agencies.index')
            <a href="{{ route('admin.agencies.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Agency Directory</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_agencies'] }} Offices</span>
            </a>
            @endcanroute

            <!-- News & Blog -->
            @canroute('admin.posts.index')
            <a href="{{ route('admin.posts.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">News & Blog</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_posts'] }} Articles</span>
            </a>
            @endcanroute

            <!-- Customer Inquiries -->
            @canroute('admin.inquiries.index')
            <a href="{{ route('admin.inquiries.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center relative">
                @if($aviationStats['new_inquiries'] > 0)
                    <span class="absolute top-2 right-2 w-2.5 h-2.5 bg-rose-500 rounded-full animate-ping"></span>
                @endif
                <div class="w-10 h-10 mx-auto rounded-xl bg-rose-50 text-rose-600 group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Inquiries Inbox</span>
                <span class="text-[11px] font-bold {{ $aviationStats['new_inquiries'] > 0 ? 'text-rose-600' : 'text-slate-400' }}">
                    {{ $aviationStats['new_inquiries'] }} New
                </span>
            </a>
            @endcanroute

            <!-- Newsletter Leads -->
            @canroute('admin.newsletter.index')
            <a href="{{ route('admin.newsletter.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Newsletter Leads</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['total_subscribers'] }} Subscribers</span>
            </a>
            @endcanroute

            <!-- Careers -->
            @canroute('admin.careers.index')
            <a href="{{ route('admin.careers.index') }}" class="naoero-card p-4 bg-white border border-slate-200 hover:border-accent group transition-all text-center">
                <div class="w-10 h-10 mx-auto rounded-xl bg-navy-50 text-navy group-hover:bg-accent group-hover:text-navy flex items-center justify-center transition-colors mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <span class="text-xs font-bold text-navy block">Careers</span>
                <span class="text-[11px] text-slate-400 font-bold">{{ $aviationStats['open_careers'] }} Roles</span>
            </a>
            @endcanroute
        </div>
    </div>

    <!-- WEBSITE & APP PAGES: the client edits these; the website and the app update within seconds -->
    @canroute('admin.content-pages.index')
    <div class="naoero-card p-6 bg-white border border-slate-200">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
            <div>
                <h2 class="text-lg font-extrabold text-navy">Website & App Pages</h2>
                <p class="text-xs text-ink-muted">Edit the text, pictures and lists of Charter, Freight, Baggage and every other page. A save shows on the website and in the app within seconds.</p>
            </div>
            <div class="flex items-center gap-2">
                @canroute('admin.content-pages.create')
                <a href="{{ route('admin.content-pages.create') }}" class="px-3.5 py-2 text-xs font-bold text-navy bg-slate-100 hover:bg-slate-200 rounded-xl">+ New page</a>
                @endcanroute
                <a href="{{ route('admin.content-pages.index') }}" class="px-3.5 py-2 text-xs font-bold text-navy bg-accent hover:bg-accent-hover rounded-xl shadow-sm">Manage all pages &rarr;</a>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="rounded-xl bg-navy-50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Pages</div>
                <div class="text-2xl font-extrabold text-navy">{{ $pageStats['total'] }}</div>
            </div>
            <div class="rounded-xl bg-emerald-50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Live</div>
                <div class="text-2xl font-extrabold text-emerald-700">{{ $pageStats['published'] }}</div>
            </div>
            <div class="rounded-xl {{ $pageStats['hidden'] ? 'bg-amber-50' : 'bg-slate-50' }} p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Hidden</div>
                <div class="text-2xl font-extrabold {{ $pageStats['hidden'] ? 'text-amber-700' : 'text-slate-400' }}">{{ $pageStats['hidden'] }}</div>
            </div>
            <div class="rounded-xl bg-slate-50 p-3.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Travel Info</div>
                <div class="text-2xl font-extrabold text-navy">{{ $pageStats['by_group']['travel-info'] ?? 0 }}</div>
            </div>
        </div>

        <div class="text-xs font-extrabold uppercase tracking-wider text-slate-500 mb-2">Recently edited</div>
        <div class="divide-y divide-slate-100 border border-slate-200 rounded-xl overflow-hidden">
            @forelse($recentPages as $recent)
                <div class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-slate-50/60">
                    <div class="min-w-0">
                        <a href="{{ route('admin.content-pages.edit', $recent) }}" class="font-bold text-navy hover:underline">{{ $recent->title }}</a>
                        <div class="text-[11px] text-slate-400">
                            {{ $recent->sections_count }} sections &middot; version {{ $recent->version }} &middot; {{ $recent->updated_at?->diffForHumans() }}@if($recent->editor) by {{ $recent->editor->name }}@endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $recent->is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $recent->is_published ? 'Live' : 'Hidden' }}</span>
                        @canroute('admin.content-pages.edit')
                        <a href="{{ route('admin.content-pages.edit', $recent) }}" class="px-3 py-1.5 text-xs font-bold text-navy bg-slate-100 hover:bg-slate-200 rounded-lg">Edit</a>
                        @endcanroute
                    </div>
                </div>
            @empty
                <div class="px-4 py-6 text-sm text-slate-500 text-center">No pages yet. Run <span class="font-mono">php artisan content:pages</span> to add them.</div>
            @endforelse
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-1 text-[11px] text-slate-500">
            <span>Team, check-in times, dangerous goods, terms, specials and schedules keep their own screens in the menu.</span>
            <a href="{{ url('/api/docs') }}" target="_blank" class="font-bold text-navy hover:underline">API reference &nearr;</a>
        </div>
    </div>
    @endcanroute

    <!-- CHARTS & VISUAL ANALYTICS SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- Left: Activity & User Growth Line Chart -->
        <div class="lg:col-span-8 naoero-card p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-lg font-extrabold text-navy">Flight Dispatch & System Activity</h2>
                    <p class="text-xs text-gray-500">Weekly audit logs and administrative operations frequency</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs text-navy font-bold px-2.5 py-1 rounded-lg bg-navy-50 border border-navy-100">
                        <span class="w-2 h-2 rounded-full bg-navy"></span>
                        Admin Operations
                    </span>
                    <span class="inline-flex items-center gap-1.5 text-xs text-accent-hover font-bold px-2.5 py-1 rounded-lg bg-amber-50 border border-amber-200">
                        <span class="w-2 h-2 rounded-full bg-accent"></span>
                        Flight Logins
                    </span>
                </div>
            </div>

            <div class="h-64 sm:h-72">
                <canvas id="activityChart"></canvas>
            </div>
        </div>

        <!-- Right: Roles Distribution Donut Chart -->
        @canroute('admin.roles.index')
        <div class="lg:col-span-4 naoero-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-extrabold text-navy">Role Hierarchy</h2>
                    <a href="{{ route('admin.roles.index') }}" class="text-xs font-bold text-accent-hover hover:underline">Manage</a>
                </div>
                <p class="text-xs text-gray-500 mb-4">Distribution of assigned system roles across users</p>
            </div>

            <div class="h-56 relative flex items-center justify-center">
                <canvas id="roleDonutChart"></canvas>
            </div>

            <div class="pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                <span>Configured Roles: <strong class="text-navy">{{ $totalRoles }}</strong></span>
                <span>Permissions: <strong class="text-navy">{{ $totalPermissions }}</strong></span>
            </div>
        </div>
        @endcanroute

    </div>

    <!-- LIVE AUDIT TRAIL -->
    @canroute('admin.activity-logs.index')
    <div>
        <!-- Live Audit Activity Feed -->
        <div class="naoero-card p-6 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-lg font-extrabold text-navy">Audit Trail Stream</h2>
                        <p class="text-xs text-gray-500">Real-time administrator activity logs</p>
                    </div>
                    <a href="{{ route('admin.activity-logs.index') }}" class="text-xs font-bold text-navy hover:text-accent-hover flex items-center gap-1">
                        <span>Full log</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div class="space-y-4">
                    @forelse($recentActivities as $log)
                        <div class="flex items-start gap-3 text-xs">
                            <div class="w-7 h-7 rounded-xl bg-navy-50 text-navy flex items-center justify-center shrink-0 mt-0.5 font-bold">
                                @if($log->action === 'login' || $log->action === 'logout')
                                    🔐
                                @elseif($log->action === 'create')
                                    ✨
                                @elseif($log->action === 'delete')
                                    🗑️
                                @else
                                    ⚡
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-gray-900 font-semibold leading-snug truncate">{{ $log->description }}</p>
                                <div class="flex items-center gap-2 mt-1 text-[11px] text-gray-400">
                                    <span class="font-bold text-navy">{{ $log->user?->name ?? 'System' }}</span>
                                    <span>&bull;</span>
                                    <span class="font-mono">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-center py-6 text-gray-400 text-xs">No activity logged yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="mt-6 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> Live Telemetry</span>
                <span class="font-mono text-navy font-bold">IP: {{ request()->ip() }}</span>
            </div>
        </div>

    </div>
    @endcanroute

</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Line Chart
        const ctxActivity = document.getElementById('activityChart').getContext('2d');
        new Chart(ctxActivity, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Admin Operations',
                        data: [12, 19, 15, 25, 22, 30, 28],
                        borderColor: '#012169',
                        backgroundColor: 'rgba(1, 33, 105, 0.08)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#012169',
                        pointRadius: 4,
                    },
                    {
                        label: 'Flight Logins',
                        data: [8, 14, 11, 18, 17, 24, 21],
                        borderColor: '#FFC631',
                        backgroundColor: 'rgba(255, 198, 49, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#FFC631',
                        pointRadius: 4,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#F1F5F9' }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });

        // Donut Chart
        const donutCanvas = document.getElementById('roleDonutChart');
        if (donutCanvas) new Chart(donutCanvas.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($roleLabels) !!},
                datasets: [{
                    data: {!! json_encode($roleUserCounts) !!},
                    backgroundColor: ['#012169', '#FFC631', '#2563EB', '#10B981', '#F59E0B'],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 12, font: { family: 'Manrope', size: 11 } }
                    }
                }
            }
        });
    });
</script>
@endpush
@endsection
