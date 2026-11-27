@extends('layouts.admin')

@section('title', 'Stock Counts')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $tag = ['open' => 'bg-amber-100 text-amber-800', 'completed' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-slate-100 text-slate-500'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Stock Counts',
        'title' => 'Stock Counts',
        'subtitle' => 'Count the shelves and correct the system to match. Every difference is written to the movements ledger.',
    ])

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 naoero-card overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Count</th><th class="px-4 py-3 text-left">What</th><th class="px-4 py-3 text-left">Status</th><th class="px-4 py-3 text-right">Counted</th><th class="px-4 py-3 text-right">Differences</th><th class="px-4 py-3 text-left">Started</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($counts as $c)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-2.5"><a href="{{ route('admin.inventory.counts.show', $c->id) }}" class="font-mono font-extrabold text-navy">{{ $c->count_number }}</a></td>
                            <td class="px-4 py-2.5 text-xs text-slate-700">{{ $c->scope }}</td>
                            <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $tag[$c->status] ?? '' }}">{{ $statuses[$c->status] ?? $c->status }}</span></td>
                            <td class="px-4 py-2.5 text-right text-xs"><strong>{{ $c->counted_items }}</strong> / {{ $c->items }}</td>
                            <td class="px-4 py-2.5 text-right text-xs {{ $c->differences ? 'text-rose-700 font-bold' : 'text-slate-400' }}">{{ $c->status === 'completed' ? $c->differences : '—' }}</td>
                            <td class="px-4 py-2.5 text-xs text-slate-500">{{ $when($c->created_at) }}<div>{{ $c->created_by_name }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">No counts yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-slate-100">{{ $counts->links() }}</div>
        </div>

        @can('shop.inventory.count')
            <form method="POST" action="{{ route('admin.inventory.counts.store') }}" class="naoero-card p-5 space-y-3 self-start">
                @csrf
                <h2 class="font-extrabold text-navy text-sm">Start a count</h2>
                <p class="text-xs text-slate-500">The sheet lists every tracked item with what the system expects. Enter what is on the shelf, then apply the count.</p>
                <select name="category" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                    <option value="">All merchandise</option>
                    @foreach ($categories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
                </select>
                <input name="notes" maxlength="500" placeholder="Notes (e.g. year-end count, storeroom B)" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <button class="w-full px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Start count</button>
            </form>
        @endcan
    </div>
</div>
@endsection
