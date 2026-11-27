@php
    $class = ['draft' => 'bg-slate-100 text-slate-700', 'ordered' => 'bg-sky-100 text-sky-800', 'partially_received' => 'bg-amber-100 text-amber-800',
        'received' => 'bg-emerald-100 text-emerald-800', 'cancelled' => 'bg-rose-100 text-rose-700'][$status] ?? 'bg-slate-100';
@endphp
<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wide whitespace-nowrap {{ $class }}">{{ \App\Services\Shop\Purchasing::STATUSES[$status] ?? $status }}</span>
