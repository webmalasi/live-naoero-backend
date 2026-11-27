@extends('layouts.admin')

@section('title', 'Shop Sales & Orders')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $s = $stats;
    $outcomes = $s['attempts30'];
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Shop Sales & Orders',
        'title' => 'Shop Sales & Orders',
        'subtitle' => 'Gift cards and merchandise bought on the website and in the app, paid by card through NAB Gateway (3-D Secure).',
    ])

    {{-- Headline numbers --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="naoero-card p-5 bg-gradient-to-br from-navy to-navy-900 text-white border-0">
            <span class="text-[11px] font-bold uppercase tracking-wider text-accent">Net revenue</span>
            <p class="text-3xl font-extrabold mt-1.5">{{ $money($s['net']) }}</p>
            <p class="text-[11px] text-white/70 mt-1">{{ $money($s['gross']) }} paid · {{ $money($s['refunded']) }} refunded</p>
            <div class="mt-3 flex gap-3 text-[11px] text-white/85">
                @foreach (['website' => 'Website', 'app' => 'App'] as $ch => $chLabel)
                    <span>{{ $chLabel }}: <b>{{ $money($s['byChannel'][$ch]->revenue ?? 0) }}</b> · {{ $s['byChannel'][$ch]->orders ?? 0 }}</span>
                @endforeach
            </div>
        </div>
        <div class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Today · last 30 days</span>
            <p class="text-2xl font-extrabold text-navy mt-1.5">{{ $money($s['today']) }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ $money($s['last30']) }} in 30 days · avg order {{ $money($s['averageOrder']) }}</p>
        </div>
        <div class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Card approval (30 days)</span>
            <p class="text-2xl font-extrabold {{ ($s['approvalRate'] ?? 100) >= 80 ? 'text-emerald-600' : 'text-amber-600' }} mt-1.5">{{ $s['approvalRate'] === null ? '—' : $s['approvalRate'].'%' }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ $outcomes['paid'] ?? 0 }} approved · {{ $s['declined30'] }} declined</p>
        </div>
        <div class="naoero-card p-5">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Gift cards issued</span>
            <p class="text-2xl font-extrabold text-navy mt-1.5">{{ $s['giftCardsIssued'] }}</p>
            <p class="text-[11px] text-slate-400 mt-1">{{ $money($s['giftCardsValue']) }} unspent balance</p>
        </div>
    </div>

    {{-- Things needing attention --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        @foreach ([
            ['To ship', $s['toShip'], route('admin.sales.index', ['shipping' => 'to_ship']), 'text-indigo-600', 'Paid merchandise'],
            ['Under review', $s['underReview'], route('admin.sales.index', ['status' => 'payment_review']), 'text-amber-600', 'Fraud screening / late pay'],
            ['Awaiting payment', $s['awaitingPayment'], route('admin.sales.index', ['status' => 'pending_payment']), 'text-yellow-600', 'Held 30 minutes'],
            ['Refunds', $s['refundCount'], route('admin.sales.payments'), 'text-purple-600', $s['failedRefunds'] ? $s['failedRefunds'].' failed' : 'All succeeded'],
            ['Paid orders', $s['paidCount'], route('admin.sales.index', ['status' => 'paid']), 'text-emerald-600', 'All time'],
        ] as [$label, $value, $href, $color, $hint])
            <a href="{{ $href }}" class="naoero-card p-4 hover:border-slate-300">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 block">{{ $label }}</span>
                <p class="text-xl font-extrabold {{ $color }} mt-1">{{ $value }}</p>
                <p class="text-[11px] text-slate-400">{{ $hint }}</p>
            </a>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="grid lg:grid-cols-3 gap-4">
        <div class="naoero-card p-5 lg:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-extrabold text-navy">Revenue, last 30 days</h2>
                <span class="text-[11px] text-slate-400">Paid orders (bars) · declined cards (line)</span>
            </div>
            <div class="h-64"><canvas id="salesChart"></canvas></div>
        </div>
        <div class="naoero-card p-5">
            <h2 class="text-sm font-extrabold text-navy mb-3">Payment outcomes, 30 days</h2>
            <div class="h-40"><canvas id="outcomeChart"></canvas></div>
            <div class="mt-4 space-y-1.5">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Best sellers</h3>
                @forelse ($s['topProducts'] as $p)
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-slate-700 truncate pr-2">{{ $p->title }} <span class="text-slate-400">× {{ $p->units }}</span></span>
                        <span class="font-bold text-navy">{{ $money($p->revenue) }}</span>
                    </div>
                @empty
                    <p class="text-xs text-slate-400">No sales yet.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            <a href="{{ route('admin.sales.index', array_filter([...$filters, 'status' => null])) }}"
               class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ empty($filters['status']) ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">All</a>
            @foreach ($statuses as $key => $label)
                <a href="{{ route('admin.sales.index', array_filter([...$filters, 'status' => $key])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ ($filters['status'] ?? '') === $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2.5">
            @if (! empty($filters['status']))<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Order number, email, name, NAB id or gift card code"
                   class="md:col-span-1 rounded-xl border border-slate-200 px-3 py-2 text-sm focus:border-accent focus:ring-accent">
            <select name="channel" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Website & app</option>
                <option value="website" @selected(($filters['channel'] ?? '') === 'website')>Website only</option>
                <option value="app" @selected(($filters['channel'] ?? '') === 'app')>App only</option>
            </select>
            <select name="type" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">All items</option>
                <option value="gift_card" @selected(($filters['type'] ?? '') === 'gift_card')>With gift cards</option>
                <option value="merch" @selected(($filters['type'] ?? '') === 'merch')>With merchandise</option>
            </select>
            <select name="shipping" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="">Any shipping</option>
                <option value="to_ship" @selected(($filters['shipping'] ?? '') === 'to_ship')>To ship</option>
                <option value="shipped" @selected(($filters['shipping'] ?? '') === 'shipped')>Shipped</option>
                <option value="delivered" @selected(($filters['shipping'] ?? '') === 'delivered')>Delivered</option>
            </select>
            <div class="flex gap-2 md:col-span-2">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="flex-1 rounded-xl border border-slate-200 px-2 py-2 text-sm">
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="flex-1 rounded-xl border border-slate-200 px-2 py-2 text-sm">
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Apply filters</button>
            <a href="{{ route('admin.sales.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Reset</a>
            @can('shop.sales.export')
                <a href="{{ route('admin.sales.index', [...request()->query(), 'export' => 'csv']) }}" class="ml-auto px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">⇩ Export CSV</a>
            @endcan
        </div>
    </form>

    {{-- Orders --}}
    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold">Order</th>
                        <th class="px-4 py-3 text-left font-bold">Customer</th>
                        <th class="px-4 py-3 text-left font-bold">Items</th>
                        <th class="px-4 py-3 text-right font-bold">Total</th>
                        <th class="px-4 py-3 text-left font-bold">Payment</th>
                        <th class="px-4 py-3 text-left font-bold">Fulfilment</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($orders as $o)
                        @php
                            $lines = $items[$o->id] ?? collect();
                            $attempt = $lastAttempts[$o->id] ?? null;
                            $giftCount = $lines->where('type', 'gift_card')->sum('quantity');
                            $payment = $o->payment_details ? json_decode($o->payment_details, true) : null;
                        @endphp
                        <tr class="hover:bg-slate-50/70 cursor-pointer" onclick="window.location='{{ route('admin.sales.show', $o->id) }}'">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.sales.show', $o->id) }}" class="font-extrabold text-navy tracking-wide">{{ $o->order_number }}</a>
                                <div class="text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($o->created_at)->format('j M Y, H:i') }}</div>
                                @include('admin.sales._channel', ['channel' => $o->channel ?? 'app'])
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-semibold text-slate-800">{{ trim($o->first_name.' '.$o->last_name) ?: '—' }}</div>
                                <div class="text-[11px] text-slate-400">{{ $o->contact_email }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600 max-w-xs">
                                {{ $lines->map(fn ($i) => $i->quantity.' × '.\App\Services\Shop\Inventory::label($i->title, $i->variant_title ?? null))->implode(', ') }}
                                @if ($giftCount)<div class="text-[11px] text-accent-hover font-bold mt-0.5">🎁 {{ $giftCount }} gift card{{ $giftCount > 1 ? 's' : '' }}</div>@endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-extrabold text-navy">{{ $money($o->total) }}</div>
                                @if ((float) $o->refunded_amount > 0)<div class="text-[11px] text-purple-600 font-bold">−{{ $money($o->refunded_amount) }}</div>@endif
                            </td>
                            <td class="px-4 py-3 space-y-1">
                                @include('admin.sales._tag', ['tag' => $o->status])
                                @if ($payment)
                                    <div class="text-[11px] text-slate-400">{{ $payment['brand'] ?? 'Card' }} {{ isset($payment['last4']) ? '•••• '.$payment['last4'] : '' }}</div>
                                @elseif ($attempt && $o->status === 'pending_payment')
                                    <div class="text-[11px] text-slate-400">Last try: {{ $attempt->status === 'started' ? 'in progress' : $attempt->status }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($o->shipping_status)
                                    @include('admin.sales._tag', ['tag' => $o->shipping_status])
                                @elseif ($giftCount && in_array($o->status, ['paid', 'partially_refunded']))
                                    @include('admin.sales._tag', ['tag' => 'success', 'text' => 'Gift cards sent'])
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">No shop orders match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $orders->links() }}</div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) return;
    const chart = @json($chart);
    new Chart(document.getElementById('salesChart'), {
        data: {
            labels: chart.labels,
            datasets: [
                { type: 'bar', label: 'Revenue (AUD)', data: chart.revenue, backgroundColor: '#012169', borderRadius: 6, yAxisID: 'y' },
                { type: 'line', label: 'Declined cards', data: chart.declined, borderColor: '#E11D48', backgroundColor: '#E11D48', tension: .35, pointRadius: 2, yAxisID: 'y1' },
            ],
        },
        options: {
            maintainAspectRatio: false,
            plugins: { legend: { labels: { boxWidth: 10, font: { family: 'Manrope', size: 11 } } } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + v }, grid: { color: '#F1F5F9' } },
                y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { precision: 0 } },
                x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
            },
        },
    });
    const o = @json($outcomes);
    new Chart(document.getElementById('outcomeChart'), {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Declined', 'Under review', 'Failed', 'Abandoned / open'],
            datasets: [{
                data: [o.paid || 0, o.declined || 0, o.review || 0, o.failed || 0, (o.started || 0) + (o.cancelled || 0)],
                backgroundColor: ['#10B981', '#E11D48', '#F59E0B', '#DC2626', '#94A3B8'],
                borderWidth: 0,
            }],
        },
        options: { maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { family: 'Manrope', size: 11 } } } } },
    });
});
</script>
@endsection
