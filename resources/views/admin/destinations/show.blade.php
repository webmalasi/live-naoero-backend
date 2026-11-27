@extends('layouts.admin')

@section('title', 'Destination: ' . $destination->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    <!-- Header & Action Buttons -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.destinations.index') }}" 
               class="p-2 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-extrabold text-navy">{{ $destination->name }}</h1>
                    @if($destination->flag)
                        <span class="text-xl">{{ $destination->flag }}</span>
                    @endif
                    @if($destination->is_featured)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">Featured on Home</span>
                    @endif
                    @if($destination->is_active)
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">Active</span>
                    @else
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-800">Inactive</span>
                    @endif
                </div>
                <p class="text-sm text-ink-muted mt-0.5">{{ $destination->country }} &bull; Route: <code class="font-mono text-xs">/destinations/{{ $destination->slug }}</code></p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.destinations.edit', $destination) }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-navy text-white text-sm font-bold hover:bg-navy-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <span>Edit Destination</span>
            </a>

            <form method="POST" action="{{ route('admin.destinations.destroy', $destination) }}" 
                  onsubmit="return confirm('Are you sure you want to delete \'{{ $destination->name }}\'?');" 
                  class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" 
                        class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-600 hover:bg-rose-100 transition-colors"
                        title="Delete Destination">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </button>
            </form>
        </div>
    </div>

    <!-- Hero Banner Preview -->
    <div class="naoero-card overflow-hidden bg-white border border-slate-200">
        <div class="relative h-64 sm:h-80 w-full bg-slate-900">
            @if($destination->banner_image_url || $destination->banner_image)
                <img src="{{ $destination->banner_image_url ?: $destination->banner_image }}" 
                     alt="{{ $destination->name }}" 
                     class="w-full h-full object-cover opacity-80">
            @endif
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/40 to-transparent flex items-end p-6">
                <div class="max-w-xl bg-white/30 backdrop-blur-md p-5 rounded-2xl border border-white/20 text-navy">
                    <p class="text-[11px] font-extrabold uppercase tracking-widest text-navy/70">Destinations &gt; {{ $destination->name }}</p>
                    <h2 class="text-2xl font-black uppercase text-navy">{{ $destination->name }}</h2>
                    <p class="text-xs text-navy/90 mt-1 leading-relaxed">{{ $destination->banner_text ?: $destination->short_description }}</p>
                </div>
            </div>
        </div>

        <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Explore Card Preview -->
            <div>
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-400 mb-2">Explore Section Card Preview</p>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm max-w-xs">
                    <img src="{{ $destination->thumbnail_url ?: $destination->thumbnail }}" 
                         alt="{{ $destination->name }}" 
                         class="h-36 w-full object-cover">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-bold text-navy">{{ $destination->name }}</h3>
                            <span class="flex items-center gap-1 text-xs text-navy/70">
                                <span>{{ $destination->flag }}</span>
                                <span>{{ $destination->country }}</span>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-600 line-clamp-2">{{ $destination->short_description }}</p>
                    </div>
                </div>
            </div>

            <!-- Overview Text Details -->
            <div class="md:col-span-2 space-y-4">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Discover Heading</h3>
                    <p class="text-base font-bold text-navy mt-0.5">{{ $destination->discover_heading ?: 'Discover ' . $destination->name }}</p>
                </div>

                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-400">Intro Paragraph</h3>
                    <p class="text-sm text-slate-700 mt-1 leading-relaxed">{{ $destination->intro ?: 'No intro paragraph set.' }}</p>
                </div>

                @if($destination->quote)
                    <div class="p-4 rounded-xl bg-accent/20 border-l-4 border-accent text-navy text-sm font-semibold">
                        "{{ $destination->quote }}"
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Why Visit Section -->
    @if(!empty($destination->why_visit))
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <h2 class="text-lg font-bold text-navy border-b border-slate-100 pb-3">Why Visit {{ $destination->name }}?</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                @foreach($destination->why_visit as $card)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex flex-col">
                        @if(!empty($card['image']) || !empty($card['image_url']))
                            <div class="h-36 w-full bg-slate-100 overflow-hidden">
                                <img src="{{ \App\Models\Destination::formatImageUrl($card['image'] ?? $card['image_url']) }}" alt="{{ $card['title'] ?? 'Highlight' }}" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="h-24 w-full bg-gradient-to-r from-navy/5 to-accent/10 flex items-center justify-center text-slate-400">
                                <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-sm font-bold text-navy">{{ $card['title'] ?? 'Highlight' }}</h4>
                                @if(!empty($card['description']))
                                    <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">{{ $card['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Things to Do -->
    @if(!empty($destination->things_to_do))
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <h2 class="text-lg font-bold text-navy border-b border-slate-100 pb-3">Things To Do In {{ $destination->name }}</h2>
            @if($destination->things_to_do_intro)
                <p class="text-sm text-slate-600">{{ $destination->things_to_do_intro }}</p>
            @endif
            <div class="space-y-4">
                @foreach($destination->things_to_do as $item)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="text-base font-bold text-navy">{{ $item['title'] ?? 'Activity' }}</h4>
                        </div>
                        @if(!empty($item['description']))
                            <p class="text-xs text-slate-600 leading-relaxed">{{ $item['description'] }}</p>
                        @endif

                        @if(!empty($item['images']) && is_array($item['images']))
                            <div class="pt-2 border-t border-slate-100">
                                <p class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">Activity Showcase Photos</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($item['images'] as $img)
                                        @if(!empty($img))
                                            <div class="h-40 rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                                                <img src="{{ \App\Models\Destination::formatImageUrl($img) }}" alt="{{ $item['title'] ?? 'Activity' }}" class="w-full h-full object-cover">
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Stays / Hotels -->
    @if(!empty($destination->stays))
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <h2 class="text-lg font-bold text-navy border-b border-slate-100 pb-3">Where To Stay In {{ $destination->name }}</h2>
            @if($destination->stays_intro)
                <p class="text-sm text-slate-600">{{ $destination->stays_intro }}</p>
            @endif
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                @foreach($destination->stays as $stay)
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm flex flex-col">
                        @if(!empty($stay['image']) || !empty($stay['image_url']))
                            <div class="h-40 w-full bg-slate-100 overflow-hidden">
                                <img src="{{ \App\Models\Destination::formatImageUrl($stay['image'] ?? $stay['image_url']) }}" alt="{{ $stay['title'] ?? 'Hotel' }}" class="w-full h-full object-cover">
                            </div>
                        @else
                            <div class="h-28 w-full bg-slate-100 flex items-center justify-center text-slate-400">
                                <svg class="w-8 h-8 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                        @endif
                        <div class="p-4 flex-1 flex flex-col justify-between">
                            <div>
                                <h4 class="text-base font-bold text-navy">{{ $stay['title'] ?? 'Hotel' }}</h4>
                                @if(!empty($stay['description']))
                                    <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">{{ $stay['description'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
