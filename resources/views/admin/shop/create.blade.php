@extends('layouts.admin')

@section('title', 'Add New Product / Gift Card')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    title: '{{ addslashes(old('title', '')) }}',
    slug: '{{ addslashes(old('slug', '')) }}',
    category: '{{ addslashes(old('category', ($categories[0] ?? 'Apparel'))) }}',
    type: '{{ old('type', 'merch') }}',
    price: '{{ old('price', '') }}',
    compareAtPrice: '{{ old('compare_at_price', '') }}',
    currency: '{{ old('currency', 'AUD') }}',
    stockQuantity: {{ old('stock_quantity', '0') }},
    sku: '{{ addslashes(old('sku', '')) }}',
    rating: '{{ old('rating', '5.0') }}',
    reviewsCount: {{ old('reviews_count', '0') }},
    isRecommended: {{ old('is_recommended') ? 'true' : 'false' }},
    isFeatured: {{ old('is_featured') ? 'true' : 'false' }},
    isActive: {{ old('is_active', true) ? 'true' : 'false' }},
    sortOrder: {{ old('sort_order', '0') }},
    shortDescription: '{{ addslashes(old('short_description', '')) }}',
    imagePath: '{{ addslashes(old('image', '')) }}',
    localImagePreview: null,
    
    get discountPercent() {
        let p = parseFloat(this.price) || 0;
        let c = parseFloat(this.compareAtPrice) || 0;
        if (c > p && p > 0) {
            return Math.round(((c - p) / c) * 100);
        }
        return null;
    },
    get savingsAmount() {
        let p = parseFloat(this.price) || 0;
        let c = parseFloat(this.compareAtPrice) || 0;
        if (c > p && p > 0) {
            return (c - p).toFixed(2);
        }
        return null;
    },
    get previewImageUrl() {
        if (this.localImagePreview) {
            return this.localImagePreview;
        }
        if (this.imagePath && this.imagePath.trim().length > 0) {
            if (this.imagePath.startsWith('http://') || this.imagePath.startsWith('https://') || this.imagePath.startsWith('/')) {
                return this.imagePath;
            }
            return '/' + this.imagePath.replace(/^\/+/, '');
        }
        return '/assets/giftcard/merch.png';
    },
    updateSlug() {
        if (!this.title) {
            this.slug = '';
            return;
        }
        this.slug = this.title
            .toLowerCase()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },
    handleFileUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.localImagePreview = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    },
    selectAsset(path) {
        this.imagePath = path;
        this.localImagePreview = null;
    },
    generateSku() {
        const prefix = (this.type === 'gift_card' || this.category === 'Gift Card') ? 'GC' : 'NA';
        const rand = Math.random().toString(36).substring(2, 6).toUpperCase();
        const catCode = this.category ? this.category.substring(0, 2).toUpperCase() : 'PR';
        this.sku = `${prefix}-${catCode}-${rand}`;
    },
    quickTemplate(tpl) {
        this.localImagePreview = null;
        if (tpl === 'hoodie') {
            this.title = 'Signature Hoodie';
            this.slug = 'signature-hoodie';
            this.category = 'Apparel';
            this.type = 'merch';
            this.price = '58.00';
            this.compareAtPrice = '75.00';
            this.currency = 'AUD';
            this.stockQuantity = 150;
            this.sku = 'NA-AP-HD01';
            this.shortDescription = 'Heavyweight Pacific blue fleece with gold embroidered 12-point star.';
            this.imagePath = 'assets/giftcard/hoodie.png';
            this.isRecommended = true;
            this.isFeatured = false;
        } else if (tpl === 'bottle') {
            this.title = 'Steel Bottle';
            this.slug = 'steel-bottle';
            this.category = 'Drinkware';
            this.type = 'merch';
            this.price = '28.00';
            this.compareAtPrice = '35.00';
            this.currency = 'AUD';
            this.stockQuantity = 200;
            this.sku = 'NA-DW-BT02';
            this.shortDescription = 'Double-wall vacuum insulated 750ml flask with leakproof seal.';
            this.imagePath = 'assets/giftcard/bottle.png';
            this.isRecommended = true;
            this.isFeatured = false;
        } else if (tpl === 'cap') {
            this.title = 'Signature Cap';
            this.slug = 'signature-cap';
            this.category = 'Caps';
            this.type = 'merch';
            this.price = '22.00';
            this.compareAtPrice = '28.00';
            this.currency = 'AUD';
            this.stockQuantity = 180;
            this.sku = 'NA-CP-CP03';
            this.shortDescription = '6-panel structured cotton twill cap with brass adjuster.';
            this.imagePath = 'assets/giftcard/cap.png';
            this.isRecommended = true;
            this.isFeatured = false;
        } else if (tpl === 'teddy') {
            this.title = 'Plush Teddy';
            this.slug = 'plush-teddy';
            this.category = 'Toys';
            this.type = 'merch';
            this.price = '25.00';
            this.compareAtPrice = '30.00';
            this.currency = 'AUD';
            this.stockQuantity = 95;
            this.sku = 'NA-TY-TD04';
            this.shortDescription = 'Captain Teddy Bear dressed in official pilot uniform and aviator goggles.';
            this.imagePath = 'assets/giftcard/teddy.png';
            this.isRecommended = false;
            this.isFeatured = false;
        } else if (tpl === 'tshirt') {
            this.title = 'Naoero Airlines Vintage Tee';
            this.slug = 'naoero-airlines-vintage-tee';
            this.category = 'Tshirts';
            this.type = 'merch';
            this.price = '32.00';
            this.compareAtPrice = '40.00';
            this.currency = 'AUD';
            this.stockQuantity = 120;
            this.sku = 'NA-TS-VT05';
            this.shortDescription = 'Vintage Pacific retro route map t-shirt in 100% combed cotton.';
            this.imagePath = 'assets/giftcard/merch.png';
            this.isRecommended = false;
            this.isFeatured = false;
        } else if (tpl === 'gc500') {
            this.title = 'AUD 500 Gift Card';
            this.slug = 'aud-500-gift-card';
            this.category = 'Gift Card';
            this.type = 'gift_card';
            this.price = '500.00';
            this.compareAtPrice = '';
            this.currency = 'AUD';
            this.stockQuantity = 999;
            this.sku = 'GC-AUD-0500';
            this.shortDescription = 'AUD $500 travel voucher redeemable for any scheduled flight or merchandise.';
            this.imagePath = 'assets/giftcard/500aud.png';
            this.isFeatured = true;
            this.isRecommended = false;
        } else if (tpl === 'gc1000') {
            this.title = 'AUD 1000 Gift Card';
            this.slug = 'aud-1000-gift-card';
            this.category = 'Gift Card';
            this.type = 'gift_card';
            this.price = '1000.00';
            this.compareAtPrice = '';
            this.currency = 'AUD';
            this.stockQuantity = 999;
            this.sku = 'GC-AUD-1000';
            this.shortDescription = 'AUD $1000 travel certificate for round-trips and island hopping.';
            this.imagePath = 'assets/giftcard/1000AUD.png';
            this.isFeatured = true;
            this.isRecommended = false;
        } else if (tpl === 'gc2000') {
            this.title = 'AUD 2000 Gift Card';
            this.slug = 'aud-2000-gift-card';
            this.category = 'Gift Card';
            this.type = 'gift_card';
            this.price = '2000.00';
            this.compareAtPrice = '';
            this.currency = 'AUD';
            this.stockQuantity = 999;
            this.sku = 'GC-AUD-2000';
            this.shortDescription = 'AUD $2000 executive travel certificate for luxury holidays and charters.';
            this.imagePath = 'assets/giftcard/2000AUD.png';
            this.isFeatured = true;
            this.isRecommended = false;
        }
    }
}">

    <!-- Top Header & Breadcrumb -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.shop.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.shop.index') }}" class="hover:text-navy transition-colors">Shop & Gift Cards</a>
                    <span>/</span>
                    <span class="text-navy font-bold">New Product</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Add Product or Gift Card</h1>
                <p class="text-xs text-ink-muted">Configure airline branded merchandise, travel accessories, or digital gift vouchers.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.shop.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('shop-create-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-navy hover:bg-navy-900 text-white font-extrabold text-xs shadow-sm hover:shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Save Product</span>
            </button>
        </div>
    </div>

    <!-- Error Summary (if any) -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs shadow-sm animate-pulse">
            <div class="flex items-center gap-2 font-bold mb-1 text-sm text-rose-900">
                <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please correct the errors below:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- One-Click Preset Templates Bar -->
    <div class="naoero-card p-4 bg-gradient-to-r from-navy/5 via-white to-accent/10 border border-slate-200 shadow-sm space-y-2.5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-accent animate-ping"></span>
                <span class="text-xs font-extrabold text-navy uppercase tracking-wider">⚡ One-Click Airline Product Presets:</span>
            </div>
            <span class="text-[11px] font-medium text-slate-500">Click any preset to auto-fill official flight gear specs & pricing</span>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="quickTemplate('hoodie')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-blue-50 text-blue-900 border border-blue-200 text-xs font-bold transition-all shadow-xs hover:border-blue-400 flex items-center gap-1.5">
                <span>🧥</span>
                <span>Signature Hoodie</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-extrabold">$58</span>
            </button>
            <button type="button" @click="quickTemplate('bottle')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-900 border border-emerald-200 text-xs font-bold transition-all shadow-xs hover:border-emerald-400 flex items-center gap-1.5">
                <span>🍶</span>
                <span>Steel Bottle</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-extrabold">$28</span>
            </button>
            <button type="button" @click="quickTemplate('cap')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-indigo-50 text-indigo-900 border border-indigo-200 text-xs font-bold transition-all shadow-xs hover:border-indigo-400 flex items-center gap-1.5">
                <span>🧢</span>
                <span>Signature Cap</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-800 font-extrabold">$22</span>
            </button>
            <button type="button" @click="quickTemplate('teddy')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-pink-50 text-pink-900 border border-pink-200 text-xs font-bold transition-all shadow-xs hover:border-pink-400 flex items-center gap-1.5">
                <span>🧸</span>
                <span>Plush Teddy</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-pink-100 text-pink-800 font-extrabold">$25</span>
            </button>
            <button type="button" @click="quickTemplate('tshirt')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-900 border border-slate-200 text-xs font-bold transition-all shadow-xs hover:border-slate-400 flex items-center gap-1.5">
                <span>👕</span>
                <span>Vintage Tee</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-200 text-slate-800 font-extrabold">$32</span>
            </button>
            <button type="button" @click="quickTemplate('gc500')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 text-xs font-bold transition-all shadow-xs hover:border-amber-500 flex items-center gap-1.5">
                <span>💳</span>
                <span>$500 AUD Gift Card</span>
            </button>
            <button type="button" @click="quickTemplate('gc1000')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 text-xs font-bold transition-all shadow-xs hover:border-amber-500 flex items-center gap-1.5">
                <span>💳</span>
                <span>$1000 AUD Gift Card</span>
            </button>
            <button type="button" @click="quickTemplate('gc2000')" 
                    class="px-3 py-1.5 rounded-lg bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 text-xs font-bold transition-all shadow-xs hover:border-amber-500 flex items-center gap-1.5">
                <span>💳</span>
                <span>$2000 AUD Gift Card</span>
            </button>
        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <form id="shop-create-form" method="POST" action="{{ route('admin.shop.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Column: Form Fields (8 Cols) -->
            <div class="lg:col-span-8 space-y-6">

                <!-- 1. Basic Product Identity -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">1</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Product Identity & Classification</h2>
                                <p class="text-xs text-ink-muted">Set product title, slug, classification type, and category.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Title -->
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Product / Gift Card Title <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" 
                                   name="title" 
                                   x-model="title"
                                   @input="updateSlug()"
                                   placeholder="e.g. Signature Hoodie, Steel Bottle, AUD 500 Gift Card" 
                                   required 
                                   class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <!-- Slug (URL) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                URL Slug (Identifier)
                            </label>
                            <input type="text" 
                                   name="slug" 
                                   x-model="slug"
                                   placeholder="signature-hoodie" 
                                   class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                            <p class="text-[11px] text-slate-400 mt-1">API Identifier: <code>/api/shop/products/<span x-text="slug || 'slug'"></span></code></p>
                        </div>

                        <!-- SKU Code with Generator -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    SKU / Item Code
                                </label>
                                <button type="button" @click="generateSku()" class="text-[11px] font-bold text-navy hover:text-accent transition-colors">
                                    ↻ Auto-Generate
                                </button>
                            </div>
                            <input type="text" 
                                   name="sku" 
                                   x-model="sku"
                                   placeholder="e.g. NA-HD-001, GC-500-AUD" 
                                   class="w-full px-3.5 py-2.5 text-xs font-mono font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <!-- Category -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Store Category <span class="text-rose-500">*</span>
                                </label>
                                <a href="{{ route('admin.dropdowns.index', ['group' => 'shop_categories']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent">+ Manage</a>
                            </div>
                            <select name="category" 
                                    x-model="category"
                                    required 
                                    class="w-full px-3.5 py-2.5 text-xs font-bold rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Product Classification Type (Radio Cards) -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Item Classification Type <span class="text-rose-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all text-xs" 
                                       :class="type === 'merch' ? 'border-navy bg-navy/5 font-extrabold text-navy ring-1 ring-navy' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <input type="radio" name="type" value="merch" x-model="type" class="text-navy focus:ring-accent">
                                    <span>👕 Physical Merch</span>
                                </label>
                                <label class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all text-xs" 
                                       :class="type === 'gift_card' ? 'border-amber-400 bg-amber-50 font-extrabold text-amber-900 ring-1 ring-amber-400' : 'border-slate-200 text-slate-600 hover:bg-slate-50'">
                                    <input type="radio" name="type" value="gift_card" x-model="type" class="text-amber-500 focus:ring-accent">
                                    <span>💳 Travel Gift Card</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- 2. Pricing, Discount & Inventory -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">2</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Pricing, Discounts & Inventory</h2>
                                <p class="text-xs text-ink-muted">Set selling price, compare-at retail price for sale discount badges, and stock level.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Currency & Selling Price -->
                        <div class="md:col-span-1">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Selling Price <span class="text-rose-500">*</span>
                                </label>
                                <a href="{{ route('admin.dropdowns.index', ['group' => 'currencies']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent">+ Manage</a>
                            </div>
                            <div class="flex items-center rounded-xl border border-slate-200 focus-within:ring-2 focus-within:ring-accent/40 focus-within:border-accent overflow-hidden">
                                <select name="currency" x-model="currency" class="px-2.5 py-2.5 text-xs font-extrabold bg-slate-50 border-r border-slate-200 focus:outline-none text-slate-700">
                                    @foreach($currencies as $curr)
                                        <option value="{{ $curr->value }}">{{ $curr->value }} ({{ $curr->icon ?? '💵' }})</option>
                                    @endforeach
                                </select>
                                <input type="number" 
                                       name="price" 
                                       x-model="price"
                                       step="0.01" 
                                       min="0" 
                                       placeholder="58.00" 
                                       required 
                                       class="w-full px-3 py-2.5 text-sm font-bold focus:outline-none">
                            </div>
                        </div>

                        <!-- Compare At Price -->
                        <div class="md:col-span-1">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Regular / Compare Price
                                </label>
                                <template x-if="discountPercent">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white animate-bounce" x-text="'-' + discountPercent + '% OFF'"></span>
                                </template>
                            </div>
                            <input type="number" 
                                   name="compare_at_price" 
                                   x-model="compareAtPrice"
                                   step="0.01" 
                                   min="0" 
                                   placeholder="e.g. 75.00" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Stock Quantity -->
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Opening Stock
                            </label>
                            <p class="text-[10px] text-ink-muted -mt-1 mb-1">Sizes, deliveries and counts are managed in Inventory after saving.</p>
                            <input type="number" 
                                   name="stock_quantity" 
                                   x-model="stockQuantity"
                                   min="0" 
                                   placeholder="150" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Live Discount Savings Banner -->
                        <template x-if="discountPercent">
                            <div class="md:col-span-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between text-xs font-bold">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🎉</span>
                                    <span>Discount Active: Customer saves <strong x-text="'$' + savingsAmount + ' ' + currency"></strong> per unit (<span x-text="'-' + discountPercent + '%'"></span>).</span>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-extrabold text-[11px]">Badge will display in storefront</span>
                            </div>
                        </template>

                        <!-- Display Sort Order -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Sort Order Priority
                            </label>
                            <input type="number" 
                                   name="sort_order" 
                                   x-model="sortOrder"
                                   value="0" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Rating -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Initial Rating (1.0 - 5.0)
                            </label>
                            <input type="number" 
                                   name="rating" 
                                   x-model="rating"
                                   step="0.1" 
                                   min="1" 
                                   max="5" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Reviews Count -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Total Reviews Count
                            </label>
                            <input type="number" 
                                   name="reviews_count" 
                                   x-model="reviewsCount"
                                   min="0" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>
                    </div>
                </div>

                <!-- 3. Product Media & Visuals -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">3</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Product Media & Graphics</h2>
                                <p class="text-xs text-ink-muted">Upload a new photo file or select an official airline graphic asset.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Option A: File Upload -->
                        <div class="p-4 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50 hover:bg-slate-100/70 transition-colors space-y-2.5 text-center flex flex-col justify-center items-center">
                            <div class="w-10 h-10 rounded-full bg-navy/10 text-navy flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="text-xs font-extrabold text-slate-800 block">Option A: Upload Image File</span>
                                <span class="text-[11px] text-slate-500">PNG, JPG, or WEBP (transparent background)</span>
                            </div>
                            <input type="file" 
                                   name="image_file" 
                                   accept="image/*"
                                   @change="handleFileUpload($event)"
                                   class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy-900 cursor-pointer">
                        </div>

                        <!-- Option B: Static Asset Path -->
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2.5">
                            <span class="text-xs font-extrabold text-slate-800 block">Option B: Airline Static Asset Path</span>
                            <input type="text" 
                                   name="image" 
                                   x-model="imagePath"
                                   placeholder="assets/giftcard/hoodie.png" 
                                   class="w-full px-3.5 py-2 text-xs rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 font-mono bg-white">

                            <!-- Quick Asset Chips -->
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1.5">Official Airline Assets:</span>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <button type="button" @click="selectAsset('assets/giftcard/hoodie.png')" class="px-2 py-1 rounded bg-white hover:bg-slate-200 border border-slate-200 text-[10px] font-bold text-slate-700">hoodie.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/bottle.png')" class="px-2 py-1 rounded bg-white hover:bg-slate-200 border border-slate-200 text-[10px] font-bold text-slate-700">bottle.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/cap.png')" class="px-2 py-1 rounded bg-white hover:bg-slate-200 border border-slate-200 text-[10px] font-bold text-slate-700">cap.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/teddy.png')" class="px-2 py-1 rounded bg-white hover:bg-slate-200 border border-slate-200 text-[10px] font-bold text-slate-700">teddy.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/merch.png')" class="px-2 py-1 rounded bg-white hover:bg-slate-200 border border-slate-200 text-[10px] font-bold text-slate-700">merch.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/500aud.png')" class="px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 border border-amber-200 text-[10px] font-bold text-amber-900">500aud.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/1000AUD.png')" class="px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 border border-amber-200 text-[10px] font-bold text-amber-900">1000AUD.png</button>
                                    <button type="button" @click="selectAsset('assets/giftcard/2000AUD.png')" class="px-2 py-1 rounded bg-amber-50 hover:bg-amber-100 border border-amber-200 text-[10px] font-bold text-amber-900">2000AUD.png</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Content & Specifications -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">4</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Content & Product Specifications</h2>
                                <p class="text-xs text-ink-muted">Catchphrase summary and full WYSIWYG specifications.</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Short Description -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Card Subtitle / Short Tagline
                            </label>
                            <input type="text" 
                                   name="short_description" 
                                   x-model="shortDescription"
                                   placeholder="e.g. Heavyweight Pacific blue fleece with gold embroidered 12-point star." 
                                   class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                            <p class="text-[11px] text-slate-400 mt-1">Displayed underneath the title on the store catalog and home section.</p>
                        </div>

                        <!-- WYSIWYG Full Description -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Detailed Overview & Care Instructions (Quill WYSIWYG)
                            </label>
                            <textarea name="description" 
                                      rows="5" 
                                      class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent"
                                      placeholder="Describe fabric materials, sizing chart, gift voucher redemption conditions..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- 5. Placement & Marketing Visibility -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">5</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Placement & Visibility Flags</h2>
                                <p class="text-xs text-ink-muted">Control where this item appears across the airline web portal.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Active Toggle Card -->
                        <label class="p-4 rounded-xl border cursor-pointer transition-all flex items-start gap-3"
                               :class="isActive ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500' : 'border-slate-200 bg-slate-50'">
                            <input type="checkbox" name="is_active" value="1" x-model="isActive" class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <span class="text-xs font-extrabold text-navy block">Live in Store</span>
                                <span class="text-[11px] text-slate-500">Visible to public visitors & REST API.</span>
                            </div>
                        </label>

                        <!-- Recommended Toggle Card -->
                        <label class="p-4 rounded-xl border cursor-pointer transition-all flex items-start gap-3"
                               :class="isRecommended ? 'border-blue-500 bg-blue-50/50 ring-1 ring-blue-500' : 'border-slate-200 bg-slate-50'">
                            <input type="checkbox" name="is_recommended" value="1" x-model="isRecommended" class="mt-0.5 rounded text-navy focus:ring-accent">
                            <div>
                                <span class="text-xs font-extrabold text-navy block">Recommended For U</span>
                                <span class="text-[11px] text-slate-500">Featured in top carousel on `GiftCardDetail.jsx`.</span>
                            </div>
                        </label>

                        <!-- Featured Toggle Card -->
                        <label class="p-4 rounded-xl border cursor-pointer transition-all flex items-start gap-3"
                               :class="isFeatured ? 'border-amber-500 bg-amber-50/50 ring-1 ring-amber-500' : 'border-slate-200 bg-slate-50'">
                            <input type="checkbox" name="is_featured" value="1" x-model="isFeatured" class="mt-0.5 rounded text-amber-600 focus:ring-accent">
                            <div>
                                <span class="text-xs font-extrabold text-navy block">Featured Promo Badge</span>
                                <span class="text-[11px] text-slate-500">Highlights card with special promo badge.</span>
                            </div>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Right Column: Live Storefront Card Preview & Publishing (4 Cols) -->
            <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-24">
                
                <!-- Live Frontend Preview Card -->
                <div class="naoero-card p-5 bg-white border border-slate-200 shadow-md space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-xs font-extrabold text-navy uppercase tracking-wider">Live Storefront Card Preview</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">React Preview</span>
                    </div>

                    <!-- The Product Card Mockup -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:shadow-md transition-all relative overflow-hidden space-y-3.5">
                        
                        <!-- Badges floating on card -->
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                                  :class="(type === 'gift_card' || category === 'Gift Card') ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-navy/10 text-navy border border-navy/20'"
                                  x-text="category || 'Apparel'">
                            </span>

                            <template x-if="discountPercent">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-xs" 
                                      x-text="'-' + discountPercent + '% OFF'">
                                </span>
                            </template>
                        </div>

                        <!-- Image Preview Box -->
                        <div class="h-44 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center p-3 overflow-hidden group">
                            <img :src="previewImageUrl" 
                                 :alt="title" 
                                 class="max-h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-105"
                                 onerror="this.src='/assets/giftcard/merch.png'">
                        </div>

                        <!-- Product Information -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs text-slate-400">
                                <span class="font-mono text-[11px]" x-text="sku || 'SKU: NA-HD-100'"></span>
                                <span class="text-amber-500 font-bold flex items-center gap-1">
                                    ★ <span class="text-slate-700 font-bold" x-text="rating || '5.0'"></span>
                                    <span class="text-[10px] text-slate-400" x-text="'(' + (reviewsCount || 0) + ')'"></span>
                                </span>
                            </div>

                            <h3 class="text-base font-extrabold text-navy line-clamp-1" x-text="title || 'Product Title'"></h3>
                            
                            <p class="text-xs text-slate-500 line-clamp-2 leading-relaxed" 
                               x-text="shortDescription || 'Short product tagline or voucher description will appear here...'"></p>
                        </div>

                        <!-- Pricing & CTA -->
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                            <div>
                                <div class="text-base font-black text-navy" x-text="'$' + (price || '0.00') + ' ' + currency"></div>
                                <template x-if="compareAtPrice && parseFloat(compareAtPrice) > parseFloat(price)">
                                    <div class="text-[11px] text-slate-400 line-through" x-text="'$' + compareAtPrice + ' ' + currency"></div>
                                </template>
                            </div>

                            <div class="px-3.5 py-2 rounded-xl bg-accent text-navy text-xs font-black shadow-xs flex items-center gap-1.5">
                                <span x-text="type === 'gift_card' ? 'Order Voucher' : 'Add to Bag'"></span>
                                <span>&rarr;</span>
                            </div>
                        </div>

                        <!-- Stock Status Footer -->
                        <div class="text-center pt-1">
                            <span class="text-[10px] font-bold" 
                                  :class="(type === 'gift_card' || stockQuantity > 10) ? 'text-emerald-600' : (stockQuantity > 0 ? 'text-amber-600' : 'text-rose-600')"
                                  x-text="(type === 'gift_card') ? '● Instant Digital eVoucher' : (stockQuantity > 0 ? '● In Stock (' + stockQuantity + ' units)' : '● Out of Stock')">
                            </span>
                        </div>

                    </div>

                </div>

                <!-- Primary Action Box -->
                <div class="naoero-card p-5 bg-white border border-slate-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-navy uppercase tracking-wider">Publishing Status:</span>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
                              :class="isActive ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600'"
                              x-text="isActive ? '● Ready to Publish' : '○ Save as Draft'">
                        </span>
                    </div>

                    <button type="submit" 
                            class="w-full py-3 px-4 rounded-xl bg-accent hover:bg-accent-hover text-navy font-black text-sm shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Create Product / Gift Card</span>
                    </button>

                    <a href="{{ route('admin.shop.index') }}" 
                       class="w-full py-2.5 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs text-center block transition-colors">
                        Discard & Back to Catalog
                    </a>
                </div>

            </div>

        </div>

    </form>

</div>
@endsection
