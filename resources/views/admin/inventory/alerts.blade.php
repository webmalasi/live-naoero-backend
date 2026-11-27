@extends('layouts.admin')

@section('title', 'Back-in-Stock Alerts')

@section('content')
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.inventory._nav', [
        'crumb' => 'Customer Alerts',
        'title' => 'Back-in-Stock Alerts',
        'subtitle' => 'Customers who asked to be told when a sold-out item is back. They get a notice in the app and on the website as soon as stock arrives.',
    ])

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Waiting now, most wanted first</div>
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                <tr><th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-right">Customers waiting</th><th class="px-4 py-3 text-left">Waiting since</th><th class="px-4 py-3"></th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($demand as $d)
                    <tr>
                        <td class="px-4 py-2.5"><span class="font-bold text-navy">{{ \App\Services\Shop\Inventory::label($d->product_title, $d->variant_title) }}</span>
                            <div class="text-[11px] text-slate-400">{{ $d->variant_id ? $d->sku : 'Any size or option' }}</div></td>
                        <td class="px-4 py-2.5 text-right font-extrabold text-purple-700">{{ $d->waiting }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $when($d->since) }}</td>
                        <td class="px-4 py-2.5 text-right"><a href="{{ route('admin.inventory.product', $d->shop_product_id) }}" class="text-xs font-bold text-navy hover:underline">Stock →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-slate-400">Nobody is waiting for anything.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Every alert</div>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @forelse ($recent as $a)
                    <tr>
                        <td class="px-4 py-2.5 text-xs text-slate-600 whitespace-nowrap">{{ $when($a->created_at) }}</td>
                        <td class="px-4 py-2.5 text-xs"><div class="font-semibold text-slate-700">{{ trim($a->first_name.' '.$a->last_name) ?: '—' }}</div><div class="text-slate-400">@include('admin.customers._identity', ['email' => $a->customer_main_email ?? null, 'login' => $a->zenith_login])</div></td>
                        <td class="px-4 py-2.5 text-xs font-bold text-navy">{{ \App\Services\Shop\Inventory::label($a->product_title, $a->variant_title) }}</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ ucfirst($a->channel) }}</td>
                        <td class="px-4 py-2.5 text-xs">
                            @if ($a->notified_at)
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold">Notified {{ $when($a->notified_at) }}</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 font-bold">Waiting</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-10 text-center text-sm text-slate-400">No alerts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-100">{{ $recent->links() }}</div>
    </div>
</div>
@endsection
