@php
    // One tag style for every outcome, payment and order status in the sales pages.
    $key = $tag ?? 'info';
    $map = [
        'success' => ['Success', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
        'paid' => ['Paid', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
        'delivered' => ['Delivered', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
        'shipped' => ['Shipped', 'bg-sky-50 text-sky-700 border-sky-200', 'bg-sky-500'],
        'processing' => ['To ship', 'bg-indigo-50 text-indigo-700 border-indigo-200', 'bg-indigo-500'],
        'declined' => ['Declined', 'bg-rose-50 text-rose-700 border-rose-200', 'bg-rose-500'],
        'failed' => ['Failed', 'bg-red-50 text-red-700 border-red-200', 'bg-red-600'],
        'review' => ['Under review', 'bg-amber-50 text-amber-800 border-amber-200', 'bg-amber-500'],
        'payment_review' => ['Under review', 'bg-amber-50 text-amber-800 border-amber-200', 'bg-amber-500'],
        'pending' => ['Pending', 'bg-yellow-50 text-yellow-800 border-yellow-200', 'bg-yellow-500'],
        'pending_payment' => ['Awaiting payment', 'bg-yellow-50 text-yellow-800 border-yellow-200', 'bg-yellow-500'],
        'started' => ['In progress', 'bg-yellow-50 text-yellow-800 border-yellow-200', 'bg-yellow-500'],
        'abandoned' => ['Abandoned', 'bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400'],
        'cancelled' => ['Cancelled', 'bg-slate-100 text-slate-600 border-slate-200', 'bg-slate-400'],
        'refunded' => ['Refunded', 'bg-purple-50 text-purple-700 border-purple-200', 'bg-purple-500'],
        'partially_refunded' => ['Partly refunded', 'bg-purple-50 text-purple-700 border-purple-200', 'bg-purple-400'],
        'succeeded' => ['Succeeded', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'bg-emerald-500'],
        'warning' => ['Warning', 'bg-orange-50 text-orange-700 border-orange-200', 'bg-orange-500'],
        'info' => ['Info', 'bg-slate-50 text-slate-600 border-slate-200', 'bg-navy-400'],
    ];
    [$label, $classes, $dot] = $map[$key] ?? [ucfirst(str_replace('_', ' ', $key)), 'bg-slate-50 text-slate-600 border-slate-200', 'bg-slate-400'];
@endphp
<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[11px] font-bold whitespace-nowrap {{ $classes }}">
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>{{ $text ?? $label }}
</span>
