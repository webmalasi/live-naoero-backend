{{-- A stock status pill: in_stock, low_stock, out_of_stock, untracked, inactive. --}}
@php
    [$label, $class] = [
        'in_stock' => ['In stock', 'bg-emerald-100 text-emerald-800'],
        'low_stock' => ['Low stock', 'bg-amber-100 text-amber-800'],
        'out_of_stock' => ['Sold out', 'bg-rose-100 text-rose-800'],
        'untracked' => ['Not tracked', 'bg-slate-100 text-slate-600'],
        'inactive' => ['Switched off', 'bg-slate-200 text-slate-500'],
    ][$status] ?? [$status, 'bg-slate-100 text-slate-600'];
@endphp
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide whitespace-nowrap {{ $class }}">{{ $label }}</span>
