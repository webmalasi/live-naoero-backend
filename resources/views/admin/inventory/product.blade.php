@extends('layouts.admin')

@section('title', 'Stock · '.$product->title)

@section('content')
@use('App\Services\Shop\Inventory')
@php
    $options = old('options', $product->options ?: []);
    // Three rows to type into, filled with the product's options.
    $rows = array_pad(array_map(fn ($o) => ['name' => $o['name'] ?? '', 'values' => is_array($o['values'] ?? null) ? implode(', ', $o['values']) : ($o['values'] ?? '')], $options), 3, ['name' => '', 'values' => '']);
    $active = $variants->where('is_active', true);
    $canManage = auth()->user()->can('shop.inventory.manage');
    $input = 'w-full rounded-lg border border-slate-200 px-2 py-1 text-xs';
@endphp
<div class="max-w-7xl mx-auto space-y-6" x-data="{ adjust: null }">
    @include('admin.inventory._nav', [
        'crumb' => $product->title,
        'title' => $product->title,
        'subtitle' => $product->category.' · '.$active->count().' active variant(s) · '.$sold30.' sold in the last 30 days'.($waiting ? " · {$waiting} customer(s) waiting for it" : ''),
    ])

    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.shop.edit', $product) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">Edit product details</a>
        <a href="{{ route('admin.inventory.movements', ['search' => $product->title]) }}" class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white text-xs font-bold text-slate-700">Full history</a>
        @if (! $product->is_active)<span class="px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-500">This product is switched off in the shop</span>@endif
    </div>

    <div class="grid lg:grid-cols-3 gap-6">
        <form method="POST" action="{{ route('admin.inventory.product.options', $product) }}" class="naoero-card p-5 space-y-3">
            @csrf
            <div>
                <h2 class="font-extrabold text-navy text-sm">Options</h2>
                <p class="text-xs text-slate-500 mt-1">Sizes, colours… Every combination becomes a variant with its own SKU and stock. Existing stock is kept; combinations you remove are switched off, never deleted.</p>
            </div>
            @foreach ($rows as $i => $row)
                <div class="grid grid-cols-3 gap-2">
                    <input name="options[{{ $i }}][name]" value="{{ $row['name'] }}" placeholder="{{ ['Size', 'Colour', 'Style'][$i] }}" maxlength="30" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" @disabled(! $canManage)>
                    <input name="options[{{ $i }}][values]" value="{{ $row['values'] }}" placeholder="{{ ['S, M, L, XL', 'Navy, White', 'Classic, Slim'][$i] }}" class="col-span-2 rounded-xl border border-slate-200 px-3 py-2 text-sm" @disabled(! $canManage)>
                </div>
            @endforeach
            <p class="text-[11px] text-slate-400">Separate values with commas. Leave every row empty for a product with no options.</p>
            @if ($canManage)
                <button class="w-full px-4 py-2 rounded-xl bg-navy text-white text-xs font-bold" onclick="return confirm('Save the options and create the variants?')">Save options &amp; variants</button>
            @endif
        </form>

        <div class="lg:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3 content-start">
            @foreach ([
                ['On hand', $active->where('track_inventory', true)->sum('on_hand')],
                ['Held for orders', $active->sum('reserved')],
                ['Available', $active->where('track_inventory', true)->sum(fn ($v) => Inventory::available($v))],
                ['Value at cost', '$'.number_format($active->sum(fn ($v) => (int) $v->on_hand * (float) ($v->cost_price ?? 0)), 2)],
            ] as [$label, $value])
                <div class="naoero-card p-4">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $label }}</div>
                    <div class="text-xl font-extrabold text-navy mt-1">{{ $value }}</div>
                </div>
            @endforeach
            <div class="col-span-2 md:col-span-4 naoero-card p-4 text-xs text-slate-600 space-y-1">
                <div><strong>On hand</strong> is what is on the shelf. <strong>Held</strong> is taken by orders awaiting payment (released after {{ config('naoero.shop.payment_window_minutes', 30) }} minutes if unpaid). Customers can buy what is <strong>available</strong>.</div>
                <div>Low-stock alerts start at {{ config('naoero.shop.low_stock_threshold', 5) }} available unless a variant has its own number. The app shows "Only N left" from {{ Inventory::SHOW_STOCK_LEFT_BELOW }}.</div>
            </div>
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Variants</div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-3 py-2.5 text-left">Variant</th>
                        <th class="px-3 py-2.5 text-left">SKU / barcode</th>
                        <th class="px-3 py-2.5 text-right">Stock</th>
                        <th class="px-3 py-2.5 text-left">Price / cost</th>
                        <th class="px-3 py-2.5 text-left">Low at / reorder</th>
                        <th class="px-3 py-2.5 text-left">Location</th>
                        <th class="px-3 py-2.5 text-left">Tracked / on</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($variants as $v)
                        @php $f = "variant-{$v->id}"; @endphp
                        <tr class="align-top {{ $v->is_active ? '' : 'bg-slate-50 opacity-70' }}">
                            <td class="px-3 py-2.5">
                                <div class="font-bold text-navy">{{ $v->title ?? 'Standard' }}</div>
                                @include('admin.inventory._status', ['status' => Inventory::status($v)])
                            </td>
                            <td class="px-3 py-2.5 space-y-1 min-w-[9rem]">
                                <input form="{{ $f }}" name="sku" value="{{ $v->sku }}" required class="{{ $input }} font-mono" @disabled(! $canManage)>
                                <input form="{{ $f }}" name="barcode" value="{{ $v->barcode }}" placeholder="Barcode" class="{{ $input }}" @disabled(! $canManage)>
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap text-xs">
                                @if ($v->track_inventory)
                                    <div><span class="text-slate-400">on hand</span> <strong class="text-slate-700">{{ $v->on_hand }}</strong></div>
                                    @if ($v->reserved)<div><span class="text-slate-400">held</span> <strong class="text-amber-700">{{ $v->reserved }}</strong></div>@endif
                                    <div><span class="text-slate-400">available</span> <strong class="text-navy text-sm">{{ Inventory::available($v) }}</strong></div>
                                @else
                                    <span class="text-slate-400">never runs out</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 space-y-1 min-w-[7rem]">
                                <input form="{{ $f }}" name="price" value="{{ $v->price }}" type="number" step="0.01" min="0" placeholder="{{ number_format((float) $product->price, 2) }}" title="Empty: the product's price" class="{{ $input }}" @disabled(! $canManage)>
                                <input form="{{ $f }}" name="cost_price" value="{{ $v->cost_price }}" type="number" step="0.01" min="0" placeholder="Cost" title="Average cost of what is on hand (updated by deliveries)" class="{{ $input }}" @disabled(! $canManage)>
                            </td>
                            <td class="px-3 py-2.5 space-y-1 min-w-[7rem]">
                                <input form="{{ $f }}" name="low_stock_threshold" value="{{ $v->low_stock_threshold }}" type="number" min="0" placeholder="Low at {{ config('naoero.shop.low_stock_threshold', 5) }}" class="{{ $input }}" @disabled(! $canManage)>
                                <div class="flex gap-1">
                                    <input form="{{ $f }}" name="reorder_point" value="{{ $v->reorder_point }}" type="number" min="0" placeholder="Point" title="Reorder when available falls to this" class="{{ $input }}" @disabled(! $canManage)>
                                    <input form="{{ $f }}" name="reorder_quantity" value="{{ $v->reorder_quantity }}" type="number" min="1" placeholder="Qty" title="How many to reorder" class="{{ $input }}" @disabled(! $canManage)>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 space-y-1 min-w-[6rem]">
                                <input form="{{ $f }}" name="bin_location" value="{{ $v->bin_location }}" placeholder="Shelf / bin" class="{{ $input }}" @disabled(! $canManage)>
                                <input form="{{ $f }}" name="weight_grams" value="{{ $v->weight_grams }}" type="number" min="0" placeholder="Grams" class="{{ $input }}" @disabled(! $canManage)>
                            </td>
                            <td class="px-3 py-2.5 text-xs space-y-1 whitespace-nowrap">
                                <label class="flex items-center gap-1.5"><input form="{{ $f }}" type="checkbox" name="track_inventory" value="1" @checked($v->track_inventory) @disabled(! $canManage)> Track stock</label>
                                <label class="flex items-center gap-1.5"><input form="{{ $f }}" type="checkbox" name="is_active" value="1" @checked($v->is_active) @disabled(! $canManage)> On sale</label>
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap space-y-1">
                                @if ($canManage)
                                    <form id="{{ $f }}" method="POST" action="{{ route('admin.inventory.variants.update', $v->id) }}">@csrf @method('PATCH')</form>
                                    <button form="{{ $f }}" class="block w-full px-2.5 py-1 rounded-lg bg-navy text-white text-[11px] font-bold">Save</button>
                                    @if ($v->track_inventory && $v->is_active)
                                        <button type="button" class="block w-full px-2.5 py-1 rounded-lg border border-slate-200 text-[11px] font-bold text-navy"
                                                @click="adjust = @js(['id' => $v->id, 'sku' => $v->sku, 'name' => Inventory::label($product->title, $v->title), 'onHand' => (int) $v->on_hand, 'reserved' => (int) $v->reserved])">Adjust</button>
                                    @endif
                                    @if (! $v->is_default || $variants->count() > 1)
                                        <form method="POST" action="{{ route('admin.inventory.variants.delete', $v->id) }}" onsubmit="return confirm('Delete {{ $v->sku }}? With stock or history it is switched off instead.')">
                                            @csrf @method('DELETE')
                                            <button class="block w-full px-2.5 py-1 rounded-lg text-[11px] font-bold text-rose-600 hover:bg-rose-50">Delete</button>
                                        </form>
                                    @endif
                                @endif
                                <a href="{{ route('admin.inventory.movements', ['variant' => $v->id]) }}" class="block px-2.5 py-1 rounded-lg text-[11px] font-bold text-slate-500 hover:bg-slate-100">History</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="naoero-card overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 font-extrabold text-navy text-sm">Latest movements</div>
        @include('admin.inventory._movements_table', ['movements' => $movements])
    </div>

    @if ($canManage)
        @include('admin.inventory._adjust')
    @endif
</div>
@endsection
