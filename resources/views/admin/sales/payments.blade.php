@extends('layouts.admin')

@section('title', 'Shop Payments & Refunds')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $current = request('outcome');
    $tabs = ['' => 'All attempts', 'paid' => 'Success', 'declined' => 'Declined', 'review' => 'Under review', 'failed' => 'Failed', 'abandoned' => 'Abandoned / open'];
    $count = fn ($k) => match ($k) {
        '' => array_sum($counts->all()),
        'abandoned' => ($counts['started'] ?? 0) + ($counts['cancelled'] ?? 0),
        default => $counts[$k] ?? 0,
    };
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    @include('admin.sales._nav', [
        'crumb' => 'Payments & Refunds',
        'title' => 'Payments & Refunds',
        'subtitle' => 'Every card payment attempt through NAB with its outcome, and every refund. Counts cover the last 30 days.',
    ])

    <form method="GET" class="naoero-card p-4 space-y-3">
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('admin.sales.payments', array_filter(['outcome' => $key ?: null, 'search' => request('search')])) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap {{ (string) $current === (string) $key ? 'bg-navy text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }} <span class="opacity-60">{{ $count($key) }}</span>
                </a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($current)<input type="hidden" name="outcome" value="{{ $current }}">@endif
            <input name="search" value="{{ request('search') }}" placeholder="Order number, customer email or NAB transaction id" class="flex-1 min-w-[16rem] rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <button class="px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Search</button>
            @can('shop.sales.export')
                <a href="{{ route('admin.sales.payments', [...request()->query(), 'export' => 'csv']) }}" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">⇩ Export CSV</a>
            @endcan
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Order</th>
                        <th class="px-4 py-3 text-left">Customer</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-left">Outcome</th>
                        <th class="px-4 py-3 text-left">Card</th>
                        <th class="px-4 py-3 text-left">Reason / NAB id</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($attempts as $a)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">{{ $when($a->created_at) }}</td>
                            <td class="px-4 py-3"><a href="{{ route('admin.sales.show', $a->shop_order_id) }}" class="font-extrabold text-navy">{{ $a->order_number }}</a></td>
                            <td class="px-4 py-3 text-xs"><div class="font-semibold text-slate-700">{{ trim($a->first_name.' '.$a->last_name) }}</div><div class="text-slate-400">@include('admin.customers._identity', ['email' => $a->customer_main_email ?? null, 'login' => $a->zenith_login])</div></td>
                            <td class="px-4 py-3 text-right font-bold text-navy">{{ $money($a->amount ?? $a->order_total) }}</td>
                            <td class="px-4 py-3">@include('admin.sales._tag', ['tag' => \App\Http\Controllers\Admin\ShopSalesController::attemptOutcome($a)])</td>
                            <td class="px-4 py-3 text-xs text-slate-600">{{ trim(($a->card_brand ?? '').' '.($a->card_last4 ? '•••• '.$a->card_last4 : '')) ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs text-slate-500">{{ $a->reason ?? '' }}<div class="font-mono text-slate-400">{{ $a->nab_transaction_id }}</div></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-14 text-center text-sm text-slate-400">No payment attempts match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $attempts->links() }}</div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Latest refunds</div>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                @forelse ($refunds as $r)
                    <tr>
                        <td class="px-4 py-2.5 text-xs text-slate-600 whitespace-nowrap">{{ $when($r->created_at) }}</td>
                        <td class="px-4 py-2.5"><a href="{{ route('admin.sales.show', $r->shop_order_id) }}" class="font-extrabold text-navy">{{ $r->order_number }}</a></td>
                        <td class="px-4 py-2.5 font-extrabold text-purple-700">{{ $money($r->amount) }}</td>
                        <td class="px-4 py-2.5">@include('admin.sales._tag', ['tag' => $r->status])</td>
                        <td class="px-4 py-2.5 text-xs text-slate-600">{{ $r->reason }}@if ($r->failure_reason)<div class="text-rose-600">{{ $r->failure_reason }}</div>@endif</td>
                        <td class="px-4 py-2.5 text-xs text-slate-500">{{ $r->staff_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-8 text-center text-sm text-slate-400">No refunds yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
