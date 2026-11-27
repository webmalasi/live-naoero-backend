@extends('layouts.admin')

@section('title', 'Shop Suppliers')

@section('content')
@php
    $canEdit = auth()->user()->can('shop.inventory.purchasing');
    $field = 'w-full rounded-xl border border-slate-200 px-3 py-2 text-sm';
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ editing: null }">
    @include('admin.inventory._nav', [
        'crumb' => 'Suppliers',
        'title' => 'Suppliers',
        'subtitle' => 'Who the shop buys merchandise from. Purchase orders are sent to them and received into stock.',
    ])

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 naoero-card overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Supplier</th><th class="px-4 py-3 text-left">Contact</th><th class="px-4 py-3 text-right">Orders</th><th class="px-4 py-3 text-left">Lead time</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($suppliers as $s)
                        <tr class="{{ $s->is_active ? '' : 'opacity-60' }}">
                            <td class="px-4 py-2.5"><div class="font-bold text-navy">{{ $s->name }}</div>@if (! $s->is_active)<div class="text-[11px] text-slate-400">Not in use</div>@endif
                                @if ($s->website)<a href="{{ $s->website }}" target="_blank" rel="noopener" class="text-[11px] text-sky-700 hover:underline">{{ parse_url($s->website, PHP_URL_HOST) }}</a>@endif</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $s->contact_name }}<div>{{ $s->email }}</div><div>{{ $s->phone }}</div></td>
                            <td class="px-4 py-2.5 text-right text-xs"><a href="{{ route('admin.inventory.purchase-orders.index', ['supplier' => $s->id]) }}" class="font-bold text-navy hover:underline">{{ $s->orders }}</a>@if ($s->open_orders)<div class="text-amber-700">{{ $s->open_orders }} open</div>@endif</td>
                            <td class="px-4 py-2.5 text-xs text-slate-600">{{ $s->lead_time_days !== null ? $s->lead_time_days.' days' : '—' }}</td>
                            <td class="px-4 py-2.5 text-right">@if ($canEdit)<button type="button" @click="editing = @js($s)" class="text-xs font-bold text-navy hover:underline">Edit</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-slate-400">No suppliers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($canEdit)
            <form method="POST" :action="editing ? '{{ url('admin/inventory/suppliers') }}/' + editing.id : '{{ route('admin.inventory.suppliers.store') }}'" class="naoero-card p-5 space-y-3 self-start">
                @csrf
                <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>
                <h2 class="font-extrabold text-navy text-sm" x-text="editing ? 'Edit ' + editing.name : 'Add a supplier'"></h2>
                <input name="name" required maxlength="120" placeholder="Name" class="{{ $field }}" :value="editing?.name ?? ''">
                <input name="contact_name" maxlength="120" placeholder="Contact person" class="{{ $field }}" :value="editing?.contact_name ?? ''">
                <div class="grid grid-cols-2 gap-2">
                    <input name="email" type="email" placeholder="Email" class="{{ $field }}" :value="editing?.email ?? ''">
                    <input name="phone" maxlength="40" placeholder="Phone" class="{{ $field }}" :value="editing?.phone ?? ''">
                </div>
                <input name="website" type="url" placeholder="https://" class="{{ $field }}" :value="editing?.website ?? ''">
                <textarea name="address" rows="2" placeholder="Address" class="{{ $field }}" x-text="editing?.address ?? ''"></textarea>
                <input name="lead_time_days" type="number" min="0" max="365" placeholder="Lead time (days)" class="{{ $field }}" :value="editing?.lead_time_days ?? ''">
                <textarea name="notes" rows="2" placeholder="Notes: minimum order, payment terms…" class="{{ $field }}" x-text="editing?.notes ?? ''"></textarea>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="checkbox" name="is_active" value="1" :checked="editing ? editing.is_active : true"> In use</label>
                <div class="flex gap-2">
                    <button class="flex-1 px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold" x-text="editing ? 'Save supplier' : 'Add supplier'"></button>
                    <button type="button" x-show="editing" @click="editing = null" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Cancel</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
