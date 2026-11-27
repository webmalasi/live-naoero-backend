@extends('layouts.admin')

@section('title', 'Edit Special Offer - ' . $special->title)

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    title: '{{ addslashes(old('title', $special->title)) }}',
    slug: '{{ addslashes(old('slug', $special->slug)) }}',
    badge: '{{ addslashes(old('badge', $special->badge ?? 'Limited Time Deal')) }}',
    imageUrl: '{{ addslashes(old('image_url', filter_var($special->image, FILTER_VALIDATE_URL) ? $special->image : '')) }}',
    currentImage: '{{ $special->image_url }}',
    localImagePreview: null,
    origin: '{{ addslashes(old('origin', $special->origin ?? '')) }}',
    destination: '{{ addslashes(old('destination', $special->destination ?? '')) }}',
    price: '{{ old('price', $special->price) }}',
    currency: '{{ old('currency', $special->currency ?? 'AUD') }}',
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
    generateSlug() {
        if (!this.slug || this.slug === '') {
            this.slug = this.title.toLowerCase()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    },
    setPresetBadge(b) {
        this.badge = b;
    },
    setPresetImage(url) {
        this.imageUrl = url;
        this.localImagePreview = null;
    }
}">

    <!-- Top Header & Breadcrumbs -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.specials.index') }}" 
               class="p-2.5 rounded-xl bg-white border border-slate-200 text-slate-600 hover:text-navy hover:bg-slate-50 hover:border-slate-300 transition-all shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-0.5">
                    <a href="{{ route('admin.specials.index') }}" class="hover:text-navy transition-colors">Special Offers</a>
                    <span>/</span>
                    <span class="text-navy font-bold">Edit Offer #{{ $special->id }}</span>
                </div>
                <h1 class="text-2xl font-extrabold text-navy tracking-tight">Edit Special Flight Offer</h1>
                <p class="text-xs text-ink-muted">{{ $special->title }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.specials.index') }}" 
               class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition-all shadow-sm">
                Cancel
            </a>
            <button type="button" 
                    @click="document.getElementById('special-edit-form').submit()" 
                    class="px-5 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-xs shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Update Special Offer</span>
            </button>
        </div>
    </div>

    <!-- Error Summary (if any) -->
    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs shadow-sm">
            <div class="flex items-center gap-2 font-bold mb-1 text-sm text-rose-900">
                <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>Please correct the form errors:</span>
            </div>
            <ul class="list-disc list-inside space-y-0.5 text-rose-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="special-edit-form" method="POST" action="{{ route('admin.specials.update', $special) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- SECTION 1: Route & Flight Basics -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Flight Deal & Route Details</h2>
                        <p class="text-xs text-ink-muted">Define the offer title, departure & arrival destination pair, and promotional badge.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Offer Title -->
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Offer Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" 
                           name="title" 
                           x-model="title"
                           @input="generateSlug()"
                           value="{{ old('title', $special->title) }}" 
                           required
                           class="w-full px-3.5 py-2.5 text-sm font-bold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        URL Slug (Web & API Key)
                    </label>
                    <input type="text" 
                           name="slug" 
                           x-model="slug"
                           value="{{ old('slug', $special->slug) }}" 
                           class="w-full px-3.5 py-2.5 text-xs font-mono rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Badge Text with Presets -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Badge Text / Callout
                    </label>
                    <input type="text" 
                           name="badge" 
                           x-model="badge"
                           value="{{ old('badge', $special->badge) }}" 
                           placeholder="e.g. 20% OFF or Limited Time Deal" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                        <span class="text-[10px] font-bold text-slate-400">Presets:</span>
                        <button type="button" @click="setPresetBadge('Limited Time Deal')" class="text-[10px] px-2 py-0.5 rounded-md bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors">Limited Time Deal</button>
                        <button type="button" @click="setPresetBadge('20% OFF')" class="text-[10px] px-2 py-0.5 rounded-md bg-amber-50 hover:bg-amber-100 text-amber-900 font-bold transition-colors">20% OFF</button>
                        <button type="button" @click="setPresetBadge('Flash Sale')" class="text-[10px] px-2 py-0.5 rounded-md bg-rose-50 hover:bg-rose-100 text-rose-800 font-bold transition-colors">Flash Sale</button>
                        <button type="button" @click="setPresetBadge('Island Hopper')" class="text-[10px] px-2 py-0.5 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-800 font-bold transition-colors">Island Hopper</button>
                    </div>
                </div>

                <!-- Origin -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Departure City / Origin
                    </label>
                    <div class="relative">
                        <input type="text" 
                               name="origin" 
                               x-model="origin"
                               value="{{ old('origin', $special->origin) }}" 
                               placeholder="e.g. Brisbane (BNE)" 
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        <span class="absolute left-3 top-2.5 text-slate-400 text-sm">🛫</span>
                    </div>
                </div>

                <!-- Destination -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Arrival City / Destination
                    </label>
                    <div class="relative">
                        <input type="text" 
                               name="destination" 
                               x-model="destination"
                               value="{{ old('destination', $special->destination) }}" 
                               placeholder="e.g. Nauru (INU)" 
                               class="w-full pl-9 pr-3.5 py-2.5 text-sm font-semibold rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                        <span class="absolute left-3 top-2.5 text-slate-400 text-sm">🛬</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Pricing, Currency & Validity Window -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">2</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Promotional Fare & Schedule Validity</h2>
                        <p class="text-xs text-ink-muted">Set ticket airfare starting price, transactional currency, and promo campaign dates.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Currency & Fare Price -->
                <div class="md:col-span-1">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                            Fare Price & Currency
                        </label>
                        <a href="{{ route('admin.dropdowns.index', ['group' => 'currencies']) }}" target="_blank" class="text-[11px] font-bold text-navy hover:text-accent-hover hover:underline">
                            + Manage
                        </a>
                    </div>
                    <div class="flex items-center rounded-xl border border-slate-200 focus-within:ring-2 focus-within:ring-accent/40 focus-within:border-accent overflow-hidden">
                        <select name="currency" class="px-3 py-2.5 text-xs font-black bg-slate-50 border-r border-slate-200 focus:outline-none text-slate-700">
                            @php
                                $selectedCurrency = old('currency', $special->currency ?? 'AUD');
                            @endphp
                            @foreach($currencies as $curr)
                                <option value="{{ $curr->value }}" {{ $selectedCurrency === $curr->value ? 'selected' : '' }}>
                                    {{ $curr->value }}
                                </option>
                            @endforeach
                        </select>
                        <input type="number" 
                               step="0.01"
                               min="0"
                               name="price" 
                               x-model="price"
                               value="{{ old('price', $special->price) }}" 
                               placeholder="499.00" 
                               class="w-full px-3 py-2.5 text-sm font-extrabold focus:outline-none font-mono">
                    </div>
                </div>

                <!-- Valid From -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Deal Valid From
                    </label>
                    <input type="date" 
                           name="valid_from" 
                           value="{{ old('valid_from', $special->valid_from ? $special->valid_from->format('Y-m-d') : '') }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>

                <!-- Valid Until -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Deal Expiry / Valid Until
                    </label>
                    <input type="date" 
                           name="valid_until" 
                           value="{{ old('valid_until', $special->valid_until ? $special->valid_until->format('Y-m-d') : '') }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                </div>
            </div>
        </div>

        <!-- SECTION 3: Promotional Description & Content -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">3</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Promotional Text & Deal Highlights</h2>
                        <p class="text-xs text-ink-muted">Describe package highlights, flight inclusions, stopover destinations, or special perks.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Offer Highlights & Body (WYSIWYG Rich Editor)
                </label>
                <textarea name="description" 
                          rows="5" 
                          placeholder="Describe the promotion in detail: island highlights, baggage allowances, booking periods..." 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">{{ old('description', $special->description) }}</textarea>
            </div>
        </div>

        <!-- SECTION 4: Terms, Conditions & Baggage Policies -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">4</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Fare Conditions & Terms</h2>
                        <p class="text-xs text-ink-muted">Specify fine-print fare rules, blackout travel dates, cancellation rules, and refund policy.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Terms & Conditions (WYSIWYG Rich Editor)
                </label>
                <textarea name="terms" 
                          rows="4" 
                          placeholder="e.g. Fares are subject to seat availability. Includes 23kg checked baggage and 7kg carry-on. Non-refundable after departure..." 
                          class="wysiwyg-editor w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent text-xs">{{ old('terms', $special->terms) }}</textarea>
            </div>
        </div>

        <!-- SECTION 5: Promotional Banner Image & Visuals -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">5</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Promotional Banner Image</h2>
                        <p class="text-xs text-ink-muted">Upload high-resolution route banner photography or provide an external image URL.</p>
                    </div>
                </div>
            </div>

            <!-- Live Image Preview Card -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col md:flex-row items-center gap-5">
                <div class="w-full md:w-64 h-36 rounded-xl bg-navy-950 overflow-hidden relative shadow-md shrink-0 flex items-center justify-center border border-slate-200">
                    <template x-if="localImagePreview">
                        <img :src="localImagePreview" alt="Upload Preview" class="w-full h-full object-cover">
                    </template>
                    <template x-if="!localImagePreview && imageUrl">
                        <img :src="imageUrl" alt="URL Preview" class="w-full h-full object-cover" onerror="this.style.display='none'">
                    </template>
                    <template x-if="!localImagePreview && !imageUrl && currentImage">
                        <img :src="currentImage" alt="Current Banner" class="w-full h-full object-cover">
                    </template>

                    <!-- Overlay Tag Simulation -->
                    <div class="absolute bottom-2 left-2 right-2 flex items-center justify-between text-white text-[10px] font-bold bg-navy-950/80 px-2 py-1 rounded-lg backdrop-blur-sm">
                        <span class="truncate" x-text="(origin || 'Origin') + ' → ' + (destination || 'Dest')"></span>
                        <span class="text-accent shrink-0 font-mono" x-text="price ? (currency + ' $' + price) : ''"></span>
                    </div>
                </div>

                <div class="flex-1 space-y-3 w-full">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Replace Image File (JPG, PNG, WebP up to 5MB)
                        </label>
                        <input type="file" 
                               name="image_file" 
                               accept="image/*"
                               @change="handleFileUpload($event)"
                               class="w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-navy file:text-white hover:file:bg-navy-900 cursor-pointer">
                    </div>

                    <div class="pt-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Or External Image URL
                        </label>
                        <input type="url" 
                               name="image_url" 
                               x-model="imageUrl"
                               value="{{ old('image_url', filter_var($special->image, FILTER_VALIDATE_URL) ? $special->image : '') }}" 
                               placeholder="https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80" 
                               class="w-full px-3.5 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent">
                    </div>

                    <div class="flex items-center gap-1.5 pt-1 flex-wrap">
                        <span class="text-[10px] font-bold text-slate-400">Quick Unsplash Presets:</span>
                        <button type="button" @click="setPresetImage('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=80')" class="text-[10px] px-2 py-0.5 rounded-md bg-white border border-slate-200 hover:border-navy text-slate-600 font-semibold transition-colors">🌴 Tropical Lagoon</button>
                        <button type="button" @click="setPresetImage('https://images.unsplash.com/photo-1436491865332-7a61a109cc05?auto=format&fit=crop&w=1200&q=80')" class="text-[10px] px-2 py-0.5 rounded-md bg-white border border-slate-200 hover:border-navy text-slate-600 font-semibold transition-colors">✈️ Boeing Flight</button>
                        <button type="button" @click="setPresetImage('https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?auto=format&fit=crop&w=1200&q=80')" class="text-[10px] px-2 py-0.5 rounded-md bg-white border border-slate-200 hover:border-navy text-slate-600 font-semibold transition-colors">🏙️ Brisbane Gateway</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 6: Publication & Visibility Settings -->
        <div class="naoero-card p-6 bg-white border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-navy-50 text-navy font-black text-xs flex items-center justify-center">6</div>
                    <div>
                        <h2 class="text-sm font-extrabold text-navy uppercase tracking-wider">Display & Publishing Controls</h2>
                        <p class="text-xs text-ink-muted">Set sequence order and whether this special offer appears live on the specials page.</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-center">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        Display Priority / Sort Order
                    </label>
                    <input type="number" 
                           name="sort_order" 
                           value="{{ old('sort_order', $special->sort_order) }}" 
                           class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent font-bold">
                    <p class="text-[11px] text-slate-400 mt-1">Lower numbers appear first on the Specials grid.</p>
                </div>

                <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="block text-xs font-bold text-navy">Publish Live on Website</span>
                        <span class="text-[11px] text-slate-500">Enable to make this deal immediately visible to passengers.</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0 ml-4">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $special->is_active) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- Bottom Action Buttons -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('admin.specials.index') }}" 
               class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-600 font-bold text-sm hover:bg-slate-50 transition-colors shadow-sm">
                Cancel
            </a>
            <button type="submit" 
                    class="px-6 py-2.5 rounded-xl bg-accent hover:bg-accent-hover text-navy font-extrabold text-sm shadow-md shadow-accent/20 hover:shadow-lg transition-all flex items-center gap-2">
                <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span>Update Special Offer</span>
            </button>
        </div>
    </form>

</div>
@endsection
