@extends('layouts.admin')

@section('title', 'Special Flight Offers')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Special Flight Offers & Deals</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $specials->total() }} Offers
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage promotional airfares, route specials, and seasonal Pacific island flight discounts.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.specials.create') }}" 
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-bold text-sm shadow-md shadow-accent/20 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Create Special Offer</span>
            </a>
        </div>
    </div>

    <!-- Specials Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($specials as $offer)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col justify-between">
                <div>
                    <!-- Image with price overlay -->
                    <div class="relative h-44 bg-navy-950 overflow-hidden group">
                        <img src="{{ $offer->image_url }}" 
                             alt="{{ $offer->title }}" 
                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                             onerror="this.src='https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80'">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-transparent to-transparent"></div>

                        @if($offer->badge)
                            <div class="absolute top-3 left-3">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold uppercase tracking-wider bg-accent text-navy shadow-md">
                                    {{ $offer->badge }}
                                </span>
                            </div>
                        @endif

                        <div class="absolute top-3 right-3">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold shadow-sm {{ $offer->is_active ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }}">
                                {{ $offer->is_active ? 'Active' : 'Expired' }}
                            </span>
                        </div>

                        <div class="absolute bottom-3 left-3 right-3 text-white flex items-end justify-between">
                            <div>
                                <span class="text-[11px] text-accent font-bold uppercase tracking-wider block">
                                    {{ $offer->origin ?: 'Pacific Network' }} &rarr; {{ $offer->destination ?: 'Island Gateway' }}
                                </span>
                                <h3 class="text-base font-extrabold leading-tight text-white">{{ $offer->title }}</h3>
                            </div>
                            @if($offer->price)
                                <div class="text-right shrink-0">
                                    <span class="text-[10px] text-white/70 block">From</span>
                                    <span class="text-lg font-extrabold text-accent font-mono">{{ $offer->currency }} ${{ number_format($offer->price, 0) }}*</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="p-4 space-y-2">
                        @if($offer->valid_until)
                            <p class="text-xs text-slate-500">
                                Valid until: <strong class="text-navy">{{ $offer->valid_until->format('d M Y') }}</strong>
                            </p>
                        @endif

                        @if($offer->description)
                            <p class="text-xs text-slate-600 line-clamp-2">{{ $offer->description }}</p>
                        @endif
                    </div>
                </div>

                <!-- Footer Actions -->
                <div class="p-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-mono text-slate-400">Order: #{{ $offer->sort_order }}</span>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('admin.specials.edit', $offer) }}" 
                           class="p-1.5 text-slate-400 hover:text-navy hover:bg-white rounded-lg transition-colors"
                           title="Edit Offer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </a>
                        <form method="POST" action="{{ route('admin.specials.destroy', $offer) }}" 
                              onsubmit="return confirm('Are you sure you want to delete offer \'{{ $offer->title }}\'?');"
                              class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors"
                                    title="Delete Offer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full naoero-card p-12 text-center text-slate-400 bg-white border border-slate-200">
                <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-base font-bold text-slate-600">No active special offers</p>
                <p class="text-xs text-slate-400 mt-1">Create promotional flight deals to attract more bookings.</p>
            </div>
        @endforelse
    </div>

    @if($specials->hasPages())
        <div class="naoero-card p-4 bg-white border border-slate-200">
            {{ $specials->links() }}
        </div>
    @endif

</div>
@endsection
