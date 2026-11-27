@extends('layouts.admin')

@section('title', 'Site Settings, Header & Notice Bar')

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-navy">Site Settings, Branding & Top Header</h1>
            <p class="text-sm text-ink-muted mt-1">Configure airline brand logo, top header welcome text, quick navigation menus, emergency alert banner, and system contact details.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.notices.index') }}" class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
                </svg>
                <span>Manage All Announcements ({{ \App\Models\Notice::count() }})</span>
            </a>
        </div>
    </div>

    <!-- Notice Bar Announcements Management Card -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-extrabold text-navy">Header Notice Bar / Travel Alerts</h2>
                <p class="text-xs text-ink-muted">The alert bar displayed at the top of the frontend website for urgent flight notices & weather advisories.</p>
            </div>
            <a href="{{ route('admin.notices.index') }}" class="text-xs font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1">
                <span>Open Advanced Notice Manager</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="space-y-4">
            @forelse($notices as $notice)
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4 shadow-sm">
                    <form method="POST" action="{{ route('admin.notices.update', $notice) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-navy uppercase tracking-wider">Announcement #{{ $notice->id }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $notice->status_badge_class }}">
                                    {{ $notice->status_text }} ({{ $notice->remaining_time_text }})
                                </span>
                            </div>

                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ $notice->is_active ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                                <span class="ml-3 text-xs font-bold text-slate-700">Display Live</span>
                            </label>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Alert Badge / Title</label>
                                <input type="text" 
                                       name="title" 
                                       value="{{ old('title', $notice->title) }}" 
                                       required
                                       class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Notice Message</label>
                                <input type="text" 
                                       name="message" 
                                       value="{{ old('message', $notice->message) }}" 
                                       required
                                       class="w-full px-3.5 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Category Type</label>
                                <select name="type" class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold">
                                    <option value="warning" {{ $notice->type === 'warning' ? 'selected' : '' }}>⚠️ Warning / Weather</option>
                                    <option value="alert" {{ in_array($notice->type, ['alert', 'danger']) ? 'selected' : '' }}>🚨 Urgent / Alert</option>
                                    <option value="info" {{ $notice->type === 'info' ? 'selected' : '' }}>ℹ️ General Info</option>
                                    <option value="success" {{ $notice->type === 'success' ? 'selected' : '' }}>✅ Promo / Normal</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Duration in Hours</label>
                                <input type="number" 
                                       name="duration_hours" 
                                       value="{{ old('duration_hours', $notice->duration_hours ?? 0) }}" 
                                       min="0"
                                       placeholder="0 for permanent"
                                       class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold">
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Optional Link URL</label>
                                <input type="text" 
                                       name="link" 
                                       value="{{ old('link', $notice->link) }}" 
                                       placeholder="/destinations or https://..."
                                       class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-mono">
                            </div>

                            <div class="md:col-span-3 flex items-center justify-between pt-2 border-t border-slate-100">
                                <span class="text-xs text-slate-500">
                                    Expires: <span class="font-mono font-bold">{{ $notice->expires_at ? $notice->expires_at->format('M d, Y H:i') : 'Permanent' }}</span>
                                </span>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.notices.edit', $notice) }}" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-all">
                                        Advanced Controls
                                    </a>
                                    <button type="submit" class="px-5 py-2 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-xs shadow-md shadow-accent/20 transition-all">
                                        Update Notice
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @empty
                <div class="naoero-card p-6 bg-white border border-slate-200 text-center text-slate-400">
                    <p class="text-sm">No notice bar entries found.</p>
                    <a href="{{ route('admin.notices.create') }}" class="inline-block mt-3 px-4 py-2 rounded-xl bg-accent text-navy font-bold text-xs">
                        Create First Announcement
                    </a>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Global Site Settings Form -->
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-extrabold text-navy">Global Airline Branding & Configuration</h2>
            <p class="text-xs text-ink-muted">Manage airline logo, top bar announcement text, quick menu links, contact emails, and social profiles.</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- 1. Brand Logo & Visual Identity Card -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-navy uppercase tracking-wider text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Airline Branding & Header Logo
                    </h3>
                    <span class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">Used in Header & Footer</span>
                </div>

                @php
                    $currentLogo = \App\Models\SiteSetting::get('site_logo', '/assets/logo.png');
                    $logoDisplayUrl = str_starts_with($currentLogo, 'http') ? $currentLogo : asset($currentLogo);
                @endphp

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <!-- Current Logo Preview -->
                    <div class="flex flex-col items-center justify-center p-4 bg-navy rounded-2xl border border-slate-200">
                        <span class="text-[11px] font-bold text-slate-300 uppercase tracking-wider mb-2">Live Logo Preview</span>
                        <div class="h-20 flex items-center justify-center">
                            <img src="{{ $logoDisplayUrl }}" alt="Naoero Airlines Logo" class="max-h-16 max-w-full object-contain">
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono mt-2 truncate max-w-full">{{ $currentLogo }}</span>
                    </div>

                    <!-- Logo Upload & Path Controls -->
                    <div class="md:col-span-2 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Upload New Logo Image
                                <span class="text-[10px] text-slate-400 font-normal">(PNG, SVG, WebP up to 5MB, recommended height 64px)</span>
                            </label>
                            <input type="file" 
                                   name="site_logo_file" 
                                   accept="image/*"
                                   class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy/90 file:cursor-pointer border border-slate-200 rounded-xl p-1.5">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Or Specify Logo URL / Asset Path
                            </label>
                            <input type="text" 
                                   name="site_logo" 
                                   value="{{ old('site_logo', $currentLogo) }}" 
                                   placeholder="/assets/logo.png or https://..."
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-mono">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Top Header Announcement Text & Welcome Bar -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-navy uppercase tracking-wider text-xs flex items-center gap-2">
                        <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                        </svg>
                        Top Header Bar Welcome Text
                    </h3>
                    <span class="text-[11px] font-bold text-slate-500 bg-slate-100 px-2.5 py-0.5 rounded-full">Top Blue Bar Banner</span>
                </div>

                @php
                    $headerText = \App\Models\SiteSetting::get('top_header_text', 'Welcome to Naoero Airline');
                @endphp

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Welcome Announcement Text (Displayed on Left Side of Top Bar)
                        </label>
                        <input type="text" 
                               name="top_header_text" 
                               value="{{ old('top_header_text', $headerText) }}" 
                               required
                               placeholder="e.g. Welcome to Naoero Airline"
                               class="w-full px-3.5 py-2.5 text-sm font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent text-navy">
                    </div>

                    <!-- Live Top Bar Preview Mock -->
                    <div class="p-3 bg-navy text-white rounded-xl flex items-center justify-between text-xs">
                        <span class="font-bold flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                            {{ $headerText }}
                        </span>
                        <span class="text-[11px] text-slate-300 font-medium">My Booking | Flight Status | Contact Us</span>
                    </div>
                </div>
            </div>

            <!-- 3. Top Header Quick Menu Links (My Booking, Flight Status, Contact Us) -->
            <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-navy uppercase tracking-wider text-xs flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                            </svg>
                            Top Header Quick Menu Navigation
                        </h3>
                        <p class="text-xs text-ink-muted mt-0.5">Manage utility navigation links shown on the right side of the top header bar.</p>
                    </div>

                    <button type="button" onclick="addMenuItem()" class="px-3 py-1.5 rounded-xl bg-navy text-white text-xs font-bold hover:bg-navy/90 flex items-center gap-1">
                        <span>+ Add Menu Item</span>
                    </button>
                </div>

                @php
                    $menuJson = \App\Models\SiteSetting::get('top_header_menu');
                    $menuItems = json_decode($menuJson, true) ?: [
                        ['label' => 'My Booking', 'route' => 'https://book.naoeroairlines.com/manage', 'icon' => 'Ticket', 'is_external' => true],
                        ['label' => 'Flight Status', 'route' => '/travel-info/flight-schedule', 'icon' => 'PlaneTakeoff', 'is_external' => false],
                        ['label' => 'Contact Us', 'route' => '/contact-us', 'icon' => 'Phone', 'is_external' => false],
                    ];
                @endphp

                <div id="menu-items-container" class="space-y-3">
                    @foreach($menuItems as $idx => $item)
                        <div class="menu-item-row p-4 bg-slate-50 rounded-xl border border-slate-200 grid grid-cols-1 md:grid-cols-12 gap-3 items-center">
                            <div class="md:col-span-3">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Link Title / Label</label>
                                <input type="text" 
                                       name="menu_labels[]" 
                                       value="{{ $item['label'] ?? '' }}" 
                                       placeholder="e.g. My Booking" 
                                       required
                                       class="w-full px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                            </div>

                            <div class="md:col-span-4">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Target Route / URL</label>
                                <input type="text" 
                                       name="menu_routes[]" 
                                       value="{{ $item['route'] ?? '' }}" 
                                       placeholder="/contact-us or https://..." 
                                       required
                                       class="w-full px-3 py-2 text-xs font-mono rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Icon Style</label>
                                <select name="menu_icons[]" class="w-full px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                                    <option value="Ticket" {{ ($item['icon'] ?? '') === 'Ticket' ? 'selected' : '' }}>🎫 Ticket</option>
                                    <option value="PlaneTakeoff" {{ ($item['icon'] ?? '') === 'PlaneTakeoff' ? 'selected' : '' }}>✈️ Plane</option>
                                    <option value="Phone" {{ ($item['icon'] ?? '') === 'Phone' ? 'selected' : '' }}>📞 Phone</option>
                                    <option value="LogIn" {{ ($item['icon'] ?? '') === 'LogIn' ? 'selected' : '' }}>🔐 Login</option>
                                    <option value="Globe" {{ ($item['icon'] ?? '') === 'Globe' ? 'selected' : '' }}>🌐 Globe</option>
                                </select>
                            </div>

                            <div class="md:col-span-2 flex items-center pt-4">
                                <label class="inline-flex items-center cursor-pointer">
                                    <input type="hidden" name="menu_externals[]" value="{{ !empty($item['is_external']) ? '1' : '0' }}">
                                    <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="rounded border-slate-300 text-accent focus:ring-accent" {{ !empty($item['is_external']) ? 'checked' : '' }}>
                                    <span class="ml-2 text-xs font-medium text-slate-700">Open External</span>
                                </label>
                            </div>

                            <div class="md:col-span-1 flex items-center justify-end pt-4">
                                <button type="button" onclick="this.closest('.menu-item-row').remove()" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-all" title="Remove Item">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 4. General, Contact & Social Settings -->
            @foreach($settings as $group => $items)
                @if(!in_array($group, ['branding', 'header']))
                    <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5 shadow-sm">
                        <h3 class="text-base font-bold text-navy border-b border-slate-100 pb-3 uppercase tracking-wider text-xs">
                            {{ $group }} Settings
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            @foreach($items as $setting)
                                @if(!in_array($setting->key, ['site_logo', 'top_header_text', 'top_header_menu']))
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                            {{ ucwords(str_replace(['site_', '_'], ['', ' '], $setting->key)) }}
                                            <span class="text-[10px] text-slate-400 font-mono">({{ $setting->key }})</span>
                                        </label>
                                        <input type="text" 
                                               name="{{ $setting->key }}" 
                                               value="{{ old($setting->key, $setting->value) }}" 
                                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <!-- Save Button -->
            <div class="flex items-center justify-end pt-4">
                <button type="submit" class="px-8 py-3 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-sm shadow-md shadow-accent/20 transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Save All Site Settings & Navigation</span>
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function addMenuItem() {
    const container = document.getElementById('menu-items-container');
    const row = document.createElement('div');
    row.className = 'menu-item-row p-4 bg-slate-50 rounded-xl border border-slate-200 grid grid-cols-1 md:grid-cols-12 gap-3 items-center';
    row.innerHTML = `
        <div class="md:col-span-3">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Link Title / Label</label>
            <input type="text" name="menu_labels[]" value="" placeholder="e.g. Flight Status" required class="w-full px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
        </div>
        <div class="md:col-span-4">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Target Route / URL</label>
            <input type="text" name="menu_routes[]" value="" placeholder="/travel-info/flight-schedule" required class="w-full px-3 py-2 text-xs font-mono rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
        </div>
        <div class="md:col-span-2">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Icon Style</label>
            <select name="menu_icons[]" class="w-full px-3 py-2 text-xs font-bold rounded-lg border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40">
                <option value="Ticket">🎫 Ticket</option>
                <option value="PlaneTakeoff" selected>✈️ Plane</option>
                <option value="Phone">📞 Phone</option>
                <option value="LogIn">🔐 Login</option>
                <option value="Globe">🌐 Globe</option>
            </select>
        </div>
        <div class="md:col-span-2 flex items-center pt-4">
            <label class="inline-flex items-center cursor-pointer">
                <input type="hidden" name="menu_externals[]" value="0">
                <input type="checkbox" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'" class="rounded border-slate-300 text-accent focus:ring-accent">
                <span class="ml-2 text-xs font-medium text-slate-700">Open External</span>
            </label>
        </div>
        <div class="md:col-span-1 flex items-center justify-end pt-4">
            <button type="button" onclick="this.closest('.menu-item-row').remove()" class="p-1.5 text-rose-500 hover:text-rose-700 hover:bg-rose-50 rounded-lg transition-all" title="Remove Item">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    `;
    container.appendChild(row);
}
</script>
@endsection
