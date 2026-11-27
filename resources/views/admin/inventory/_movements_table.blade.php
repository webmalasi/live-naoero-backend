{{-- Rows of the stock ledger (shop_inventory_movements, with staff_name). --}}
@php
    $when = fn ($v) => $v ? \Carbon\Carbon::parse($v)->timezone(config('app.timezone'))->format('j M Y, H:i') : '—';
    $signed = fn (int $n) => $n > 0 ? '+'.$n : (string) $n;
    $typeClass = [
        'opening' => 'bg-slate-100 text-slate-700', 'receipt' => 'bg-emerald-100 text-emerald-800', 'adjustment' => 'bg-sky-100 text-sky-800',
        'count' => 'bg-indigo-100 text-indigo-800', 'reserve' => 'bg-amber-100 text-amber-800', 'release' => 'bg-slate-100 text-slate-600',
        'sale' => 'bg-navy/10 text-navy', 'return' => 'bg-purple-100 text-purple-800',
    ];
    $reasons = \App\Services\Shop\Inventory::REASONS;
    $types = \App\Services\Shop\Inventory::TYPES;
@endphp
<div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
            <tr>
                <th class="px-4 py-3 text-left">When</th>
                <th class="px-4 py-3 text-left">Item</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-right">On hand</th>
                <th class="px-4 py-3 text-right">Held</th>
                <th class="px-4 py-3 text-left">Why / reference</th>
                <th class="px-4 py-3 text-left">By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($movements as $m)
                <tr class="hover:bg-slate-50/70">
                    <td class="px-4 py-2.5 text-xs text-slate-600 whitespace-nowrap">{{ $when($m->created_at) }}</td>
                    <td class="px-4 py-2.5 text-xs">
                        @if ($m->shop_product_id)
                            <a href="{{ route('admin.inventory.product', $m->shop_product_id) }}" class="font-bold text-slate-700 hover:underline">{{ \App\Services\Shop\Inventory::label($m->product_title, $m->variant_title) }}</a>
                        @else
                            <span class="font-bold text-slate-700">{{ \App\Services\Shop\Inventory::label($m->product_title, $m->variant_title) }}</span>
                        @endif
                        <div class="font-mono text-slate-400">{{ $m->sku }}</div>
                    </td>
                    <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $typeClass[$m->type] ?? '' }}">{{ $types[$m->type] ?? $m->type }}</span></td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        @if ($m->on_hand_change)<span class="font-extrabold {{ $m->on_hand_change > 0 ? 'text-emerald-700' : 'text-rose-700' }}">{{ $signed($m->on_hand_change) }}</span>@endif
                        <span class="text-xs text-slate-400"> → {{ $m->on_hand_after }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        @if ($m->reserved_change)<span class="font-bold text-amber-700">{{ $signed($m->reserved_change) }}</span>@endif
                        <span class="text-xs text-slate-400"> → {{ $m->reserved_after }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-600 max-w-xs">
                        @if ($m->reason)<span class="font-semibold">{{ $reasons[$m->reason] ?? $m->reason }}</span>@endif
                        @if ($m->reference_label)
                            @php
                                $href = match ($m->reference_type) {
                                    'order' => \Illuminate\Support\Str::isUuid($m->reference_id) ? route('admin.sales.show', $m->reference_id) : null,
                                    'purchase_order' => route('admin.inventory.purchase-orders.show', (int) $m->reference_id),
                                    'stock_count' => route('admin.inventory.counts.show', (int) $m->reference_id),
                                    'return' => \Illuminate\Support\Str::isUuid($m->reference_id) ? route('admin.sales.returns.show', $m->reference_id) : null,
                                    default => null,
                                };
                            @endphp
                            @if ($href)<a href="{{ $href }}" class="font-mono font-bold text-navy hover:underline">{{ $m->reference_label }}</a>@else<span class="font-mono">{{ $m->reference_label }}</span>@endif
                        @endif
                        @if ($m->unit_cost !== null)<span class="text-slate-400"> @ ${{ number_format((float) $m->unit_cost, 2) }}</span>@endif
                        @if ($m->note)<div class="text-slate-500">{{ $m->note }}</div>@endif
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-500">{{ $m->staff_name ?? ($m->type === 'reserve' || $m->type === 'sale' ? 'Customer' : 'System') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-12 text-center text-sm text-slate-400">No stock movements.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
