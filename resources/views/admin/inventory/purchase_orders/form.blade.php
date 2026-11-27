@extends('layouts.admin')

@section('title', $po ? 'Edit '.$po->po_number : 'New Purchase Order')

@section('content')
@php
    $field = 'w-full rounded-xl border border-slate-200 px-3 py-2 text-sm';
    $initial = old('lines', $lines ?: [['variant_id' => '', 'quantity' => '', 'unit_cost' => '']]);
@endphp
<div class="max-w-6xl mx-auto space-y-6"
     x-data="{
        catalogue: @js($catalogue),
        lines: @js(array_values($initial)),
        add() { this.lines.push({ variant_id: '', quantity: '', unit_cost: '' }) },
        cost(line) { const v = this.catalogue.find(c => c.id == line.variant_id); if (v && (line.unit_cost === '' || line.unit_cost === null) && v.cost !== null) line.unit_cost = v.cost },
        total() { return this.lines.reduce((s, l) => s + (+l.quantity || 0) * (+l.unit_cost || 0), 0) + (+$refs.shipping.value || 0) },
     }">
    @include('admin.inventory._nav', [
        'crumb' => 'Purchase Orders',
        'title' => $po ? 'Edit '.$po->po_number : 'New purchase order',
        'subtitle' => 'Saved as a draft. Mark it as ordered when it goes to the supplier, then receive what arrives.',
    ])

    @if ($suggestions)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
            {{ count($suggestions) }} item(s) are at or below their reorder point.
            @if (! $lines)<a href="{{ route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']) }}" class="font-bold underline">Fill this order with them</a>@else Their suggested quantities are filled in below.@endif
        </div>
    @endif

    <form method="POST" action="{{ $po ? route('admin.inventory.purchase-orders.update', $po->id) : route('admin.inventory.purchase-orders.store') }}" class="space-y-6">
        @csrf
        @if ($po) @method('PUT') @endif
        <div class="naoero-card p-5 grid md:grid-cols-4 gap-3">
            <label class="md:col-span-2 text-xs font-bold text-slate-600">Supplier
                <select name="supplier_id" class="{{ $field }} mt-1">
                    <option value="">Choose later</option>
                    @foreach ($suppliers as $s)<option value="{{ $s->id }}" @selected((int) old('supplier_id', $po->supplier_id ?? 0) === $s->id)>{{ $s->name }}</option>@endforeach
                </select>
                @if ($suppliers->isEmpty())<a href="{{ route('admin.inventory.suppliers') }}" class="text-[11px] text-navy underline">Add a supplier first</a>@endif
            </label>
            <label class="text-xs font-bold text-slate-600">Supplier reference
                <input name="supplier_reference" value="{{ old('supplier_reference', $po->supplier_reference ?? '') }}" maxlength="80" placeholder="Their quote or invoice no." class="{{ $field }} mt-1">
            </label>
            <label class="text-xs font-bold text-slate-600">Expected delivery
                <input type="date" name="expected_at" value="{{ old('expected_at', $po->expected_at ?? '') }}" class="{{ $field }} mt-1">
            </label>
            <label class="md:col-span-3 text-xs font-bold text-slate-600">Notes
                <input name="notes" value="{{ old('notes', $po->notes ?? '') }}" maxlength="1000" class="{{ $field }} mt-1">
            </label>
            <label class="text-xs font-bold text-slate-600">Freight / shipping
                <input x-ref="shipping" type="number" step="0.01" min="0" name="shipping_cost" value="{{ old('shipping_cost', $po->shipping_cost ?? 0) }}" class="{{ $field }} mt-1">
            </label>
        </div>

        <div class="naoero-card overflow-hidden">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr><th class="px-4 py-3 text-left">Item</th><th class="px-4 py-3 text-right w-28">Quantity</th><th class="px-4 py-3 text-right w-32">Unit cost</th><th class="px-4 py-3 text-right w-28">Line</th><th class="w-10"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(line, i) in lines" :key="i">
                        <tr>
                            <td class="px-4 py-2">
                                <select :name="`lines[${i}][variant_id]`" x-model="line.variant_id" @change="cost(line)" class="{{ $field }}">
                                    <option value="">Choose an item…</option>
                                    <template x-for="c in catalogue" :key="c.id">
                                        <option :value="c.id" :selected="c.id == line.variant_id" x-text="c.label + (c.available === null ? '' : ` (${c.available} available)`)"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-4 py-2"><input type="number" min="0" :name="`lines[${i}][quantity]`" x-model="line.quantity" class="{{ $field }} text-right"></td>
                            <td class="px-4 py-2"><input type="number" min="0" step="0.01" :name="`lines[${i}][unit_cost]`" x-model="line.unit_cost" class="{{ $field }} text-right"></td>
                            <td class="px-4 py-2 text-right font-bold text-slate-700" x-text="'$' + ((+line.quantity || 0) * (+line.unit_cost || 0)).toFixed(2)"></td>
                            <td class="px-2"><button type="button" @click="lines.splice(i, 1)" class="text-rose-500 font-bold" title="Remove">×</button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div class="px-4 py-3 border-t border-slate-100 flex items-center justify-between">
                <button type="button" @click="add()" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-navy">+ Add item</button>
                <div class="text-sm text-slate-600">Total with freight <strong class="text-navy text-lg" x-text="'$' + total().toFixed(2)"></strong></div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ $po ? route('admin.inventory.purchase-orders.show', $po->id) : route('admin.inventory.purchase-orders.index') }}" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600">Cancel</a>
            <button class="px-5 py-2 rounded-xl bg-navy text-white text-xs font-bold">Save draft</button>
        </div>
    </form>
</div>
@endsection
