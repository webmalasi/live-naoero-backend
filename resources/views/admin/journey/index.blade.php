@extends('layouts.admin')

@section('title', 'Our Journey & Enhance Journey Management')

@section('content')
@php $only = request()->query('only'); @endphp
<div class="space-y-8">

    @unless($only)
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Journey & Travel Essentials Hub</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total_milestones'] }} Milestones &bull; {{ $stats['total_enhance'] }} Stays & Activities
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage the historical "Our Journey" timeline (About page) and "Enhance Your Journey" travel essentials (Homepage).</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.journey.milestones.create') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-bold text-xs shadow transition-all">
                <span class="text-accent">+</span>
                <span>Add History Milestone</span>
            </a>
            <a href="{{ route('admin.journey.enhance.create') }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-xs shadow-md shadow-accent/20 transition-all">
                <span class="font-bold">+</span>
                <span>Add Enhance Journey Card</span>
            </a>
        </div>
    </div>

    @endunless

    <!-- Section Headings Settings Bar -->
    <div class="naoero-card p-5 bg-white border border-slate-200">
        <h3 class="text-sm font-extrabold text-navy mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Section Headings & Descriptions</span>
        </h3>
        <form method="POST" action="{{ route('admin.journey.update-headings') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @csrf
            @if(! $only || $only === 'milestones')
            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Our Journey Heading</label>
                <input type="text" name="our_journey_heading" value="{{ old('our_journey_heading', $ourJourneyHeading) }}" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 font-bold">
            </div>
            @endif
            @if(! $only || $only === 'enhance')
            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Enhance Journey Heading</label>
                <input type="text" name="enhance_journey_heading" value="{{ old('enhance_journey_heading', $enhanceHeading) }}" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 font-bold">
            </div>
            <div class="sm:col-span-4">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Enhance Subtitle</label>
                <input type="text" name="enhance_journey_subheading" value="{{ old('enhance_journey_subheading', $enhanceSubheading) }}" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200">
            </div>
            @endif
            <div class="sm:col-span-2">
                <button type="submit" class="w-full py-2 bg-navy text-white text-xs font-bold rounded-xl hover:bg-navy/90 shadow transition-all">
                    Save Headings
                </button>
            </div>
        </form>
    </div>

    @if(! $only || $only === 'milestones')
    <!-- SECTION 1: OUR JOURNEY TIMELINE MILESTONES -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-lg font-bold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                    <span>1. Our Journey Timeline Milestones (About Us Page)</span>
                </h2>
                <p class="text-xs text-slate-500">Historical steps rendered on the animated timeline of /about page.</p>
            </div>
            <a href="{{ route('admin.journey.milestones.create') }}" class="text-xs font-bold text-navy hover:text-accent">+ Add Milestone</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($milestones as $m)
                <div class="naoero-card bg-white border border-slate-200 rounded-2xl p-4 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-lg font-black text-navy bg-slate-100 px-2.5 py-0.5 rounded-lg border border-slate-200">{{ $m->year }}</span>
                            <form method="POST" action="{{ route('admin.journey.milestones.toggle-active', $m) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-2 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all {{ $m->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $m->is_active ? 'Active' : 'Hidden' }}
                                </button>
                            </form>
                        </div>
                        <div class="h-28 rounded-xl overflow-hidden bg-slate-100 mb-3 border border-slate-100">
                            @if($m->image_url)
                                <img src="{{ $m->image_url }}" alt="{{ $m->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No Image</div>
                            @endif
                        </div>
                        <h3 class="font-bold text-navy text-sm mb-1">{{ $m->title }}</h3>
                        <p class="text-xs text-slate-600 line-clamp-3 leading-relaxed">{{ $m->description }}</p>
                    </div>

                    <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-mono text-slate-400">Order #{{ $m->sort_order }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.journey.milestones.edit', $m) }}" class="p-1.5 text-navy hover:bg-navy/10 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.journey.milestones.destroy', $m) }}" onsubmit="return confirm('Delete this milestone?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-4 p-8 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                    No milestones found.
                </div>
            @endforelse
        </div>
    </div>

    @endif

    @if(! $only || $only === 'enhance')
    <!-- SECTION 2: ENHANCE YOUR JOURNEY CARDS -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-lg font-bold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>2. Enhance Your Journey Cards (Homepage Booking Essentials)</span>
                </h2>
                <p class="text-xs text-slate-500">Stays, car rentals, travel insurance, and adventures displayed on homepage.</p>
            </div>
            <a href="{{ route('admin.journey.enhance.create') }}" class="text-xs font-bold text-navy hover:text-accent">+ Add Card</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @forelse($enhanceItems as $item)
                <div class="naoero-card bg-white border border-slate-200 rounded-2xl p-4 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-mono font-bold text-navy bg-slate-100 px-2 py-0.5 rounded">{{ $item->icon }}</span>
                            <form method="POST" action="{{ route('admin.journey.enhance.toggle-active', $item) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-2 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all {{ $item->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $item->is_active ? 'Active' : 'Hidden' }}
                                </button>
                            </form>
                        </div>
                        <div class="h-28 rounded-xl overflow-hidden bg-slate-100 mb-3 border border-slate-100">
                            @if($item->image_url)
                                <img src="{{ $item->image_url }}" alt="{{ $item->title }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-slate-400 text-xs">No Image</div>
                            @endif
                        </div>
                        <h3 class="font-bold text-navy text-sm mb-1">{{ $item->title }}</h3>
                        <p class="text-xs text-slate-600 line-clamp-2 leading-relaxed">{{ $item->description }}</p>
                        @if($item->link_url)
                            <span class="text-[10px] text-accent-hover font-mono mt-1 block truncate">&rarr; {{ $item->link_url }}</span>
                        @endif
                    </div>

                    <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[11px] font-mono text-slate-400">Order #{{ $item->sort_order }}</span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.journey.enhance.edit', $item) }}" class="p-1.5 text-navy hover:bg-navy/10 rounded-lg">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            </a>
                            <form method="POST" action="{{ route('admin.journey.enhance.destroy', $item) }}" onsubmit="return confirm('Delete this card?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="sm:col-span-4 p-8 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                    No enhance journey cards found.
                </div>
            @endforelse
        </div>
    </div>
    @endif

</div>
@endsection
