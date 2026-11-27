@extends('layouts.admin')

@section('title', $count->count_number)

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $open = $count->status === 'open';
    $canCount = $open && auth()->user()->can('shop.inventory.count');
    $signed = fn (?int $n) => $n === null ? '—' : ($n > 0 ? '+'.$n : (string) $n);
@endphp
<div class="max-w-6xl mx-auto space-y-6" x-data="{ filter: '' }">
    @include('admin.inventory._nav', [
        'crumb' => 'Stock Counts',
        'title' => $count->count_number.' · '.$count->scope,
        'subtitle' => ($statuses[$count->status] ?? $count->status).' · started '.$when($count->created_at).($count->created_by_name ? ' by '.$count->created_by_name : '')
            .($count->completed_at ? ' · applied '.$when($count->completed_at).($count->completed_by_name ? ' by '.$count->completed_by_name : '') : ''),
    ])

    @if ($count->notes)<div class="naoero-card p-4 text-sm text-slate-600">{{ $count->notes }}</div>@endif

    <form method="POST" action="{{ route('admin.inventory.counts.save', $count->id) }}" class="naoero-card overflow-hidden">
        @csrf
        <div class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center gap-3 justify-between">
            <input x-model="filter" placeholder="Find an item, SKU or barcode…" class="flex-1 min-w-[14rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
            @if ($canCount)
                <div class="flex gap-2">
                    <button name="then" value="save" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-navy">Save progress</button>
                    <button name="then" value="complete" class="px-4 py-2 rounded-xl bg-emerald-600 text-white text-xs font-bold"
                            onclick="return confirm('Apply the count? Every counted item\'s stock becomes what you counted. Blank items are left as they are.')">Apply count</button>
                </div>
            @endif
        </div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-left">Location</th>
                    <th class="px-4 py-3 text-right">{{ $open ? 'System now' : 'System then' }}</th>
                    <th class="px-4 py-3 text-right w-32">Counted</th><th class="px-4 py-3 text-right">Difference</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($items as $i)
                    @php
                        $system = $open ? $i->on_hand_now : $i->on_hand_before;
                        $diff = $open ? ($i->counted === null || $system === null ? null : $i->counted - $system) : $i->variance;
                    @endphp
                    <tr x-show="!filter || @js(mb_strtolower($i->title.' '.$i->sku.' '.$i->barcode)).includes(filter.toLowerCase())">
                        <td class="px-4 py-2.5"><div class="font-bold text-slate-700">{{ $i->title }}</div><div class="text-[11px] font-mono text-slate-400">{{ $i->sku }}@if ($i->barcode) · {{ $i->barcode }}@endif</div></td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $i->bin_location }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-600">{{ $system ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @if ($canCount)
                                <input type="number" min="0" name="counted[{{ $i->id }}]" value="{{ old("counted.{$i->id}", $i->counted) }}" class="w-full rounded-lg border border-slate-200 px-2 py-1 text-sm text-right">
                            @else
                                <span class="font-bold">{{ $i->counted ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-extrabold {{ $diff ? ($diff > 0 ? 'text-emerald-700' : 'text-rose-700') : 'text-slate-400' }}">{{ $signed($diff) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </form>

    @if ($canCount)
        <form method="POST" action="{{ route('admin.inventory.counts.cancel', $count->id) }}" onsubmit="return confirm('Cancel this count? No stock is changed.')">
            @csrf<button class="text-xs font-bold text-rose-600 hover:underline">Cancel this count</button>
        </form>
    @endif
</div>
@endsection
