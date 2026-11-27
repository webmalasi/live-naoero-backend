{{-- Header and section tabs shared by the shop sales pages. --}}
<div class="space-y-4">
    <div>
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-muted mb-1">
            <a href="{{ route('admin.shop.index') }}" class="hover:text-navy transition-colors">Shop & Gift Cards</a>
            <span>/</span>
            <span class="text-navy font-bold">{{ $crumb }}</span>
        </div>
        <h1 class="text-2xl font-extrabold text-navy tracking-tight">{{ $title }}</h1>
        <p class="text-sm text-ink-muted mt-0.5">{{ $subtitle }}</p>
    </div>
    <nav aria-label="Shop sales sections" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-1 p-1 rounded-xl bg-white border border-slate-200 shadow-sm">
        @foreach ([
            'admin.sales.index' => ['Shop Orders', ['admin.sales.index', 'admin.sales.show']],
            'admin.sales.payments' => ['Payments & Refunds', ['admin.sales.payments']],
            'admin.sales.returns' => ['Returns', ['admin.sales.returns', 'admin.sales.returns.*']],
            'admin.sales.support' => ['Help Requests', ['admin.sales.support', 'admin.sales.support.*']],
            'admin.sales.reviews' => ['Reviews', ['admin.sales.reviews']],
            'admin.sales.activity' => ['Activity Log', ['admin.sales.activity']],
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
