@extends('layouts.admin')

@section('title', 'Naoero Airlines Shop & Gift Cards')

@section('content')
<div class="max-w-7xl mx-auto space-y-8" x-data="{
    bannerModalOpen: false,
    viewMode: 'grid',
    toastMessage: '',
    showToast: false,
    notify(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3000);
    }
}">

    <!-- Toast Notification -->
    <div x-show="showToast" 
         x-cloak
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl bg-navy text-white text-xs font-bold shadow-2xl flex items-center gap-2 border border-accent/40">
        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
        <span x-text="toastMessage"></span>
    </div>

    <!-- Top Header & Action Controls -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Shop & Gift Cards Console</h1>
                <span class="px-2.5 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-accent/20 text-navy border border-accent/40">
                    Retail & Vouchers
                </span>
            </div>
            <p class="text-sm text-ink-muted mt-1">Manage Pacific island merchandise, branded apparel, travel vouchers, and homepage promotional cards.</p>
        </div>

        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" 
                    @click="bannerModalOpen = !bannerModalOpen" 
                    class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>Homepage Banners</span>
            </button>

            <a href="{{ route('admin.shop.orders') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <span>Orders & Vouchers</span>
                @if($pendingOrdersCount > 0)
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] bg-rose-500 text-white font-black animate-pulse">
                        {{ $pendingOrdersCount }} new
                    </span>
                @endif
            </a>

            <a href="{{ route('admin.shop.create') }}" 
               class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 flex items-center gap-2 transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
                <span>Add Product / Gift Card</span>
            </a>
        </div>
    </div>

    <!-- Stats KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-6 gap-3.5">
        <a href="{{ route('admin.shop.index') }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Items</span>
                <span class="text-xs font-extrabold text-navy bg-navy/5 px-2 py-0.5 rounded-md">{{ $totalCount }}</span>
            </div>
            <p class="text-2xl font-extrabold text-navy mt-2">{{ $totalCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Catalog products</p>
        </a>

        <a href="{{ route('admin.shop.index', ['category' => 'Merch']) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-blue-700 uppercase tracking-wider">Merchandise</span>
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
            </div>
            <p class="text-2xl font-extrabold text-blue-600 mt-2">{{ $merchCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Hoodies, bottles, caps</p>
        </a>

        <a href="{{ route('admin.shop.index', ['category' => 'Gift Card']) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-amber-700 uppercase tracking-wider">Gift Cards</span>
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
            </div>
            <p class="text-2xl font-extrabold text-amber-600 mt-2">{{ $giftCardCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Travel vouchers</p>
        </a>

        <a href="{{ route('admin.shop.index', ['recommended' => 1]) }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">Recommended</span>
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            </div>
            <p class="text-2xl font-extrabold text-emerald-600 mt-2">{{ $recommendedCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Recommended For U</p>
        </a>

        <div class="naoero-card p-4 bg-white border border-slate-200">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Stock Valuation</span>
                <span class="text-[10px] font-mono font-bold text-slate-400">AUD</span>
            </div>
            <p class="text-2xl font-extrabold text-navy mt-2">${{ number_format($totalStockValue, 0) }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">Total retail value</p>
        </div>

        <a href="{{ route('admin.shop.orders') }}" 
           class="naoero-card p-4 bg-white border border-slate-200 hover:border-slate-300 transition-all">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-purple-700 uppercase tracking-wider">Voucher Orders</span>
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
            </div>
            <p class="text-2xl font-extrabold text-purple-600 mt-2">{{ $ordersCount }}</p>
            <p class="text-[11px] text-slate-400 mt-0.5">${{ number_format($totalRevenue, 0) }} AUD total</p>
        </a>
    </div>

    <!-- Collapsible Homepage Shop Cards Editor (ShopSection.jsx) -->
    <div x-show="bannerModalOpen" 
         x-cloak
         x-transition
         class="naoero-card p-6 bg-white border border-slate-200 space-y-4 shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
                <h2 class="text-base font-extrabold text-navy flex items-center gap-2">
                    <svg class="w-5 h-5 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Homepage Shop Promotional Cards (`ShopSection.jsx`)</span>
                </h2>
                <p class="text-xs text-ink-muted">Configure the 2 promotional banner cards displayed on the homepage linking to merchandise and gift cards.</p>
            </div>
            <button type="button" @click="bannerModalOpen = false" class="text-slate-400 hover:text-slate-600 text-sm font-bold">
                ✕ Close
            </button>
        </div>

        <form method="POST" action="{{ route('admin.shop.banners.update') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                @foreach($banners as $banner)
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black text-navy uppercase tracking-wider">Card #{{ $banner->id }}</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="banners[{{ $banner->id }}][is_active]" value="1" class="sr-only peer" {{ $banner->is_active ? 'checked' : '' }}>
                                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500"></div>
                                <span class="ml-2 text-xs font-bold text-slate-700">Display on Home</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Card Heading</label>
                            <input type="text" 
                                   name="banners[{{ $banner->id }}][title]" 
                                   value="{{ $banner->title }}" 
                                   required
                                   class="w-full px-3 py-1.5 text-xs font-bold rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 bg-white">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Description Subtext</label>
                            <textarea name="banners[{{ $banner->id }}][description]" 
                                      rows="2" 
                                      class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 bg-white">{{ $banner->description }}</textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Button Text (CTA)</label>
                                <input type="text" 
                                       name="banners[{{ $banner->id }}][cta]" 
                                       value="{{ $banner->cta }}" 
                                       class="w-full px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 bg-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Target Filter Tag</label>
                                <input type="text" 
                                       name="banners[{{ $banner->id }}][filter]" 
                                       value="{{ $banner->filter }}" 
                                       class="w-full px-3 py-1.5 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 bg-white">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="bannerModalOpen = false" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50">
                    Cancel
                </button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-navy hover:bg-navy-900 text-white font-bold text-xs shadow-sm transition-all">
                    Save Homepage Banners
                </button>
            </div>
        </form>
    </div>

    <!-- Filter & Search Console -->
    <div class="naoero-card p-4 bg-white border border-slate-200 space-y-3">
        <!-- Top Row: Category Pills & View Switch -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-100 pb-3">
            <!-- Categories Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 md:pb-0">
                @foreach($categories as $cat)
                    <a href="{{ route('admin.shop.index', array_merge(request()->query(), ['category' => $cat === 'All' ? null : $cat])) }}" 
                       class="px-3.5 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap transition-all {{ (request('category') == $cat || (!request('category') && $cat === 'All')) ? 'bg-navy text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>

            <!-- View Switch & CSV Export -->
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('admin.shop.index', array_merge(request()->query(), ['export' => 'csv'])) }}" 
                   class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 text-xs font-bold text-slate-600 flex items-center gap-1.5 shadow-sm"
                   title="Export catalog to CSV spreadsheet">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span>Export CSV</span>
                </a>

                <div class="p-0.5 rounded-lg bg-slate-100 border border-slate-200 flex items-center">
                    <button type="button" 
                            @click="viewMode = 'grid'" 
                            :class="viewMode === 'grid' ? 'bg-white text-navy shadow-sm font-bold' : 'text-slate-500 hover:text-navy'"
                            class="px-2.5 py-1 text-xs rounded-md transition-all flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                        </svg>
                        <span>Grid</span>
                    </button>
                    <button type="button" 
                            @click="viewMode = 'table'" 
                            :class="viewMode === 'table' ? 'bg-white text-navy shadow-sm font-bold' : 'text-slate-500 hover:text-navy'"
                            class="px-2.5 py-1 text-xs rounded-md transition-all flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                        </svg>
                        <span>Table</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Row: Search, Type, Stock, Sort -->
        <form method="GET" action="{{ route('admin.shop.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            @if(request('category'))
                <input type="hidden" name="category" value="{{ request('category') }}">
            @endif

            <!-- Search -->
            <div class="relative">
                <input type="text" 
                       name="search" 
                       value="{{ request('search') }}" 
                       placeholder="Search title, SKU, description..." 
                       class="w-full pl-9 pr-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Stock Status -->
            <div>
                <select name="stock_status" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40 font-semibold text-slate-700">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock" {{ request('stock_status') === 'in_stock' ? 'selected' : '' }}>In Stock Only</option>
                    <option value="low_stock" {{ request('stock_status') === 'low_stock' ? 'selected' : '' }}>Low Stock (&le; 10 units)</option>
                    <option value="out_of_stock" {{ request('stock_status') === 'out_of_stock' ? 'selected' : '' }}>Out of Stock</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <select name="sort" onchange="this.form.submit()" class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40 font-semibold text-slate-700">
                    <option value="default" {{ request('sort') === 'default' ? 'selected' : '' }}>Sort: Default Display Order</option>
                    <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="stock_asc" {{ request('sort') === 'stock_asc' ? 'selected' : '' }}>Stock: Lowest First</option>
                    <option value="title_asc" {{ request('sort') === 'title_asc' ? 'selected' : '' }}>Name: A to Z</option>
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest Items First</option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit" class="w-full py-2 bg-navy hover:bg-navy-900 text-white rounded-xl text-xs font-bold transition-all shadow-sm">
                    Filter Results
                </button>
                @if(request()->hasAny(['search', 'stock_status', 'sort', 'category', 'recommended', 'active']))
                    <a href="{{ route('admin.shop.index') }}" class="px-3 py-2 rounded-xl border border-slate-200 hover:bg-slate-100 text-xs font-bold text-slate-600">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- 1. GRID VIEW MODE -->
    <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($products as $product)
            <div class="naoero-card bg-white border border-slate-200 overflow-hidden flex flex-col justify-between hover:border-slate-300 transition-all group">
                <!-- Product Image & Badges -->
                <div>
                    <div class="h-48 bg-slate-50 border-b border-slate-100 p-4 relative flex items-center justify-center overflow-hidden">
                        <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="h-full w-full object-contain group-hover:scale-105 transition-transform duration-300">
                        
                        <!-- Badges -->
                        <div class="absolute top-3 left-3 flex items-center gap-1.5 flex-wrap">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ ($product->type === 'gift_card' || $product->category === 'Gift Card') ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-blue-100 text-blue-900 border border-blue-300' }}">
                                {{ $product->category }}
                            </span>

                            @if($product->is_recommended)
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-900 border border-emerald-300 flex items-center gap-1">
                                    ★ Recommended
                                </span>
                            @endif

                            @if($product->discount_percent)
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-rose-500 text-white">
                                    -{{ $product->discount_percent }}% OFF
                                </span>
                            @endif
                        </div>

                        <!-- Price Tag -->
                        <div class="absolute bottom-3 right-3 bg-navy text-white px-3 py-1 rounded-lg text-xs font-black shadow-md flex items-center gap-1.5">
                            @if($product->compare_at_price && $product->compare_at_price > $product->price)
                                <span class="line-through text-slate-400 text-[10px]">${{ number_format($product->compare_at_price, 2) }}</span>
                            @endif
                            <span>{{ $product->currency }} ${{ number_format($product->price, 2) }}</span>
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="p-4 space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-extrabold text-navy line-clamp-1 group-hover:text-accent transition-colors">{{ $product->title }}</h3>
                            <span class="text-[10px] font-mono text-slate-400 shrink-0">{{ $product->sku }}</span>
                        </div>

                        <p class="text-xs text-ink-muted line-clamp-2">{{ $product->short_description ?: strip_tags($product->description ?: 'Official Naoero Airlines product.') }}</p>

                        <!-- Stock & Type Bar -->
                        <div class="pt-2 text-[11px] text-slate-500 flex items-center justify-between border-t border-slate-50">
                            <div class="flex items-center gap-1.5">
                                <span>Stock:</span>
                                @if($product->type === 'gift_card' || $product->category === 'Gift Card')
                                    <span class="font-bold text-emerald-600">Digital / Unlimited</span>
                                @elseif($product->stock_quantity <= 0)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-rose-100 text-rose-700 font-bold">Out of Stock</span>
                                @elseif($product->stock_quantity <= 10)
                                    <span class="px-1.5 py-0.2 rounded text-[10px] bg-amber-100 text-amber-800 font-bold">Low: {{ $product->stock_quantity }}</span>
                                @else
                                    <strong class="text-navy font-bold">{{ $product->stock_quantity }}</strong>
                                @endif
                            </div>

                            @if($product->rating)
                                <div class="flex items-center gap-1 text-amber-500 font-bold text-xs">
                                    <span>★</span>
                                    <span class="text-navy text-[11px]">{{ number_format($product->rating, 1) }}</span>
                                    <span class="text-slate-400 text-[10px]">({{ $product->reviews_count }})</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Footer Toggles & Actions -->
                <div class="p-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-between gap-2">
                    <!-- Quick Toggles -->
                    <div class="flex items-center gap-3">
                        <!-- Active Toggle -->
                        <form method="POST" action="{{ route('admin.shop.toggle-active', $product) }}">
                            @csrf
                            @method('PATCH')
                            <label class="relative inline-flex items-center cursor-pointer" title="{{ $product->is_active ? 'Click to deactivate' : 'Click to activate' }}">
                                <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" {{ $product->is_active ? 'checked' : '' }}>
                                <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                            </label>
                        </form>

                        <!-- Recommended Toggle -->
                        <form method="POST" action="{{ route('admin.shop.toggle-recommended', $product) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs font-bold transition-colors {{ $product->is_recommended ? 'text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200' : 'text-slate-400 hover:text-slate-600' }}" title="Toggle Recommended For U">
                                ★ Rec
                            </button>
                        </form>

                        <!-- Featured Toggle -->
                        <form method="POST" action="{{ route('admin.shop.toggle-featured', $product) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="text-xs font-bold transition-colors {{ $product->is_featured ? 'text-amber-700 bg-amber-50 px-2 py-0.5 rounded border border-amber-200' : 'text-slate-400 hover:text-slate-600' }}" title="Toggle Featured">
                                ★ Feat
                            </button>
                        </form>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-1.5">
                        <a href="{{ route('admin.shop.edit', $product) }}" class="px-3 py-1 rounded-lg bg-navy hover:bg-navy-900 text-white font-bold text-xs shadow-sm transition-all">
                            Edit
                        </a>

                        <form method="POST" action="{{ route('admin.shop.destroy', $product) }}" onsubmit="return confirm('Are you sure you want to delete this product?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-1 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all" title="Delete Item">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full naoero-card p-12 bg-white border border-slate-200 text-center space-y-4">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 border border-amber-200 flex items-center justify-center text-amber-600">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-navy">No products match your criteria</h3>
                    <p class="text-xs text-ink-muted mt-1">Try clearing your filters or create a new airline souvenir or gift card.</p>
                </div>
                <a href="{{ route('admin.shop.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent text-navy font-extrabold text-xs shadow-md shadow-accent/20">
                    <span>Add New Item</span>
                </a>
            </div>
        @endforelse
    </div>

    <!-- 2. TABLE VIEW MODE -->
    <div x-show="viewMode === 'table'" x-cloak class="naoero-card bg-white border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                        <th class="py-3 px-4">Item</th>
                        <th class="py-3 px-3">Category</th>
                        <th class="py-3 px-3">SKU</th>
                        <th class="py-3 px-3">Price (AUD)</th>
                        <th class="py-3 px-3">Stock</th>
                        <th class="py-3 px-3">Flags</th>
                        <th class="py-3 px-3">Status</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($products as $product)
                        <tr class="hover:bg-slate-50/60 transition-colors">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="w-10 h-10 rounded-lg object-contain bg-slate-50 border border-slate-100 p-1">
                                    <div>
                                        <span class="font-extrabold text-navy block text-xs">{{ $product->title }}</span>
                                        <span class="text-slate-400 text-[10px] block line-clamp-1">{{ $product->short_description ?: 'No short description' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider {{ ($product->type === 'gift_card' || $product->category === 'Gift Card') ? 'bg-amber-100 text-amber-900' : 'bg-blue-100 text-blue-900' }}">
                                    {{ $product->category }}
                                </span>
                            </td>
                            <td class="py-3 px-3 font-mono text-[11px] text-slate-500 font-bold">
                                {{ $product->sku }}
                            </td>
                            <td class="py-3 px-3">
                                <span class="font-extrabold text-navy text-xs">${{ number_format($product->price, 2) }}</span>
                                @if($product->compare_at_price)
                                    <span class="block line-through text-slate-400 text-[10px]">${{ number_format($product->compare_at_price, 2) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                @if($product->type === 'gift_card' || $product->category === 'Gift Card')
                                    <span class="text-emerald-600 font-bold text-[11px]">Unlimited</span>
                                @elseif($product->stock_quantity <= 10)
                                    <span class="px-1.5 py-0.5 rounded text-[10px] bg-amber-100 text-amber-800 font-bold">{{ $product->stock_quantity }} units</span>
                                @else
                                    <span class="font-bold text-navy">{{ $product->stock_quantity }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-3">
                                <div class="flex items-center gap-1.5">
                                    @if($product->is_recommended)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] bg-emerald-100 text-emerald-800 font-bold uppercase" title="Recommended For U">★ Rec</span>
                                    @endif
                                    @if($product->is_featured)
                                        <span class="px-1.5 py-0.5 rounded text-[9px] bg-amber-100 text-amber-800 font-bold uppercase" title="Featured Item">★ Feat</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-3">
                                <form method="POST" action="{{ route('admin.shop.toggle-active', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" onchange="this.form.submit()" class="sr-only peer" {{ $product->is_active ? 'checked' : '' }}>
                                        <div class="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-emerald-500"></div>
                                    </label>
                                </form>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.shop.edit', $product) }}" class="px-2.5 py-1 rounded bg-navy text-white text-[11px] font-bold hover:bg-navy-900 transition-all">
                                        Edit
                                    </a>
                                    <form method="POST" action="{{ route('admin.shop.destroy', $product) }}" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1 rounded text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-slate-400">
                                No products found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="pt-4">
            {{ $products->links() }}
        </div>
    @endif

</div>
@endsection
