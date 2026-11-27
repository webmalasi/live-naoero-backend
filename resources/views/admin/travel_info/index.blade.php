@extends('layouts.admin')

@section('title', 'Travel Info & Operations Hub')

@section('content')
@php $only = request()->query('only'); @endphp
<div class="space-y-8">

    @unless($only)
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-2xl font-extrabold text-navy">Travel Info & Operations Hub</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-navy/10 text-navy border border-navy/20">
                    {{ $stats['total_ports'] }} Ports &bull; {{ $stats['total_dg'] }} DG Items &bull; {{ $stats['total_cards'] }} Cards
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage airport port check-in times, dangerous goods classifications, and passenger helpful information cards.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.travel-info.check-in.create') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-navy text-white font-bold text-xs shadow hover:bg-navy-900 transition-all">
                <span class="text-accent">+</span>
                <span>Add Port Timetable</span>
            </a>
            <a href="{{ route('admin.travel-info.dangerous-goods.create') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-600 text-white font-bold text-xs shadow hover:bg-amber-700 transition-all">
                <span>+</span>
                <span>Add DG Item</span>
            </a>
            <a href="{{ route('admin.travel-info.cards.create') }}" 
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-accent text-navy font-bold text-xs shadow-md shadow-accent/20 hover:bg-accent-hover transition-all">
                <span>+</span>
                <span>Add Helpful Card</span>
            </a>
        </div>
    </div>

    @endunless

    @if(! $only || $only === 'check-in')
    <!-- SECTION 1: CHECK-IN TIMES TIMETABLE -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-lg font-bold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-navy"></span>
                    <span>1. Airport Check-In Times Timetable</span>
                </h2>
                <p class="text-xs text-slate-500">Official airport desk opening and closing timetable across the Pacific route network.</p>
            </div>
            <a href="{{ route('admin.travel-info.check-in.create') }}" class="text-xs font-bold text-navy hover:text-accent">+ Add Port</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[11px] font-extrabold text-navy uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Port & Airport Code</th>
                            <th class="px-5 py-3.5">Opens (Desk Opens)</th>
                            <th class="px-5 py-3.5">Closes (Desk Closes)</th>
                            <th class="px-5 py-3.5 text-center">Highlighted</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($checkInTimes as $port)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-navy flex items-center gap-2">
                                    <span class="w-6 h-6 rounded-full bg-navy/10 text-navy flex items-center justify-center text-[10px]">✈</span>
                                    <span>{{ $port->port }}</span>
                                    @if($port->airport_code)
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">{{ $port->airport_code }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-emerald-700 font-semibold">{{ $port->opens }}</td>
                                <td class="px-5 py-3.5 {{ $port->is_highlighted ? 'text-rose-600 font-bold' : 'text-slate-600' }}">{{ $port->closes }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    @if($port->is_highlighted)
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Special Notice</span>
                                    @else
                                        <span class="text-slate-300">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-center">
                                    <form method="POST" action="{{ route('admin.travel-info.check-in.toggle-active', $port) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-all {{ $port->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $port->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.travel-info.check-in.edit', $port) }}" class="p-1 text-navy hover:bg-navy/10 rounded">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('admin.travel-info.check-in.destroy', $port) }}" onsubmit="return confirm('Delete this port?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-rose-600 hover:bg-rose-50 rounded">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-6 text-center text-slate-400">No ports configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @endif

    @if(! $only || $only === 'dangerous-goods')
    <!-- SECTION 2: DANGEROUS GOODS CLASSIFICATIONS -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-lg font-bold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                    <span>2. Dangerous Goods Classifications & Passenger Regulations</span>
                </h2>
                <p class="text-xs text-slate-500">Batteries, power banks, aerosols, and conditional/prohibited items.</p>
            </div>
            <a href="{{ route('admin.travel-info.dangerous-goods.create') }}" class="text-xs font-bold text-navy hover:text-accent">+ Add DG Item</a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50 text-[11px] font-extrabold text-navy uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5">Item Name</th>
                            <th class="px-5 py-3.5">Category</th>
                            <th class="px-5 py-3.5">Carry-On</th>
                            <th class="px-5 py-3.5">Checked</th>
                            <th class="px-5 py-3.5">Approval Required</th>
                            <th class="px-5 py-3.5 text-center">Status</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($dangerousGoods as $dg)
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-5 py-3.5 font-bold text-navy">{{ $dg->item_name }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $dg->category === 'conditional' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">
                                        {{ ucfirst(str_replace('_', ' ', $dg->category)) }}
                                    </span>
                                </td>
                                <td class="px-5 py-3.5">{{ $dg->carry_on }}</td>
                                <td class="px-5 py-3.5">{{ $dg->checked }}</td>
                                <td class="px-5 py-3.5 text-slate-500 max-w-[200px] truncate">{{ $dg->approval_required }}</td>
                                <td class="px-5 py-3.5 text-center">
                                    <form method="POST" action="{{ route('admin.travel-info.dangerous-goods.toggle-active', $dg) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-all {{ $dg->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                            {{ $dg->is_active ? 'Active' : 'Inactive' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.travel-info.dangerous-goods.edit', $dg) }}" class="p-1 text-navy hover:bg-navy/10 rounded">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                        </a>
                                        <form method="POST" action="{{ route('admin.travel-info.dangerous-goods.destroy', $dg) }}" onsubmit="return confirm('Delete this DG item?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-rose-600 hover:bg-rose-50 rounded">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-6 text-center text-slate-400">No dangerous goods items configured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @endif

    @if(! $only || $only === 'cards')
    <!-- SECTION 3: HELPFUL INFORMATION CARDS -->
    <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-200 pb-3">
            <div>
                <h2 class="text-lg font-bold text-navy flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span>3. Helpful Information For Your Journey Cards (Hub)</span>
                </h2>
                <p class="text-xs text-slate-500">Cards shown on the main /travel-info hub page directing passengers to baggage, pregnant flyer, and check-in pages.</p>
            </div>
            <a href="{{ route('admin.travel-info.cards.create') }}" class="text-xs font-bold text-navy hover:text-accent">+ Add Card</a>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            @forelse($travelCards as $card)
                <div class="naoero-card bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between shadow-sm hover:shadow transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <span class="w-7 h-7 rounded-full bg-navy/10 text-navy flex items-center justify-center text-xs font-bold">#{{ $card->sort_order }}</span>
                            <form method="POST" action="{{ route('admin.travel-info.cards.toggle-active', $card) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="px-1.5 py-0.5 rounded-full text-[10px] font-bold cursor-pointer {{ $card->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $card->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </form>
                        </div>
                        <h4 class="font-bold text-navy text-xs mb-1 line-clamp-2">{{ $card->title }}</h4>
                        <p class="text-[11px] text-slate-500 line-clamp-2">{{ $card->description }}</p>
                        @if($card->route_url)
                            <span class="inline-block text-[10px] text-blue-600 font-mono mt-2 truncate max-w-full">{{ $card->route_url }}</span>
                        @endif
                    </div>
                    <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-end gap-1">
                        <a href="{{ route('admin.travel-info.cards.edit', $card) }}" class="p-1 text-navy hover:bg-navy/10 rounded">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </a>
                        <form method="POST" action="{{ route('admin.travel-info.cards.destroy', $card) }}" onsubmit="return confirm('Delete this card?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 text-rose-600 hover:bg-rose-50 rounded">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full p-4 text-center text-slate-400 bg-white rounded-xl">No travel info cards.</div>
            @endforelse
        </div>
    </div>
    @endif

</div>
@endsection
