{{-- Header and section tabs shared by the shop inventory pages. --}}
<div class="space-y-4">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1">
            <a href="{{ route('admin.shop.index') }}" class="hover:text-navy transition-colors">Shop & Gift Cards</a>
            <span>/</span>
            <a href="{{ route('admin.inventory.index') }}" class="hover:text-navy transition-colors">Inventory</a>
            @if ($crumb !== 'Overview')
                <span>/</span>
                <span class="text-navy font-bold">{{ $crumb }}</span>
            @endif
        </div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">{{ $title }}</h1>
        <p class="text-sm text-ink-muted mt-0.5">{{ $subtitle }}</p>
    </div>
    <nav aria-label="Inventory sections" class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-sm">
        @foreach ([
            'admin.inventory.index' => ['Overview', ['admin.inventory.index']],
            'admin.inventory.stock' => ['Stock', ['admin.inventory.stock', 'admin.inventory.product']],
            'admin.inventory.movements' => ['Movements', ['admin.inventory.movements']],
            'admin.inventory.purchase-orders.index' => ['Purchase Orders', ['admin.inventory.purchase-orders.*']],
            'admin.inventory.suppliers' => ['Suppliers', ['admin.inventory.suppliers']],
            'admin.inventory.counts.index' => ['Stock Counts', ['admin.inventory.counts.*']],
            'admin.inventory.alerts' => ['Customer Alerts', ['admin.inventory.alerts']],
        ] as $route => [$label, $patterns])
            <a href="{{ route($route) }}"
               @if (request()->routeIs(...$patterns)) aria-current="page" @endif
               class="flex items-center justify-center text-center px-3 py-2.5 rounded-lg text-xs font-bold leading-tight transition-all {{ request()->routeIs(...$patterns) ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
        @endforeach
    </nav>
</div>

@if (session('success'))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-800">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
@endif
