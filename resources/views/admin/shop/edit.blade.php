@extends('layouts.admin')

@section('title', 'Edit Product: ' . $product->title)

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{
    title: '{{ addslashes($product->title) }}',
    slug: '{{ addslashes($product->slug) }}',
    category: '{{ $product->category }}',
    type: '{{ $product->type }}',
    price: '{{ $product->price }}',
    compareAtPrice: '{{ $product->compare_at_price }}',
    currency: '{{ $product->currency ?: 'AUD' }}',
    stockQuantity: {{ $product->stock_quantity ?? 0 }},
    sku: '{{ addslashes($product->sku ?? '') }}',
    rating: '{{ $product->rating ?? '5.0' }}',
    reviewsCount: {{ $product->reviews_count ?? 0 }},
    sortOrder: {{ $product->sort_order ?? 0 }},
    shortDescription: '{{ addslashes($product->short_description ?? '') }}',
    imagePath: '{{ addslashes($product->image ?? '') }}',
    currentImageUrl: '{{ $product->image_url }}',
    localImagePreview: null,
    isRecommended: {{ $product->is_recommended ? 'true' : 'false' }},
    isFeatured: {{ $product->is_featured ? 'true' : 'false' }},
    isActive: {{ $product->is_active ? 'true' : 'false' }},
    
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
        return this.currentImageUrl || '/assets/giftcard/merch.png';
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
                    <span class="text-slate-400">Products</span>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit #{{ $product->id }}</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit: {{ $product->title }}</h1>
                <p class="text-xs text-ink-muted">Update product specifications, sale discounts, stock availability, and marketing tags.</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.shop.create') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm flex items-center gap-1.5">
                <span>+</span>
                <span>Add Another</span>
            </a>
            <button type="button" 
                    @click="document.getElementById('shop-edit-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-navy hover:bg-navy-900 text-white font-extrabold text-xs shadow-sm hover:shadow transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Update Changes</span>
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
                <span>Please correct the following errors:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Product Status & Quick Overview Header -->
    <div class="naoero-card p-4 bg-white border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-xl bg-slate-50 border border-slate-200 p-2 flex items-center justify-center shrink-0 overflow-hidden">
                <img src="{{ $product->image_url }}" alt="{{ $product->title }}" class="max-h-full max-w-full object-contain">
            </div>
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ ($product->type === 'gift_card' || $product->category === 'Gift Card') ? 'bg-amber-100 text-amber-900 border border-amber-300' : 'bg-blue-100 text-blue-900 border border-blue-300' }}">
                        {{ $product->category }}
                    </span>
                    <span class="text-xs font-mono font-bold text-slate-500">SKU: {{ $product->sku }}</span>
                    @if($product->is_recommended)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">★ Recommended</span>
                    @endif
                    @if($product->is_featured)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300">🏷️ Featured</span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-base font-extrabold text-navy">{{ $product->formatted_price }}</span>
                    @if($product->formatted_compare_at_price)
                        <span class="line-through text-slate-400 text-xs">{{ $product->formatted_compare_at_price }}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white">-{{ $product->discount_percent }}% OFF</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="/api/shop/products/{{ $product->slug }}" target="_blank" class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-bold transition-colors flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span>View REST JSON</span>
            </a>
        </div>
    </div>

    <!-- Main Form -->
    <form id="shop-edit-form" method="POST" action="{{ route('admin.shop.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            <!-- Left Column: Form Controls (8 Cols) -->
            <div class="lg:col-span-8 space-y-6">

                <!-- 1. Product Identity & Classification -->
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
                                   value="{{ old('title', $product->title) }}"
                                   required 
                                   class="w-full px-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                URL Slug (Identifier)
                            </label>
                            <input type="text" 
                                   name="slug" 
                                   x-model="slug"
                                   value="{{ old('slug', $product->slug) }}"
                                   class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                            <p class="text-[11px] text-slate-400 mt-1">API: <code>/api/shop/products/<span x-text="slug || 'slug'"></span></code></p>
                        </div>

                        <!-- SKU -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                SKU / Item Code
                            </label>
                            <input type="text" 
                                   name="sku" 
                                   x-model="sku"
                                   value="{{ old('sku', $product->sku) }}"
                                   class="w-full px-3.5 py-2.5 text-xs font-mono font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <!-- Category -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                    Category <span class="text-rose-500">*</span>
                                </label>
                                <a href="{{ route('admin.dropdowns.index', ['group' => 'shop_categories']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent">+ Manage</a>
                            </div>
                            <select name="category" 
                                    x-model="category"
                                    required 
                                    class="w-full px-3.5 py-2.5 text-xs font-bold rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" {{ $product->category === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Classification Type -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Classification Type <span class="text-rose-500">*</span>
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
                                <p class="text-xs text-ink-muted">Set selling price, compare-at regular price for discount badges, and stock.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Selling Price & Currency -->
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
                                        <option value="{{ $curr->value }}" {{ ($product->currency ?: 'AUD') === $curr->value ? 'selected' : '' }}>
                                            {{ $curr->value }} ({{ $curr->icon ?? '💵' }})
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number" 
                                       name="price" 
                                       x-model="price"
                                       value="{{ old('price', $product->price) }}"
                                       step="0.01" 
                                       min="0" 
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
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white" x-text="'-' + discountPercent + '% OFF'"></span>
                                </template>
                            </div>
                            <input type="number" 
                                   name="compare_at_price" 
                                   x-model="compareAtPrice"
                                   value="{{ old('compare_at_price', $product->compare_at_price) }}"
                                   step="0.01" 
                                   min="0" 
                                   placeholder="e.g. 75.00" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Stock Quantity -->
                        <div class="md:col-span-1">
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Available Stock
                            </label>
                            <div class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 bg-slate-50 font-semibold text-slate-700">
                                {{ $product->stock_quantity ?? 'Not tracked' }}
                            </div>
                            @if ($product->type !== 'gift_card' && ! in_array($product->category, ['Gift Card', 'Gift Cards'], true))
                                <a href="{{ route('admin.inventory.product', $product) }}" class="mt-1 inline-block text-[11px] font-bold text-navy hover:underline">Sizes, variants &amp; stock &rarr;</a>
                            @endif
                        </div>

                        <!-- Live Discount Savings Banner -->
                        <template x-if="discountPercent">
                            <div class="md:col-span-3 p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900 flex items-center justify-between text-xs font-bold">
                                <div class="flex items-center gap-2">
                                    <span class="text-base">🎉</span>
                                    <span>Discount Active: Customer saves <strong x-text="'$' + savingsAmount + ' ' + currency"></strong> per unit (<span x-text="'-' + discountPercent + '%'"></span>).</span>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-emerald-600 text-white font-extrabold text-[11px]">Badge active</span>
                            </div>
                        </template>

                        <!-- Sort Order -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Display Sort Priority
                            </label>
                            <input type="number" 
                                   name="sort_order" 
                                   x-model="sortOrder"
                                   value="{{ old('sort_order', $product->sort_order) }}"
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>

                        <!-- Rating -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Product Rating (1.0 - 5.0)
                            </label>
                            <input type="number" 
                                   name="rating" 
                                   x-model="rating"
                                   value="{{ old('rating', $product->rating) }}"
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
                                   value="{{ old('reviews_count', $product->reviews_count) }}"
                                   min="0" 
                                   class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-semibold">
                        </div>
                    </div>
                </div>

                <!-- 3. Media & Imagery -->
                <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="w-7 h-7 rounded-lg bg-navy/10 text-navy font-black text-xs flex items-center justify-center">3</div>
                            <div>
                                <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Product Media & Graphics</h2>
                                <p class="text-xs text-ink-muted">Upload a new photo file or link an existing static asset.</p>
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
                                <span class="text-xs font-extrabold text-slate-800 block">Upload New Photo File</span>
                                <span class="text-[11px] text-slate-500">Leaves current image unchanged if blank</span>
                            </div>
                            <input type="file" 
                                   name="image_file" 
                                   accept="image/*"
                                   @change="handleFileUpload($event)"
                                   class="block w-full text-xs text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy-900 cursor-pointer">
                        </div>

                        <!-- Option B: Static Asset Path -->
                        <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2.5">
                            <span class="text-xs font-extrabold text-slate-800 block">Or Static Asset Path</span>
                            <input type="text" 
                                   name="image" 
                                   x-model="imagePath"
                                   value="{{ old('image', $product->image) }}"
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
                                   value="{{ old('short_description', $product->short_description) }}"
                                   class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        </div>

                        <!-- WYSIWYG Full Description -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                                Detailed Overview & Care Instructions (Quill WYSIWYG)
                            </label>
                            <textarea name="description" 
                                      rows="5" 
                                      class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('description', $product->description) }}</textarea>
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
                              x-text="isActive ? '● Live in Store' : '○ Deactivated'">
                        </span>
                    </div>

                    <button type="submit" 
                            class="w-full py-3 px-4 rounded-xl bg-accent hover:bg-accent-hover text-navy font-black text-sm shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center justify-center gap-2">
                        <svg class="w-5 h-5 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Update Product / Gift Card</span>
                    </button>

                    <a href="{{ route('admin.shop.index') }}" 
                       class="w-full py-2.5 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs text-center block transition-colors">
                        Cancel Changes
                    </a>
                </div>

            </div>

        </div>

    </form>

</div>
@endsection
