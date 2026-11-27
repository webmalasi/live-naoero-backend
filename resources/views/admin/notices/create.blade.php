@extends('layouts.admin')

@section('title', 'Create Announcement Notice')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    title: '{{ old('title', 'Flight Update and Advisory') }}',
    badgeText: '{{ old('badge_text', 'Update and News') }}',
    message: '{{ old('message', 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions.') }}',
    link: '{{ old('link', '/destinations') }}',
    type: '{{ old('type', 'warning') }}',
    isActive: {{ old('is_active', 1) ? 'true' : 'false' }},
    durationPreset: '24',
    customHours: {{ old('duration_hours', 24) }},
    startTime: '{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}',
    priority: {{ old('priority', 0) }},
    setPreset(hours) {
        this.durationPreset = hours.toString();
        this.customHours = hours === 'permanent' ? 0 : hours;
    },
    useTemplate(templateType) {
        if (templateType === 'weather') {
            this.title = 'Weather Advisory';
            this.badgeText = 'Weather Alert';
            this.message = 'Flight 201 to Nauru has been rescheduled due to adverse weather conditions.';
            this.type = 'warning';
            this.setPreset(24);
        } else if (templateType === 'schedule') {
            this.title = 'Flight Schedule Update';
            this.badgeText = 'Flight Update';
            this.message = 'Naoero Airlines Pacific route network is operating on regular schedule. Plan your next island getaway today!';
            this.type = 'info';
            this.setPreset(72);
        } else if (templateType === 'runway') {
            this.title = 'Airport Advisory';
            this.badgeText = 'Urgent Notice';
            this.message = 'Runway maintenance scheduled at Naoero International Airport. Please check latest flight departures.';
            this.type = 'alert';
            this.setPreset(48);
        }
    }
}">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.notices.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.notices.index') }}" class="hover:text-navy transition-colors">Notice Bar & Alerts</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Notice</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Create Announcement Notice</h1>
                <p class="text-xs text-ink-muted">Broadcast weather warnings, schedule alterations, and urgent passenger notices on the global website ticker.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.notices.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('notice-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Publish Notice</span>
            </button>
        </div>
    </div>

    <!-- Error Alert Box -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs shadow-sm">
            <div class="flex items-center gap-2 font-bold mb-1 text-sm text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please correct the form errors:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Live Website Simulation / Real-time Ticker Preview -->
    <div class="naoero-card p-5 bg-gradient-to-br from-slate-900 via-navy to-slate-950 text-white overflow-hidden space-y-3 shadow-md">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-accent animate-ping"></span>
                <span class="text-xs font-bold text-accent uppercase tracking-wider">Live Website Top Ticker Simulation</span>
            </div>
            <span class="text-[10px] font-mono text-white/60">Real-time Frontend Simulator</span>
        </div>

        <div class="rounded-xl overflow-hidden shadow-inner border border-white/15">
            <div class="w-full bg-[#FFC631] py-3 px-4 text-[#012169] flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0 fill-[#012169] text-[#FFC631]" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                    <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                </svg>

                <div class="flex-1 overflow-hidden font-medium text-xs sm:text-sm">
                    <div class="flex items-center gap-2 truncate">
                        <span class="font-extrabold uppercase tracking-wide bg-navy/10 px-2 py-0.5 rounded text-[11px] border border-navy/20" x-text="badgeText || title || 'Notice'">
                            Update and News
                        </span>
                        <span class="font-semibold text-navy">
                            : <span x-text="message || 'Enter your announcement message below...'"></span>
                        </span>
                    </div>
                </div>

                <span class="text-[10px] font-bold bg-navy text-accent px-2 py-0.5 rounded-full whitespace-nowrap" x-text="isActive ? 'Live' : 'Draft / Inactive'">
                    Live
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Preset Templates -->
    <div class="naoero-card p-4 bg-white border border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <span class="text-xs font-bold text-navy uppercase tracking-wider block">Quick Presets / Templates:</span>
            <span class="text-[11px] text-ink-muted">Click a template to quickly pre-fill announcement details</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="useTemplate('weather')" class="px-3 py-1.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 text-xs font-bold transition-all">
                ⚠️ Weather / Reschedule
            </button>
            <button type="button" @click="useTemplate('schedule')" class="px-3 py-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-900 border border-blue-200 text-xs font-bold transition-all">
                ℹ️ Regular Network Schedule
            </button>
            <button type="button" @click="useTemplate('runway')" class="px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-900 border border-rose-200 text-xs font-bold transition-all">
                🚨 Airport Advisory
            </button>
        </div>
    </div>

    <form id="notice-create-form" method="POST" action="{{ route('admin.notices.store') }}" class="space-y-6">
        @csrf

        <!-- CARD 1: Announcement Identity & Category -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Announcement Content & Category</h2>
                        <p class="text-xs text-ink-muted">Define the notice headline, badge tag, ticker message, and alert classification.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Internal Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           x-model="title"
                           placeholder="e.g. Flight 201 Weather Reschedule" 
                           required 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Badge Text -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Badge Tag Label
                    </label>
                    <input type="text" 
                           name="badge_text" 
                           x-model="badgeText"
                           placeholder="e.g. Weather Alert, Travel Notice" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Message -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Ticker Message Text <span class="text-rose-500">*</span>
                    </label>
                    <textarea name="message" 
                              rows="3" 
                              x-model="message"
                              placeholder="e.g. Flight 201 to Nauru has been rescheduled due to adverse weather conditions." 
                              required 
                              class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent leading-relaxed"></textarea>
                    <p class="text-[11px] text-slate-400 mt-1">This text appears prominently in the yellow top ticker bar.</p>
                </div>

                <!-- Alert Category Dropdown / Radios -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Notice Type Style <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'notice_types']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Types</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        @forelse($noticeTypes as $nt)
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all" :class="type === '{{ $nt->value }}' ? 'border-navy bg-navy-50 ring-2 ring-navy/20' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="type" value="{{ $nt->value }}" x-model="type" class="text-navy">
                                <span class="text-xs font-bold text-navy">{{ $nt->name }}</span>
                            </label>
                        @empty
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all" :class="type === 'warning' ? 'border-amber-400 bg-amber-50 ring-2 ring-amber-200' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="type" value="warning" x-model="type" class="text-amber-500">
                                <span class="text-xs font-bold text-amber-900">⚠️ Warning</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all" :class="type === 'alert' ? 'border-rose-400 bg-rose-50 ring-2 ring-rose-200' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="type" value="alert" x-model="type" class="text-rose-500">
                                <span class="text-xs font-bold text-rose-900">🚨 Alert</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all" :class="type === 'info' ? 'border-blue-400 bg-blue-50 ring-2 ring-blue-200' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="type" value="info" x-model="type" class="text-blue-500">
                                <span class="text-xs font-bold text-blue-900">ℹ️ Info</span>
                            </label>
                            <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all" :class="type === 'success' ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" name="type" value="success" x-model="type" class="text-emerald-500">
                                <span class="text-xs font-bold text-emerald-900">✅ Notice</span>
                            </label>
                        @endforelse
                    </div>
                </div>

                <!-- Action Link URL -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Optional Target Link URL
                    </label>
                    <input type="text" 
                           name="link" 
                           x-model="link"
                           placeholder="e.g. /destinations or /contact" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-mono text-xs">
                    <p class="text-[11px] text-slate-400 mt-1">Directs users to a full guidance page when clicked.</p>
                </div>
            </div>
        </div>

        <!-- CARD 2: Timing, Duration & Expiration -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Active Duration & Schedule Timing</h2>
                    <p class="text-xs text-ink-muted">Set automatic ticker expiration or schedule future announcements.</p>
                </div>
            </div>

            <!-- Quick Duration Presets -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Duration Presets</label>
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" @click="setPreset(1)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 1 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        1 Hour
                    </button>
                    <button type="button" @click="setPreset(6)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 6 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        6 Hours
                    </button>
                    <button type="button" @click="setPreset(12)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 12 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        12 Hours
                    </button>
                    <button type="button" @click="setPreset(24)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 24 ? 'bg-accent text-navy border-accent font-extrabold shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        24 Hours (1 Day)
                    </button>
                    <button type="button" @click="setPreset(48)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 48 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        48 Hours (2 Days)
                    </button>
                    <button type="button" @click="setPreset(72)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 72 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        72 Hours (3 Days)
                    </button>
                    <button type="button" @click="setPreset(168)" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 168 ? 'bg-navy text-white border-navy shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        1 Week
                    </button>
                    <button type="button" @click="setPreset('permanent')" class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all border" :class="customHours === 0 ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'">
                        Permanent / No Expiry
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50/50 p-4 rounded-xl border border-slate-200">
                <!-- Custom Duration in Hours -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Active Duration (Hours)
                    </label>
                    <div class="relative">
                        <input type="number" 
                               name="duration_hours" 
                               x-model="customHours"
                               min="0" 
                               max="8760" 
                               placeholder="0 for permanent" 
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold bg-white">
                        <span class="absolute right-3 top-2.5 text-xs font-bold text-slate-400">hours</span>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-1">Set to 0 for permanent / indefinite display.</p>
                </div>

                <!-- Scheduled Start Time -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Start Date & Time
                    </label>
                    <input type="datetime-local" 
                           name="starts_at" 
                           x-model="startTime"
                           class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <p class="text-[10px] text-slate-500 mt-1">Leave as now for immediate activation.</p>
                </div>

                <!-- Priority / Sort Order -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Priority (0 - 100)
                    </label>
                    <input type="number" 
                           name="priority" 
                           x-model="priority"
                           min="0" 
                           max="100" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                    <p class="text-[10px] text-slate-500 mt-1">Higher priority notices show first in rotation.</p>
                </div>
            </div>
        </div>

        <!-- CARD 3: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Publication</h2>
                    <p class="text-xs text-ink-muted">Control whether this notice is currently published and broadcasting live.</p>
                </div>
            </div>

            <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors max-w-md">
                <div class="relative flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" x-model="isActive">
                    <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-navy block">Publish Live on Site</span>
                    <span class="text-xs text-slate-500">Immediately broadcast on top ticker banner for all website visitors.</span>
                </div>
            </label>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.notices.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Publish Notice</span>
            </button>
        </div>
    </form>

</div>
@endsection
