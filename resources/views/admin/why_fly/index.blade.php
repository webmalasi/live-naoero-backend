@extends('layouts.admin')

@section('title', 'Why Fly With Us - Homepage Cards')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Why Fly With Us Cards</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total'] }} Feature Cards
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage the trust, safety, and hospitality selling points displayed on the Homepage Why Fly With Us section.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.why-fly.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Feature Card</span>
            </a>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="naoero-card p-4 bg-white border border-slate-200 flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Cards</span>
                <span class="text-2xl font-extrabold text-navy mt-1 block">{{ $stats['total'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-navy/10 flex items-center justify-center text-navy font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
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
                <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Inactive / Hidden</span>
                <span class="text-2xl font-extrabold text-slate-600 mt-1 block">{{ $stats['inactive'] }}</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 font-bold">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Section Headings Settings Bar -->
    <div class="naoero-card p-5 bg-white border border-slate-200">
        <h3 class="text-sm font-extrabold text-navy mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span>Homepage Section Heading & Subheading</span>
        </h3>
        <form method="POST" action="{{ route('admin.why-fly.update-headings') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
            @csrf
            <div class="sm:col-span-4">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Section Heading</label>
                <input type="text" name="why_fly_heading" value="{{ old('why_fly_heading', $sectionHeading) }}" required class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-bold">
            </div>
            <div class="sm:col-span-6">
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Section Subheading</label>
                <input type="text" name="why_fly_subheading" value="{{ old('why_fly_subheading', $sectionSubheading) }}" class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full py-2 px-4 bg-navy text-white text-xs font-bold rounded-xl hover:bg-navy/90 shadow transition-all">
                    Save Headings
                </button>
            </div>
        </form>
    </div>

    <!-- Features Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @forelse($features as $feature)
            <div class="naoero-card bg-white border border-slate-200 rounded-2xl p-5 flex flex-col justify-between shadow-sm hover:shadow-md transition-shadow">
                <div>
                    <!-- Icon / Badge & Status -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-full border border-navy/20 bg-navy/5 flex items-center justify-center text-navy font-bold">
                            @if($feature->icon_image)
                                <img src="{{ $feature->icon_image_url }}" alt="{{ $feature->title }}" class="w-6 h-6 object-contain">
                            @else
                                <span class="text-xs font-mono font-bold">{{ substr($feature->icon, 0, 3) }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('admin.why-fly.toggle-active', $feature) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="px-2 py-0.5 rounded-full text-[11px] font-bold cursor-pointer transition-all {{ $feature->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                    {{ $feature->is_active ? 'Active' : 'Hidden' }}
                                </button>
                            </form>
                            <span class="text-xs font-bold text-slate-400">#{{ $feature->sort_order }}</span>
                        </div>
                    </div>

                    <!-- Title & Description -->
                    <h3 class="text-base font-bold text-navy mb-1.5">{{ $feature->title }}</h3>
                    <p class="text-xs text-slate-600 leading-relaxed mb-3">{{ $feature->description }}</p>

                    <!-- Icon Name Badge -->
                    <div class="flex items-center gap-1.5 text-[11px] text-slate-400 font-mono">
                        <span>Icon:</span>
                        <span class="font-bold text-navy bg-slate-100 px-1.5 py-0.5 rounded">{{ $feature->icon }}</span>
                    </div>
                </div>

                <!-- Card Footer Actions -->
                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-[11px] text-slate-400">ID: {{ $feature->id }}</span>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.why-fly.edit', $feature) }}" 
                           class="p-1.5 text-navy hover:bg-navy/10 rounded-lg transition-all" 
                           title="Edit Card">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.why-fly.destroy', $feature) }}" onsubmit="return confirm('Are you sure you want to delete this feature card?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition-all" 
                                    title="Delete Card">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="sm:col-span-4 p-12 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                <p class="text-sm font-semibold text-slate-600">No feature cards found.</p>
                <p class="text-xs text-slate-400 mt-1">Create your first Why Fly With Us selling point.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection
