@extends('layouts.admin')

@section('title', 'Users Directory')

@section('content')
@php
    $money = fn ($v) => '$'.number_format((float) $v, 2);
    $date = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y') : '—';
    $ago = fn ($v) => $v ? \Carbon\Carbon::parse($v)->diffForHumans() : 'Never';
    $q = request()->query();
@endphp
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1">
                <span>Access & Users</span><span>/</span><span class="text-navy font-bold">Users Directory</span>
            </div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Users Directory</h1>
            <p class="text-sm text-ink-muted mt-0.5">Everyone with a Naoero Airlines account (website and app), with their bookings, shop orders and activity. Staff accounts are under <a href="{{ route('admin.users.index') }}" class="font-bold text-navy underline">Admin Users &amp; Staff</a>.</p>
        </div>
        <a href="{{ route('admin.customers.index', [...$q, 'export' => 'csv']) }}" class="self-start lg:self-auto px-4 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700 shadow-sm">⇩ Export CSV</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-8 gap-3">
        @foreach ([
            ['All users', $stats['total'], [], 'text-navy'],
            ['Active', $stats['active'], ['status' => 'active'], 'text-emerald-600'],
            ['New (30 days)', $stats['new30'], ['segment' => 'new'], 'text-sky-600'],
            ['Signed in (7 days)', $stats['active7'], ['segment' => 'active7'], 'text-indigo-600'],
            ['Have booked flights', $stats['booked'], ['segment' => 'booked'], 'text-navy'],
            ['Shop customers', $stats['shoppers'], ['segment' => 'shoppers'], 'text-purple-600'],
            ['Deletion requested', $stats['removal'], ['status' => 'removal_requested'], 'text-amber-600'],
            ['Disabled', $stats['disabled'], ['status' => 'disabled'], 'text-rose-600'],
        ] as [$label, $value, $params, $color])
            <a href="{{ route('admin.customers.index', $params) }}" class="naoero-card p-3.5 hover:border-slate-300 {{ $params && array_intersect_assoc($params, $q) == $params ? 'ring-2 ring-accent' : '' }}">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block leading-tight">{{ $label }}</span>
                <p class="text-xl font-extrabold {{ $color }} mt-1">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <form method="GET" class="naoero-card p-4 grid grid-cols-1 md:grid-cols-5 gap-2.5">
        <input name="search" value="{{ request('search') }}" placeholder="Name, username, email, phone, PNR or Zenith ref" class="md:col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm">
        <select name="status" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            <option value="">Any status</option>
            @foreach (['active' => 'Active', 'removal_requested' => 'Deletion requested', 'disabled' => 'Disabled'] as $k => $v)
                <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <select name="sort" class="rounded-xl border border-slate-200 px-3 py-2 text-sm">
            @foreach (['newest' => 'Newest first', 'last_login' => 'Last signed in', 'name' => 'Name', 'spend' => 'Shop spend'] as $k => $v)
                <option value="{{ $k }}" @selected(request('sort', 'newest') === $k)>{{ $v }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            @if (request('segment'))<input type="hidden" name="segment" value="{{ request('segment') }}">@endif
            <button class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold">Search</button>
            <a href="{{ route('admin.customers.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Reset</a>
        </div>
    </form>

    <div class="naoero-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">User</th>
                        <th class="px-4 py-3 text-left">Contact</th>
                        <th class="px-4 py-3 text-left">Joined · last sign-in</th>
                        <th class="px-4 py-3 text-center">Flights</th>
                        <th class="px-4 py-3 text-center">Shop</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $c)
                        @php $name = trim(($c->first_name ?? '').' '.($c->last_name ?? '')) ?: $c->zenith_login; @endphp
                        <tr class="hover:bg-slate-50/70 cursor-pointer" onclick="window.location='{{ route('admin.customers.show', $c->id) }}'">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($c->has_avatar)
                                        <img src="{{ route('admin.customers.avatar', $c->id) }}" class="w-10 h-10 rounded-full object-cover" alt="">
                                    @else
                                        <div class="w-10 h-10 rounded-full bg-navy text-accent flex items-center justify-center text-sm font-extrabold">{{ strtoupper(mb_substr($c->first_name ?? $c->zenith_login, 0, 1).mb_substr($c->last_name ?? '', 0, 1)) }}</div>
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.customers.show', $c->id) }}" class="font-extrabold text-navy">{{ trim(($c->civility_code ? ucfirst(strtolower($c->civility_code)).' ' : '').$name) }}</a>
                                        <div class="text-[11px] text-slate-400">Zenith {{ $c->zenith_customer_ref }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-xs"><div class="text-slate-700">@include('admin.customers._identity', ['email' => $c->email, 'login' => $c->zenith_login])</div><div class="text-slate-400">{{ $c->phone ? \App\Services\Support\Phones::display($c->phone) : '—' }}</div></td>
                            <td class="px-4 py-3 text-xs"><div class="text-slate-700">{{ $date($c->created_at) }}</div><div class="text-slate-400">{{ $ago($c->last_login_at) }}</div></td>
                            <td class="px-4 py-3 text-center font-bold text-navy">{{ $c->bookings_count }}</td>
                            <td class="px-4 py-3 text-center"><div class="font-bold text-navy">{{ $c->orders_count }}</div>@if ((float) $c->shop_spend > 0)<div class="text-[11px] text-purple-600 font-bold">{{ $money($c->shop_spend) }}</div>@endif</td>
                            <td class="px-4 py-3">
                                @include('admin.sales._tag', ['tag' => match ($c->status) { 'active' => 'success', 'disabled' => 'declined', default => 'review' }, 'text' => match ($c->status) { 'active' => 'Active', 'disabled' => 'Disabled', default => 'Deletion requested' }])
                                @if ($c->deletion_pending && $c->status !== 'disabled')<div class="mt-1">@include('admin.sales._tag', ['tag' => 'review', 'text' => 'Deletion requested'])</div>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-14 text-center text-sm text-slate-400">No users match.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 border-t border-slate-100">{{ $customers->links() }}</div>
    </div>
</div>
@endsection
