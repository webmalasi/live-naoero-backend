@extends('layouts.admin')

@section('title', 'Edit Agency Contact - ' . $agency->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Top Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.agencies.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.agencies.index') }}" class="hover:text-navy transition-colors">Port Offices & Agencies</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit Office</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit Agency / PSA Office</h1>
                <p class="text-xs text-ink-muted line-clamp-1 max-w-xl">{{ $agency->name }} &bull; {{ $agency->country }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.agencies.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('agency-edit-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Changes</span>
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

    <form id="agency-edit-form" method="POST" action="{{ route('admin.agencies.update', $agency) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- CARD 1: Office & Country Location -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Office Details & Country Territory</h2>
                        <p class="text-xs text-ink-muted">Update office designation, host country, regional flag, and external link.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Agency Name -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Agency or Office Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="name" 
                           value="{{ old('name', $agency->name) }}" 
                           required 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                </div>

                <!-- Country -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Operating Country <span class="text-rose-500">*</span>
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'agency_countries']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent flex items-center gap-1 transition-colors">
                            <span>+ Manage Countries</span>
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <select name="country" required class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent bg-white">
                        @foreach($countries as $country)
                            <option value="{{ $country }}" {{ old('country', $agency->country) === $country ? 'selected' : '' }}>{{ $country }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Flag Emoji -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Country Flag Emoji
                    </label>
                    <input type="text" 
                           name="flag" 
                           value="{{ old('flag', $agency->flag) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Website URL -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Website URL (Optional)
                    </label>
                    <input type="url" 
                           name="website" 
                           value="{{ old('website', $agency->website) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', $agency->sort_order) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- CARD 2: Physical Address -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Physical Address & Location</h2>
                    <p class="text-xs text-ink-muted">Terminal floor, building suite, district and postal location.</p>
                </div>
            </div>

            <div>
                <textarea name="address" 
                          rows="3" 
                          class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent leading-relaxed">{{ old('address', $agency->address) }}</textarea>
            </div>
        </div>

        <!-- CARD 3: Communications & Contact Channels -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Communication Channels</h2>
                    <p class="text-xs text-ink-muted">Enter direct telephone extensions and support email inboxes (one per line).</p>
                </div>
            </div>

            @php
                $phonesText = is_array($agency->phones) ? implode("\n", $agency->phones) : '';
                $emailsText = is_array($agency->emails) ? implode("\n", $agency->emails) : '';
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Phone Numbers (One per row)
                    </label>
                    <textarea name="phones" 
                              rows="4" 
                              class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('phones', $phonesText) }}</textarea>
                    <p class="text-[11px] text-slate-400 mt-1">International dial code format recommended.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Email Inboxes (One per row)
                    </label>
                    <textarea name="emails" 
                              rows="4" 
                              class="w-full px-3.5 py-2.5 text-sm font-mono rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('emails', $emailsText) }}</textarea>
                    <p class="text-[11px] text-slate-400 mt-1">Direct inquiries to these listed mailboxes.</p>
                </div>
            </div>
        </div>

        <!-- CARD 4: Status & Visibility -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                <div>
                    <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Status & Publication</h2>
                    <p class="text-xs text-ink-muted">Control whether this office appears in the public agent directory.</p>
                </div>
            </div>

            <label class="flex items-start gap-3.5 p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-slate-50 cursor-pointer transition-colors max-w-md">
                <div class="relative flex items-center mt-0.5">
                    <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $agency->is_active) ? 'checked' : '' }}>
                    <div class="w-10 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </div>
                <div>
                    <span class="text-sm font-extrabold text-navy block">Publish in Directory</span>
                    <span class="text-xs text-slate-500">Visible on the agency lookup page and booking office directory.</span>
                </div>
            </label>
        </div>

        <!-- Bottom Action Bar -->
        <div class="flex items-center justify-between p-4 rounded-2xl bg-white border border-slate-200 shadow-sm">
            <a href="{{ route('admin.agencies.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Changes</span>
            </button>
        </div>
    </form>

</div>
@endsection
