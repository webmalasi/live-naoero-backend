@extends('layouts.admin')

@section('title', 'Header Notice Bar & Flight Alerts')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    createModalOpen: false,
    deleteModalOpen: false,
    deleteNoticeId: null,
    deleteNoticeTitle: '',
    previewNotice: 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions',
    previewBadge: 'Update and News',
    previewType: 'warning',
    durationPreset: '24',
    customHours: 24,
    setPreset(hours) {
        this.durationPreset = hours;
        this.customHours = hours === 'custom' ? 12 : (hours === 'permanent' ? 0 : hours);
    }
}">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Notice Bar & Flight Announcements</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider {{ $masterEnabled ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-rose-100 text-rose-800 border border-rose-200' }}">
                    {{ $masterEnabled ? '● System Live' : '○ System Disabled' }}
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage header news ticker, emergency weather alerts, flight schedule notices, and validity hours.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <!-- Master Toggle Button -->
            <form method="POST" action="{{ route('admin.notices.toggle-master') }}">
                @csrf
                <button type="submit" 
                        class="px-4 py-2.5 rounded-xl font-bold text-xs flex items-center gap-2 border transition-all {{ $masterEnabled ? 'bg-white border-rose-200 text-rose-700 hover:bg-rose-50 shadow-sm' : 'bg-emerald-600 border-emerald-700 text-white hover:bg-emerald-700 shadow-md shadow-emerald-600/20' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>{{ $masterEnabled ? 'Turn All Off (Master Switch)' : 'Turn All On (Master Switch)' }}</span>
                </button>
            </form>

            <a href="{{ route('admin.notices.create') }}" 
               class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add New Announcement</span>
            </a>
        </div>
    </div>

    <!-- Master Switch Alert Banner -->
    @if(!$masterEnabled)
        <div class="p-4 rounded-2xl bg-rose-50 border-2 border-dashed border-rose-300 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center text-rose-600 shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-rose-900">Header Notice Bar is Globally Disabled</h3>
                    <p class="text-xs text-rose-700 mt-0.5">The announcement bar is currently hidden on the frontend website for all visitors regardless of individual active notices.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.notices.toggle-master') }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-all whitespace-nowrap">
                    Enable Global Notice Bar
                </button>
            </form>
        </div>
    @endif

    <!-- Live Website Simulation / Ticker Preview -->
    <div class="naoero-card p-5 bg-gradient-to-br from-slate-900 via-navy to-slate-950 text-white overflow-hidden relative">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-accent animate-ping"></span>
                <span class="text-xs font-bold text-accent-soft uppercase tracking-wider">Frontend Website Live Simulation</span>
            </div>
            <span class="text-[11px] font-mono text-white/60">http://localhost:5173/naoero-airlines</span>
        </div>

        <!-- Simulated Top Bar matching Frontend NoticeBar -->
        <div class="rounded-xl overflow-hidden shadow-inner border border-white/15">
            <div class="w-full bg-[#FFC631] py-2.5 px-4 text-[#012169] flex items-center gap-3">
                <!-- Alert Icon -->
                <svg class="w-5 h-5 shrink-0 fill-[#012169] text-[#FFC631]" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                </svg>

                <div class="flex-1 overflow-hidden font-medium text-xs sm:text-sm">
                    @php
                        $primaryLiveNotice = $notices->first(fn($n) => $n->isActiveNow());
                    @endphp
                    @if($masterEnabled && $primaryLiveNotice)
                        <div class="flex items-center gap-2 truncate">
                            <span class="font-extrabold uppercase tracking-wide bg-navy/10 px-2 py-0.5 rounded text-[11px] border border-navy/20">
                                {{ $primaryLiveNotice->badge_text ?: $primaryLiveNotice->title }}
                            </span>
                            <span class="font-semibold text-navy">
                                : {{ $primaryLiveNotice->message }}
                            </span>
                            @if($primaryLiveNotice->link)
                                <a href="{{ $primaryLiveNotice->link }}" class="text-[11px] underline font-bold text-navy hover:text-blue-900 ml-1">
                                    [Learn More &rarr;]
                                </a>
                            @endif
                        </div>
                    @elseif(!$masterEnabled)
                        <span class="italic text-navy/70 text-xs">[ Notice bar is turned OFF by master switch ]</span>
                    @else
                        <span class="italic text-navy/70 text-xs">[ No active announcements right now. Activate an update below to display ]</span>
                    @endif
                </div>

                @if($masterEnabled && $activeCount > 1)
                    <span class="text-[10px] font-bold bg-navy text-accent px-2 py-0.5 rounded-full whitespace-nowrap">
                        +{{ $activeCount - 1 }} More in Rotation
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- Stats KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
        <a href="{{ route('admin.notices.index') }}" 
           class="naoero-card p-4 bg-white border {{ $status === 'all' ? 'border-navy ring-2 ring-navy/10' : 'border-slate-200' }} hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total</span>
                <span class="text-xs font-extrabold text-navy bg-navy/5 px-2 py-0.5 rounded-md">{{ $totalCount }}</span>
            </div>
            <p class="text-2xl font-extrabold text-navy mt-2">{{ $totalCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">All created updates</p>
        </a>

        <a href="{{ route('admin.notices.index', ['status' => 'active']) }}" 
           class="naoero-card p-4 bg-white border {{ $status === 'active' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-slate-200' }} hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider">Live & Active</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            </div>
            <p class="text-2xl font-extrabold text-emerald-600 mt-2">{{ $activeCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Currently displaying</p>
        </a>

        <a href="{{ route('admin.notices.index', ['status' => 'scheduled']) }}" 
           class="naoero-card p-4 bg-white border {{ $status === 'scheduled' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-slate-200' }} hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Scheduled</span>
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            </div>
            <p class="text-2xl font-extrabold text-blue-600 mt-2">{{ $scheduledCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Upcoming future time</p>
        </a>

        <a href="{{ route('admin.notices.index', ['status' => 'expired']) }}" 
           class="naoero-card p-4 bg-white border {{ $status === 'expired' ? 'border-rose-500 ring-2 ring-rose-500/10' : 'border-slate-200' }} hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-rose-700 uppercase tracking-wider">Expired</span>
                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
            </div>
            <p class="text-2xl font-extrabold text-rose-600 mt-2">{{ $expiredCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Passed duration hours</p>
        </a>

        <a href="{{ route('admin.notices.index', ['status' => 'inactive']) }}" 
           class="naoero-card p-4 bg-white border {{ $status === 'inactive' ? 'border-slate-500 ring-2 ring-slate-500/10' : 'border-slate-200' }} hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-600 uppercase tracking-wider">Deactivated</span>
                <span class="w-2 h-2 rounded-full bg-slate-300"></span>
            </div>
            <p class="text-2xl font-extrabold text-slate-700 mt-2">{{ $inactiveCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Manually toggled off</p>
        </a>
    </div>

    <!-- Filter Bar & Search -->
    <div class="naoero-card p-4 bg-white border border-slate-200 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
            <a href="{{ route('admin.notices.index', ['search' => request('search')]) }}" 
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $status === 'all' ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                All ({{ $totalCount }})
            </a>
            <a href="{{ route('admin.notices.index', ['status' => 'active', 'search' => request('search')]) }}" 
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $status === 'active' ? 'bg-emerald-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Active ({{ $activeCount }})
            </a>
            <a href="{{ route('admin.notices.index', ['status' => 'scheduled', 'search' => request('search')]) }}" 
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $status === 'scheduled' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Scheduled ({{ $scheduledCount }})
            </a>
            <a href="{{ route('admin.notices.index', ['status' => 'expired', 'search' => request('search')]) }}" 
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $status === 'expired' ? 'bg-rose-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Expired ({{ $expiredCount }})
            </a>
            <a href="{{ route('admin.notices.index', ['status' => 'inactive', 'search' => request('search')]) }}" 
               class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ $status === 'inactive' ? 'bg-slate-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                Deactivated ({{ $inactiveCount }})
            </a>
        </div>

        <!-- Search Form & Bulk Actions -->
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('admin.notices.index') }}" class="relative w-full sm:w-64">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search announcements..." 
                       class="w-full pl-9 pr-3.5 py-1.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </form>

            <form method="POST" action="{{ route('admin.notices.bulk') }}" class="shrink-0">
                @csrf
                <input type="hidden" name="action" value="activate_all">
                <button type="submit" title="Turn ON all notices" class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-bold border border-emerald-200 transition-all">
                    Enable All
                </button>
            </form>

            <form method="POST" action="{{ route('admin.notices.bulk') }}" class="shrink-0">
                @csrf
                <input type="hidden" name="action" value="deactivate_all">
                <button type="submit" title="Turn OFF all notices" class="px-3 py-1.5 rounded-lg bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-bold border border-rose-200 transition-all">
                    Disable All
                </button>
            </form>
        </div>
    </div>

    <!-- Notices Listing -->
    <div class="space-y-4">
        @forelse($notices as $notice)
            <div class="naoero-card p-5 bg-white border border-slate-200 hover:border-slate-300 transition-all space-y-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-3 border-b border-slate-100">
                    <!-- Title & Badges -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="text-xs font-black text-navy px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200">
                            #{{ $notice->id }}
                        </span>

                        <span class="px-3 py-1 rounded-lg text-xs font-extrabold uppercase tracking-wide border {{ $notice->type_badge_class }}">
                            {{ $notice->badge_text ?: $notice->title }}
                        </span>

                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $notice->status_badge_class }} flex items-center gap-1.5">
                            @if($notice->status === 'active')
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            @elseif($notice->status === 'expired')
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                            @elseif($notice->status === 'scheduled')
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                            @else
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                            @endif
                            <span>{{ $notice->status_text }}</span>
                        </span>

                        @if($notice->priority > 0)
                            <span class="text-[11px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200">
                                Priority {{ $notice->priority }}
                            </span>
                        @endif
                    </div>

                    <!-- Actions Bar -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <!-- Quick Active/Deactive Toggle -->
                        <form method="POST" action="{{ route('admin.notices.toggle-active', $notice) }}" class="inline-flex items-center">
                            @csrf
                            @method('PATCH')
                            <label class="relative inline-flex items-center cursor-pointer" title="{{ $notice->is_active ? 'Click to Deactivate' : 'Click to Activate' }}">
                                <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" {{ $notice->is_active ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                <span class="ml-2 text-xs font-bold text-slate-700">
                                    {{ $notice->is_active ? 'Active' : 'Off' }}
                                </span>
                            </label>
                        </form>

                        <!-- Quick Extend Hours Dropdown -->
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" 
                                    @click.away="open = false" 
                                    class="px-2.5 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-slate-700 font-bold text-xs flex items-center gap-1.5 transition-all">
                                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span>Extend Time</span>
                                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div x-show="open" x-cloak class="absolute right-0 mt-1 w-44 bg-white border border-slate-200 rounded-xl shadow-xl z-20 py-1 text-xs">
                                <p class="px-3 py-1 font-bold text-slate-400 text-[10px] uppercase tracking-wider">Add Validity Hours</p>
                                <form method="POST" action="{{ route('admin.notices.extend-time', $notice) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="hours" value="6">
                                    <button type="submit" class="w-full text-left px-3 py-1.5 hover:bg-slate-100 text-slate-700 font-semibold flex items-center justify-between">
                                        <span>+6 Hours</span>
                                        <span class="text-slate-400 text-[10px]">Short alert</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.notices.extend-time', $notice) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="hours" value="24">
                                    <button type="submit" class="w-full text-left px-3 py-1.5 hover:bg-slate-100 text-slate-700 font-semibold flex items-center justify-between">
                                        <span>+24 Hours (1 Day)</span>
                                        <span class="text-emerald-600 text-[10px]">Standard</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.notices.extend-time', $notice) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="hours" value="48">
                                    <button type="submit" class="w-full text-left px-3 py-1.5 hover:bg-slate-100 text-slate-700 font-semibold flex items-center justify-between">
                                        <span>+48 Hours (2 Days)</span>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.notices.extend-time', $notice) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="hours" value="72">
                                    <button type="submit" class="w-full text-left px-3 py-1.5 hover:bg-slate-100 text-slate-700 font-semibold flex items-center justify-between">
                                        <span>+72 Hours (3 Days)</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Edit Button -->
                        <a href="{{ route('admin.notices.edit', $notice) }}" 
                           class="px-3 py-1.5 rounded-lg bg-navy hover:bg-navy-900 text-white text-xs font-bold transition-all shadow-sm">
                            Edit
                        </a>

                        <!-- Delete Form -->
                        <form method="POST" action="{{ route('admin.notices.destroy', $notice) }}" onsubmit="return confirm('Are you sure you want to delete this announcement notice?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Delete Notice">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="space-y-2">
                    <p class="text-sm font-bold text-navy leading-relaxed">
                        {{ $notice->message }}
                    </p>

                    @if($notice->link)
                        <div class="flex items-center gap-1.5 text-xs text-ink-muted">
                            <span class="font-bold text-slate-600">Action Link:</span>
                            <a href="{{ $notice->link }}" target="_blank" class="text-blue-600 hover:underline font-mono text-[11px]">
                                {{ $notice->link }} &nearr;
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Duration & Timing Meta Bar -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 text-xs bg-slate-50 p-3 rounded-xl border border-slate-100">
                    <!-- Configured Duration -->
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Duration</span>
                            <span class="font-extrabold text-navy">
                                @if($notice->duration_hours && $notice->duration_hours > 0)
                                    {{ $notice->duration_hours }} Hours ({{ round($notice->duration_hours / 24, 1) }} days)
                                @else
                                    <span class="text-slate-600">Permanent / No Limit</span>
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Live Validity Countdown -->
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 {{ $notice->status === 'active' ? 'text-emerald-500' : ($notice->status === 'expired' ? 'text-rose-500' : 'text-blue-500') }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Validity & Countdown</span>
                            <span class="font-extrabold {{ $notice->status === 'active' ? 'text-emerald-700' : ($notice->status === 'expired' ? 'text-rose-600' : 'text-slate-700') }}">
                                {{ $notice->remaining_time_text }}
                            </span>
                        </div>
                    </div>

                    <!-- Schedule Window -->
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block">Start & Expiry Schedule</span>
                            <span class="text-[11px] font-mono text-slate-700">
                                {{ $notice->starts_at ? $notice->starts_at->format('M d H:i') : 'Immediate' }}
                                &rarr;
                                {{ $notice->expires_at ? $notice->expires_at->format('M d H:i') : 'Never' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="naoero-card p-12 bg-white border border-slate-200 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-navy">No announcements found</h3>
                    <p class="text-xs text-ink-muted mt-1 max-w-md mx-auto">Create a flight alert, weather notice, or general news ticker announcement to display on the website.</p>
                </div>
                <a href="{{ route('admin.notices.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:bg-accent-hover transition-all">
                    <span>Create First Announcement</span>
                </a>
            </div>
        @endforelse

        <!-- Pagination -->
        @if($notices->hasPages())
            <div class="pt-4">
                {{ $notices->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
