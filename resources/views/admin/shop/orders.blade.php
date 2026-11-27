@extends('layouts.admin')

@section('title', 'Gift Card Orders & Inquiries')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    selectedOrder: null,
    copiedCode: '',
    copyCode(code) {
        navigator.clipboard.writeText(code);
        this.copiedCode = code;
        setTimeout(() => { this.copiedCode = ''; }, 2500);
    }
}">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1">
                <a href="{{ route('admin.shop.index') }}" class="hover:text-navy transition-colors">Shop & Gift Cards</a>
                <span>/</span>
                <span class="text-navy font-bold">Orders & Vouchers Ledger</span>
            </div>
            <h1 class="text-2xl font-extrabold text-navy tracking-tight">Gift Card Orders & Voucher Hub</h1>
            <p class="text-sm text-ink-muted mt-0.5">Review customer travel gift card purchase inquiries, manage voucher status, and issue redemption codes.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.shop.orders', array_merge(request()->query(), ['export' => 'csv'])) }}" 
               class="px-3.5 py-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm flex items-center gap-1.5">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export Orders (CSV)</span>
            </a>

            <a href="{{ route('admin.shop.index') }}" class="px-4 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-bold text-xs shadow-sm transition-all flex items-center gap-2">
                <span>&larr; Shop Catalog</span>
            </a>
        </div>
    </div>

    <!-- Status KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3.5">
        <a href="{{ route('admin.shop.orders') }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Total Inquiries</span>
            <p class="text-2xl font-extrabold text-navy mt-1.5">{{ $totalOrders }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">All customer orders</p>
        </a>

        <a href="{{ route('admin.shop.orders', ['status' => 'pending']) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider block">Pending Review</span>
            <p class="text-2xl font-extrabold text-amber-600 mt-1.5">{{ $pendingCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Awaiting verification</p>
        </a>

        <a href="{{ route('admin.shop.orders', ['status' => 'confirmed']) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider block">Confirmed / Paid</span>
            <p class="text-2xl font-extrabold text-emerald-600 mt-1.5">{{ $confirmedCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Ready for issuance</p>
        </a>

        <a href="{{ route('admin.shop.orders', ['status' => 'issued']) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <span class="text-[11px] font-bold text-blue-700 uppercase tracking-wider block">Active Vouchers</span>
            <p class="text-2xl font-extrabold text-blue-600 mt-1.5">{{ $issuedCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Issued to passenger</p>
        </a>

        <div class="naoero-card p-4 bg-white border border-slate-200">
            <span class="text-[11px] font-bold text-purple-700 uppercase tracking-wider block">Total Revenue</span>
            <p class="text-2xl font-extrabold text-purple-600 mt-1.5">${{ number_format($totalRevenue, 2) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">AUD Confirmed</p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="naoero-card p-4 bg-white border border-slate-200 space-y-3">
        <!-- Status Tabs -->
        <div class="flex items-center gap-1.5 overflow-x-auto border-b border-slate-100 pb-2.5">
            @php $currentStatus = request('status', 'all'); @endphp
            @foreach(['all' => 'All Orders', 'pending' => 'Pending', 'confirmed' => 'Confirmed', 'issued' => 'Issued', 'redeemed' => 'Redeemed', 'cancelled' => 'Cancelled'] as $k => $label)
                <a href="{{ route('admin.shop.orders', array_merge(request()->query(), ['status' => $k === 'all' ? null : $k])) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ ($currentStatus === $k || (!$currentStatus && $k === 'all')) ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- Search & Filter Controls -->
        <form method="GET" action="{{ route('admin.shop.orders') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            <div class="relative sm:col-span-2">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search by order #, buyer name, email, voucher code..." 
                       class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <div>
                <select name="delivery_type" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none font-semibold text-slate-700">
                    <option value="">All Delivery Types</option>
                    <option value="digital" {{ request('delivery_type') === 'digital' ? 'selected' : '' }}>Digital (Email eVoucher)</option>
                    <option value="postal" {{ request('delivery_type') === 'postal' ? 'selected' : '' }}>Physical Postal Delivery</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="w-full py-2 bg-navy hover:bg-navy-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                    Search
                </button>
                @if(request()->hasAny(['search', 'delivery_type', 'status']))
                    <a href="{{ route('admin.shop.orders') }}" class="px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-xs font-bold text-slate-600">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Orders Table Card -->
    <div class="naoero-card bg-white border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3.5 px-4">Order Reference</th>
                        <th class="py-3.5 px-3">Voucher Code</th>
                        <th class="py-3.5 px-3">Buyer Contact</th>
                        <th class="py-3.5 px-3">Recipient</th>
                        <th class="py-3.5 px-3">Amount</th>
                        <th class="py-3.5 px-3">Delivery</th>
                        <th class="py-3.5 px-3">Status</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($orders as $order)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3.5 px-4 font-mono font-bold text-navy">
                                {{ $order->order_number }}
                                <span class="block text-[10px] text-slate-400 font-normal">{{ $order->created_at->format('M d, Y H:i') }}</span>
                            </td>
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 font-mono font-bold text-navy text-[11px]">
                                        {{ $order->voucher_code }}
                                    </span>
                                    <button type="button" 
                                            @click="copyCode('{{ $order->voucher_code }}')" 
                                            class="p-1 text-slate-400 hover:text-navy transition-colors"
                                            title="Copy Voucher Code">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                                <span x-show="copiedCode === '{{ $order->voucher_code }}'" class="text-[10px] text-emerald-600 font-bold block">Copied!</span>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="font-bold text-navy block">{{ $order->buyer_name }}</span>
                                <span class="text-slate-500 block text-[11px]">{{ $order->buyer_email }}</span>
                                @if($order->buyer_phone)
                                    <span class="text-slate-400 text-[10px]">{{ $order->buyer_phone }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                @if($order->recipient_name)
                                    <span class="font-semibold text-slate-700 block">{{ $order->recipient_name }}</span>
                                    <span class="text-slate-400 text-[10px]">{{ $order->recipient_email }}</span>
                                @else
                                    <span class="text-slate-400 italic">Self (Purchaser)</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="font-extrabold text-navy text-xs">
                                    {{ $order->formatted_amount }}
                                </span>
                                @if($order->quantity > 1)
                                    <span class="block text-[10px] text-slate-400">Qty: {{ $order->quantity }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $order->delivery_type === 'digital' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">
                                    {{ $order->delivery_type }}
                                </span>
                            </td>
                            <td class="py-3.5 px-3">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $order->status_badge_class }}">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <form method="POST" action="{{ route('admin.shop.orders.status', $order) }}" class="inline-flex items-center">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" onchange="this.form.submit()" class="px-2 py-1 text-xs rounded-lg border border-slate-200 bg-white focus:outline-none font-bold text-slate-700">
                                            <option value="pending" {{ $order->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                            <option value="confirmed" {{ $order->status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                            <option value="issued" {{ $order->status === 'issued' ? 'selected' : '' }}>Issued</option>
                                            <option value="redeemed" {{ $order->status === 'redeemed' ? 'selected' : '' }}>Redeemed</option>
                                            <option value="cancelled" {{ $order->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                        </select>
                                    </form>

                                    <!-- Regenerate Voucher Button -->
                                    <form method="POST" action="{{ route('admin.shop.orders.regenerate-voucher', $order) }}" onsubmit="return confirm('Generate a new unique voucher code for this order?');">
                                        @csrf
                                        <button type="submit" class="p-1 rounded text-slate-400 hover:text-navy hover:bg-slate-100 transition-all" title="Regenerate Voucher Code">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400">
                                No gift card orders match the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $orders->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
