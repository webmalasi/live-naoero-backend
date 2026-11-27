<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-[#F5F7FB]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Admin Portal') - Naoero Airlines</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">

    <!-- Google Fonts: Manrope -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS & Alpine.js -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Manrope', 'sans-serif'],
                    },
                    colors: {
                        navy: {
                            DEFAULT: '#012169',
                            50: '#EEF3FF',
                            100: '#D9E4FF',
                            200: '#B8D0FF',
                            300: '#85B1FF',
                            400: '#4D8BFF',
                            500: '#2563EB',
                            600: '#1347BF',
                            700: '#0C3699',
                            800: '#012169',
                            900: '#0A1F44',
                            950: '#06132D',
                        },
                        accent: {
                            DEFAULT: '#FFC631',
                            hover: '#F2B71E',
                            soft: '#FFE9A8',
                            light: '#FFF9E5',
                        },
                        ink: {
                            DEFAULT: '#0B1730',
                            muted: '#5B6472',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Quill WYSIWYG Editor -->
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>

    <style>
        body {
            font-family: 'Manrope', sans-serif;
            background-color: #F5F7FB;
            color: #0B1730;
        }
        .naoero-sidebar-scrollbar::-webkit-scrollbar {
            width: 4px;
        }
        .naoero-sidebar-scrollbar::-webkit-scrollbar-thumb {
            background-color: rgba(255, 198, 49, 0.25);
            border-radius: 9999px;
        }
        .naoero-card {
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-radius: 1rem;
            box-shadow: 0 4px 20px -2px rgba(1, 33, 105, 0.04), 0 2px 6px -1px rgba(1, 33, 105, 0.02);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .naoero-card:hover {
            box-shadow: 0 10px 25px -3px rgba(1, 33, 105, 0.07), 0 4px 10px -2px rgba(1, 33, 105, 0.03);
        }
        [x-cloak] { display: none !important; }

        /* Custom Quill WYSIWYG Styling */
        .quill-wrapper {
            position: relative;
            border-radius: 0.75rem;
            background: #FFFFFF;
            transition: all 0.15s ease-in-out;
            width: 100%;
        }
        .quill-wrapper:focus-within {
            box-shadow: 0 0 0 2px rgba(255, 198, 49, 0.45);
        }
        .quill-wrapper:focus-within .ql-toolbar.ql-snow {
            border-color: #FFC631 !important;
        }
        .quill-wrapper:focus-within .ql-container.ql-snow {
            border-color: #FFC631 !important;
        }
        .ql-toolbar.ql-snow {
            border-top-left-radius: 0.75rem;
            border-top-right-radius: 0.75rem;
            border: 1px solid #E2E8F0 !important;
            border-bottom: 1px solid #EEF2F6 !important;
            background-color: #F8FAFC;
            padding: 5px 10px !important;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 2px 4px;
        }
        .ql-snow .ql-formats {
            margin-right: 6px !important;
            margin-bottom: 0 !important;
            display: inline-flex;
            align-items: center;
        }
        .ql-snow.ql-toolbar button, .ql-snow .ql-toolbar button {
            height: 24px !important;
            width: 24px !important;
            padding: 3px !important;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .ql-snow.ql-toolbar button:hover, .ql-snow .ql-toolbar button.ql-active {
            background-color: #EEF3FF !important;
            color: #012169 !important;
        }
        .ql-snow.ql-toolbar button:hover .ql-stroke, .ql-snow .ql-toolbar button.ql-active .ql-stroke {
            stroke: #012169 !important;
        }
        .ql-snow.ql-toolbar button:hover .ql-fill, .ql-snow .ql-toolbar button.ql-active .ql-fill {
            fill: #012169 !important;
        }
        .ql-snow .ql-picker-label:hover,
        .ql-snow .ql-picker-label.ql-active {
            color: #012169 !important;
        }
        .ql-snow .ql-picker-label:hover .ql-stroke,
        .ql-snow .ql-picker-label.ql-active .ql-stroke {
            stroke: #012169 !important;
        }
        .ql-container.ql-snow {
            border-bottom-left-radius: 0.75rem;
            border-bottom-right-radius: 0.75rem;
            border: 1px solid #E2E8F0 !important;
            border-top: none !important;
            font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            font-size: 0.875rem !important;
            background-color: #FFFFFF;
        }
        .ql-editor {
            min-height: 140px;
            color: #0F172A;
            line-height: 1.6;
            padding: 10px 14px;
        }
        .ql-editor.ql-blank::before {
            color: #94A3B8;
            font-style: normal;
            font-weight: 400;
            left: 14px;
            right: 14px;
        }
        .quill-compact .ql-toolbar.ql-snow {
            padding: 4px 8px !important;
            background-color: #F8FAFC;
        }
        .quill-compact .ql-editor {
            min-height: 75px !important;
            max-height: 220px;
            overflow-y: auto;
            padding: 8px 12px !important;
            font-size: 0.8125rem !important;
        }
    </style>
    @stack('styles')
@if(request()->boolean('embed'))
    <style>aside, header.sticky, [data-embed-hide] { display: none !important; } body { background: #fff; }</style>
@endif
</head>
<body class="h-full antialiased overflow-x-hidden" x-data="{
    sidebarOpen: false,
    profileDropdown: false,
    openMenus: {
        pages: {{ request()->routeIs(...array_merge(app(\App\Services\Content\PageUsage::class)->routePatterns(), ['admin.banners.*', 'admin.notices.*'])) ? 'true' : 'false' }},
        news: {{ request()->routeIs('admin.posts.*') ? 'true' : 'false' }},
        flights: {{ (request()->routeIs('admin.flight-bookings.*') || request()->routeIs('admin.refund-requests.*') || request()->routeIs('admin.locations.*')) ? 'true' : 'false' }},
        shop: {{ (request()->routeIs('admin.shop.*') || request()->routeIs('admin.sales.*') || request()->routeIs('admin.inventory.*')) ? 'true' : 'false' }},
        commercial: {{ (request()->routeIs('admin.inquiries.*') || request()->routeIs('admin.newsletter.*')) ? 'true' : 'false' }},
        careers: {{ (request()->routeIs('admin.careers.*') || request()->routeIs('admin.job-applications.*')) ? 'true' : 'false' }},
        access: {{ (request()->routeIs('admin.customers.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*')) ? 'true' : 'false' }},
        system: {{ (request()->routeIs('admin.system-logs.*') || request()->routeIs('admin.zenith-events.*') || request()->routeIs('admin.push.*') || request()->routeIs('admin.settings.*') || request()->routeIs('admin.notices.*') || request()->routeIs('admin.activity-logs.*') || request()->routeIs('admin.system.*') || request()->routeIs('admin.profile.*')) ? 'true' : 'false' }}
    },
    toggleMenu(name) {
        this.openMenus[name] = !this.openMenus[name];
    }
}">

    <div class="min-h-full flex flex-col lg:flex-row">

        <!-- Mobile Sidebar Backdrop -->
        <div x-show="sidebarOpen" 
             x-cloak
             @click="sidebarOpen = false" 
             class="fixed inset-0 z-40 bg-navy-950/70 backdrop-blur-sm lg:hidden transition-opacity"
             x-transition:enter="transition-opacity ease-linear duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"></div>

        <!-- SIDEBAR -->
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="fixed inset-y-0 left-0 z-50 w-72 bg-[#012169] text-white flex flex-col justify-between transition-transform duration-300 ease-in-out lg:static lg:inset-auto lg:translate-x-0 shadow-2xl lg:shadow-none shrink-0">
            
            <div class="flex flex-col h-full">
                <!-- Brand Header -->
                <div class="h-20 flex items-center justify-between px-5 bg-white border-b border-white/10 shrink-0">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 group">
                        <img src="{{ asset('assets/logo.png') }}" alt="Naoero Airlines" class="h-14 w-auto object-contain group-hover:scale-105 transition-transform">
                    </a>

                    <!-- Close Button on Mobile -->
                    <button @click="sidebarOpen = false" class="lg:hidden p-1.5 rounded-lg text-navy/70 hover:text-navy hover:bg-navy/10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Navigation Links with Menu & Submenu Accordions -->
                <div class="px-3.5 py-5 overflow-y-auto flex-1 naoero-sidebar-scrollbar space-y-4">
                    
                    <!-- 1. Flight Deck & Dashboard -->
                    <div>
                        <a href="{{ route('admin.dashboard') }}" 
                            class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-accent text-navy font-black shadow-md shadow-accent/20' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span>Dashboard</span>
                            </div>
                            <span class="text-[10px] uppercase font-bold tracking-widest px-1.5 py-0.5 rounded {{ request()->routeIs('admin.dashboard') ? 'bg-navy/15 text-navy font-black' : 'bg-white/10 text-white/70' }}">Live</span>
                        </a>
                    </div>

                    @php
                        // Pages first: everything a visitor reads is reached from "Website & App Pages".
                        // The library holds the lists those pages show (cards, fleet, offers…), each edited once.
                        $navLink = fn (string $label, string $href, array $routes, $count = null, string $icon = '') => compact('label', 'href', 'routes', 'count', 'icon');
                        // Shown only to staff who may open it (the same rule the server enforces).
                        $canOpen = fn (string $route) => \App\Services\Auth\AdminAccess::allows(auth()->user(), $route);
                        $navGroups = [
                            ['key' => 'pages', 'label' => 'Website & App Pages', 'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z', 'items' => array_values(array_filter([
                                $canOpen('admin.content-pages.index') ? $navLink('All Pages & Lists', route('admin.content-pages.index'), app(\App\Services\Content\PageUsage::class)->routePatterns(), \App\Models\ContentPage::count() + count(\App\Services\Content\PageUsage::LIST_PAGES)) : null,
                                $canOpen('admin.banners.index') ? $navLink('Banners & Hero Images', route('admin.banners.index'), ['admin.banners.*'], \App\Models\PageBanner::count()) : null,
                                $canOpen('admin.notices.index') ? $navLink('Notice Bar & Alerts', route('admin.notices.index'), ['admin.notices.*'], \App\Models\Notice::count()) : null,
                            ]))],
                            ['key' => 'news', 'label' => 'News & Blog', 'icon' => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9', 'items' => array_values(array_filter([
                                $canOpen('admin.posts.index') ? $navLink('News & Blog Posts', route('admin.posts.index'), ['admin.posts.*'], \App\Models\Post::count()) : null,
                            ]))],
                            ['key' => 'flights', 'label' => 'Flight Network', 'icon' => 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z', 'items' => array_values(array_filter([
                                $canOpen('admin.flight-bookings.index') ? $navLink('Flight Sales & Bookings', route('admin.flight-bookings.index'), ['admin.flight-bookings.*'], \Illuminate\Support\Facades\Schema::hasTable('bookings') ? \Illuminate\Support\Facades\DB::table('bookings')->count() : 0) : null,
                                $canOpen('admin.refund-requests.index') ? $navLink('Refund Requests', route('admin.refund-requests.index'), ['admin.refund-requests.*'], \Illuminate\Support\Facades\Schema::hasTable('refund_requests') ? \Illuminate\Support\Facades\DB::table('refund_requests')->whereIn('status', ['submitted', 'in_review'])->count() : 0) + ['warn' => true] : null,
                                $canOpen('admin.locations.index') ? $navLink('Locations Master', route('admin.locations.index'), ['admin.locations.*'], \App\Models\Country::count()) : null,
                            ]))],
                        ];
                    @endphp
                    @foreach (array_filter($navGroups, fn ($g) => $g['items'] !== []) as $group)
                        @php
                            $groupActive = collect($group['items'])->contains(fn ($i) => request()->routeIs(...$i['routes']));
                        @endphp
                        <div class="space-y-1">
                            <button type="button"
                                    @click="toggleMenu('{{ $group['key'] }}')"
                                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $groupActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 shrink-0 {{ $groupActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $group['icon'] }}"/>
                                    </svg>
                                    <span class="tracking-wide">{{ $group['label'] }}</span>
                                </div>
                                <svg class="w-4 h-4 transition-transform duration-200"
                                     :class="openMenus.{{ $group['key'] }} ? 'rotate-90 text-accent' : 'text-white/40'"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <div x-show="openMenus.{{ $group['key'] }}" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                                @foreach ($group['items'] as $item)
                                    @php $on = request()->routeIs(...$item['routes']); @endphp
                                    <a href="{{ $item['href'] }}"
                                       class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $on ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                        <span class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $on ? 'bg-navy' : 'bg-accent' }}"></span>
                                            <span>{{ $item['label'] }}</span>
                                        </span>
                                        @if (is_int($item['count']) && ($item['count'] > 0 || empty($item['warn'])))
                                            <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ $on ? 'bg-navy/20 text-navy font-bold' : (! empty($item['warn']) ? 'bg-amber-400 text-navy font-bold' : 'bg-white/10 text-white/80') }}">{{ $item['count'] }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <!-- 4. Menu: Shop & Gift Cards (Accordion) -->
                    @if ($canOpen('admin.shop.index') || $canOpen('admin.shop.orders') || $canOpen('admin.sales.index') || $canOpen('admin.inventory.index'))
                    @php
                        $isShopActive = request()->routeIs('admin.shop.*') || request()->routeIs('admin.sales.*') || request()->routeIs('admin.inventory.*');
                        $totalShopProducts = \App\Models\ShopProduct::count();
                        $newGiftOrders = \App\Models\GiftCardOrder::pending()->count();
                    @endphp
                    <div class="space-y-1">
                        <button type="button" 
                                @click="toggleMenu('shop')" 
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $isShopActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0 {{ $isShopActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <span class="tracking-wide">Shop & Gift Cards</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($newGiftOrders > 0)
                                    <span class="w-2 h-2 rounded-full bg-accent animate-pulse"></span>
                                @endif
                                <svg class="w-4 h-4 transition-transform duration-200" 
                                     :class="openMenus.shop ? 'rotate-90 text-accent' : 'text-white/40'" 
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </button>

                        <!-- Submenu: Shop & Gift Cards -->
                        <div x-show="openMenus.shop" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                            @canroute('admin.shop.index')
                            <!-- Products & Merch -->
                            <a href="{{ route('admin.shop.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ (request()->routeIs('admin.shop.index') || request()->routeIs('admin.shop.edit')) ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.shop.index') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Products & Merch</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.shop.index') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ $totalShopProducts }}</span>
                            </a>
                            @canroute('admin.shop.create')
                            <!-- Add Product -->
                            <a href="{{ route('admin.shop.create') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.shop.create') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/60 hover:bg-white/10 hover:text-white' }}">
                                <span class="text-accent font-bold">+</span>
                                <span>Add New Item</span>
                            </a>
                            @endcanroute
                            @endcanroute
                            @canroute('admin.shop.orders')
                            <!-- Gift Card Orders -->
                            <a href="{{ route('admin.shop.orders') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.shop.orders') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.shop.orders') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Gift Card Orders</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.shop.orders') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\GiftCardOrder::count() }}</span>
                            </a>
                            @endcanroute
                            @canroute('admin.sales.index')
                                @php
                                    // Orders needing staff: paid merchandise to ship, and payments under review.
                                    $salesAttention = \Illuminate\Support\Facades\Schema::hasTable('shop_orders')
                                        ? \Illuminate\Support\Facades\DB::table('shop_orders')->where(fn ($q) => $q->whereIn('status', ['paid', 'partially_refunded'])->where('shipping_status', 'processing'))->orWhere('status', 'payment_review')->count()
                                        : 0;
                                @endphp
                                @php
                                    $returnsAttention = \Illuminate\Support\Facades\Schema::hasTable('shop_return_requests') ? \Illuminate\Support\Facades\DB::table('shop_return_requests')->whereIn('status', ['requested', 'received'])->count() : 0;
                                    $supportAttention = \Illuminate\Support\Facades\Schema::hasTable('shop_support_requests') ? \Illuminate\Support\Facades\DB::table('shop_support_requests')->where('status', 'open')->count() : 0;
                                @endphp
                                @foreach (['admin.sales.index' => ['Shop Sales & Orders', $salesAttention], 'admin.sales.returns' => ['Returns & Exchanges', $returnsAttention], 'admin.sales.support' => ['Help Requests', $supportAttention], 'admin.sales.reviews' => ['Product Reviews', null], 'admin.sales.payments' => ['Payments & Refunds', null], 'admin.sales.activity' => ['Shop Activity Log', null]] as $salesRoute => [$salesLabel, $salesBadge])
                                    @php $salesActive = request()->routeIs($salesRoute, $salesRoute.'.*') || ($salesRoute === 'admin.sales.index' && request()->routeIs('admin.sales.show')); @endphp
                                    <a href="{{ route($salesRoute) }}"
                                       class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $salesActive ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                        <span class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $salesActive ? 'bg-navy' : 'bg-accent' }}"></span>
                                            <span>{{ $salesLabel }}</span>
                                        </span>
                                        @if ($salesBadge)
                                            <span class="px-1.5 py-0.2 rounded-full text-[11px] font-bold {{ $salesActive ? 'bg-navy/20 text-navy' : 'bg-accent text-navy' }}" title="To ship or under review">{{ $salesBadge }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            @endcanroute
                            @canroute('admin.inventory.index')
                                @php
                                    // Merchandise variants that are low or sold out.
                                    $stockAttention = \Illuminate\Support\Facades\Schema::hasTable('shop_product_variants')
                                        ? \Illuminate\Support\Facades\DB::table('shop_product_variants as v')->join('shop_products as p', 'p.id', '=', 'v.shop_product_id')
                                            ->where('v.is_active', true)->where('p.is_active', true)->where('v.track_inventory', true)
                                            ->whereRaw('v.on_hand - v.reserved <= coalesce(v.low_stock_threshold, ?)', [(int) config('naoero.shop.low_stock_threshold', 5)])->count()
                                        : 0;
                                @endphp
                                @foreach (['admin.inventory.index' => ['Inventory', $stockAttention, ['admin.inventory.index', 'admin.inventory.stock', 'admin.inventory.product', 'admin.inventory.movements', 'admin.inventory.alerts']], 'admin.inventory.purchase-orders.index' => ['Purchase Orders', null, ['admin.inventory.purchase-orders.*', 'admin.inventory.suppliers']], 'admin.inventory.counts.index' => ['Stock Counts', null, ['admin.inventory.counts.*']]] as $inventoryRoute => [$inventoryLabel, $inventoryBadge, $inventoryPatterns])
                                    @continue (! $canOpen($inventoryRoute))
                                    @php $inventoryActive = request()->routeIs(...$inventoryPatterns); @endphp
                                    <a href="{{ route($inventoryRoute) }}"
                                       class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ $inventoryActive ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                        <span class="flex items-center gap-2">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $inventoryActive ? 'bg-navy' : 'bg-accent' }}"></span>
                                            <span>{{ $inventoryLabel }}</span>
                                        </span>
                                        @if ($inventoryBadge)
                                            <span class="px-1.5 py-0.2 rounded-full text-[11px] font-bold {{ $inventoryActive ? 'bg-navy/20 text-navy' : 'bg-rose-400 text-white' }}" title="Low or sold out">{{ $inventoryBadge }}</span>
                                        @endif
                                    </a>
                                @endforeach
                            @endcanroute
                        </div>
                    </div>
                    @endif

                    <!-- 5. Menu: Commercial & Inquiries (Accordion) -->
                    @if ($canOpen('admin.inquiries.index') || $canOpen('admin.newsletter.index'))
                    @php
                        $isCommercialActive = request()->routeIs('admin.inquiries.*') || request()->routeIs('admin.newsletter.*');
                        $unreadInquiries = \App\Models\Inquiry::where('status', 'new')->count();
                    @endphp
                    <div class="space-y-1">
                        <button type="button" 
                                @click="toggleMenu('commercial')" 
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $isCommercialActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0 {{ $isCommercialActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="tracking-wide">Commercial & Inbox</span>
                            </div>
                            <div class="flex items-center gap-2">
                                @if($unreadInquiries > 0)
                                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                @endif
                                <svg class="w-4 h-4 transition-transform duration-200" 
                                     :class="openMenus.commercial ? 'rotate-90 text-accent' : 'text-white/40'" 
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </button>

                        <!-- Submenu: Commercial & Inquiries -->
                        <div x-show="openMenus.commercial" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                            @canroute('admin.inquiries.index')
                            <!-- Inquiries Inbox -->
                            <a href="{{ route('admin.inquiries.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.inquiries.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.inquiries.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Inquiries Inbox</span>
                                </span>
                                @if($unreadInquiries > 0)
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-rose-500 text-white font-black animate-pulse">{{ $unreadInquiries }} new</span>
                                @else
                                    <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.inquiries.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\Inquiry::count() }}</span>
                                @endif
                            </a>
                            @endcanroute

                            @canroute('admin.newsletter.index')
                            <!-- Newsletter Subscribers -->
                            <a href="{{ route('admin.newsletter.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.newsletter.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.newsletter.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Newsletter Leads</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.newsletter.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\NewsletterSubscriber::count() }}</span>
                            </a>
                            @endcanroute
                        </div>
                    </div>
                    @endif

                    <!-- 5. Menu: Careers & HR Hub (Accordion) -->
                    @canroute('admin.careers.index')
                    @php
                        $isCareersActive = request()->routeIs('admin.careers.*') || request()->routeIs('admin.job-applications.*');
                    @endphp
                    <div class="space-y-1">
                        <button type="button" 
                                @click="toggleMenu('careers')" 
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $isCareersActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0 {{ $isCareersActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                </svg>
                                <span class="tracking-wide">Careers & HR</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200" 
                                 :class="openMenus.careers ? 'rotate-90 text-accent' : 'text-white/40'" 
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <!-- Submenu: Careers & HR -->
                        <div x-show="openMenus.careers" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                            <!-- Job Openings -->
                            <a href="{{ route('admin.careers.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.careers.index') || request()->routeIs('admin.careers.edit') || request()->routeIs('admin.careers.show') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.careers.index') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Job Openings</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.careers.index') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\Career::count() }}</span>
                            </a>
                            @canroute('admin.careers.create')
                            <!-- Post New Job -->
                            <a href="{{ route('admin.careers.create') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.careers.create') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/60 hover:bg-white/10 hover:text-white' }}">
                                <span class="text-accent font-bold">+</span>
                                <span>Post New Opening</span>
                            </a>
                            @endcanroute

                            <!-- Job Applications -->
                            <a href="{{ route('admin.job-applications.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.job-applications.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.job-applications.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Applications</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.job-applications.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\JobApplication::count() }}</span>
                            </a>
                        </div>
                    </div>
                    @endcanroute

                    <!-- 6. Menu: Access Control & RBAC (Accordion) -->
                    @if ($canOpen('admin.customers.index') || $canOpen('admin.users.index') || $canOpen('admin.roles.index') || $canOpen('admin.permissions.index'))
                    @php
                        $isAccessActive = request()->routeIs('admin.customers.*') || request()->routeIs('admin.users.*') || request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*');
                    @endphp
                    <div class="space-y-1">
                        <button type="button" 
                                @click="toggleMenu('access')" 
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $isAccessActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0 {{ $isAccessActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                <span class="tracking-wide">Access & Users</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200" 
                                 :class="openMenus.access ? 'rotate-90 text-accent' : 'text-white/40'" 
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <!-- Submenu: Access Control -->
                        <div x-show="openMenus.access" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                            <!-- Users Directory: website & app customers -->
                            @canroute('admin.customers.index')
                            <a href="{{ route('admin.customers.index') }}"
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.customers.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.customers.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Users Directory</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.customers.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \Illuminate\Support\Facades\Schema::hasTable('customers') ? \Illuminate\Support\Facades\DB::table('customers')->count() : 0 }}</span>
                            </a>
                            @endcanroute
                            @canroute('admin.users.index')
                            <!-- Admin Users & Staff: panel accounts -->
                            <a href="{{ route('admin.users.index') }}"
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ (request()->routeIs('admin.users.index') || request()->routeIs('admin.users.edit') || request()->routeIs('admin.users.show')) ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ (request()->routeIs('admin.users.index') || request()->routeIs('admin.users.edit') || request()->routeIs('admin.users.show')) ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Admin Users & Staff</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ (request()->routeIs('admin.users.index') || request()->routeIs('admin.users.edit') || request()->routeIs('admin.users.show')) ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\User::count() }}</span>
                            </a>
                            @endcanroute
                            @canroute('admin.users.create')
                            <a href="{{ route('admin.users.create') }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.users.create') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/60 hover:bg-white/10 hover:text-white' }}">
                                <span class="text-accent font-bold">+</span>
                                <span>Create Staff Account</span>
                            </a>
                            @endcanroute

                            @canroute('admin.roles.index')
                            <!-- Roles -->
                            <a href="{{ route('admin.roles.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.roles.index') || request()->routeIs('admin.roles.create') || request()->routeIs('admin.roles.edit') || request()->routeIs('admin.roles.show') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.roles.index') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Roles & Tiers</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.roles.index') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \Spatie\Permission\Models\Role::count() }}</span>
                            </a>

                            <!-- Permission Matrix -->
                            <a href="{{ route('admin.roles.matrix') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.roles.matrix') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.roles.matrix') ? 'bg-navy' : 'bg-accent' }}"></span>
                                <span>Permission Matrix</span>
                            </a>
                            @endcanroute

                            @canroute('admin.permissions.index')
                            <!-- Permissions -->
                            <a href="{{ route('admin.permissions.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.permissions.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.permissions.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>All Permissions</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.permissions.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \Spatie\Permission\Models\Permission::count() }}</span>
                            </a>
                            @endcanroute
                        </div>
                    </div>
                    @endif

                    <!-- 7. Menu: System & Controls (Accordion) -->
                    @if (collect(['admin.system-logs.index', 'admin.zenith-events.index', 'admin.push.index', 'admin.dropdowns.index', 'admin.settings.index', 'admin.activity-logs.index', 'admin.system.index', 'admin.profile.edit'])->contains($canOpen))
                    @php
                        $isSystemActive = request()->routeIs('admin.system-logs.*') || request()->routeIs('admin.zenith-events.*') || request()->routeIs('admin.push.*') || request()->routeIs('admin.settings.*') || request()->routeIs('admin.dropdowns.*') || request()->routeIs('admin.activity-logs.*') || request()->routeIs('admin.system.*') || request()->routeIs('admin.profile.*');
                    @endphp
                    <div class="space-y-1">
                        <button type="button" 
                                @click="toggleMenu('system')" 
                                class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-bold transition-all {{ $isSystemActive ? 'bg-white/[0.12] text-white border-l-4 border-accent' : 'text-white/85 hover:bg-white/10 hover:text-white' }}">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 shrink-0 {{ $isSystemActive ? 'text-accent' : 'text-white/70' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="tracking-wide">System & Settings</span>
                            </div>
                            <svg class="w-4 h-4 transition-transform duration-200" 
                                 :class="openMenus.system ? 'rotate-90 text-accent' : 'text-white/40'" 
                                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>

                        <!-- Submenu: System & Controls -->
                        <div x-show="openMenus.system" x-cloak class="pl-4 ml-3 border-l border-white/15 my-1.5 space-y-1">
                            @canroute('admin.system-logs.index')
                            <a href="{{ route('admin.system-logs.index') }}"
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.system-logs.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.system-logs.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>API, App & Login Logs</span>
                                </span>
                            </a>
                            @endcanroute
                            @canroute('admin.zenith-events.index')
                            <a href="{{ route('admin.zenith-events.index') }}"
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.zenith-events.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.zenith-events.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Zenith Flight Events</span>
                                </span>
                            </a>
                            @endcanroute
                            @canroute('admin.push.index')
                            <a href="{{ route('admin.push.index') }}"
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.push.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.push.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Push Notifications</span>
                                </span>
                            </a>
                            @endcanroute
                            @canroute('admin.dropdowns.index')
                            <!-- Dynamic Dropdowns & Taxonomies -->
                            <a href="{{ route('admin.dropdowns.index') }}" 
                               class="flex items-center justify-between px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.dropdowns.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.dropdowns.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                    <span>Dynamic Dropdowns</span>
                                </span>
                                <span class="px-1.5 py-0.2 rounded-full text-[11px] {{ request()->routeIs('admin.dropdowns.*') ? 'bg-navy/20 text-navy font-bold' : 'bg-white/10 text-white/80' }}">{{ \App\Models\DropdownOption::count() }}</span>
                            </a>
                            @endcanroute

                            @canroute('admin.settings.index')
                            <!-- Site & Notice Bar -->
                            <a href="{{ route('admin.settings.index') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.settings.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.settings.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                <span>Site & Emergency Notice</span>
                            </a>
                            @endcanroute

                            @canroute('admin.activity-logs.index')
                            <!-- Activity Logs -->
                            <a href="{{ route('admin.activity-logs.index') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.activity-logs.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.activity-logs.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                <span>Audit Activity Logs</span>
                            </a>
                            @endcanroute

                            @canroute('admin.system.index')
                            <!-- PostgreSQL & Redis Optimization -->
                            <a href="{{ route('admin.system.index') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.system.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.system.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                <span>Cache & Optimizer</span>
                            </a>
                            @endcanroute

                            @canroute('admin.profile.edit')
                            <!-- Account Profile -->
                            <a href="{{ route('admin.profile.edit') }}" 
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all {{ request()->routeIs('admin.profile.*') ? 'bg-accent text-navy font-bold shadow-sm' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ request()->routeIs('admin.profile.*') ? 'bg-navy' : 'bg-accent' }}"></span>
                                <span>Executive Profile</span>
                            </a>
                            @endcanroute
                        </div>
                    </div>
                    @endif
                </div>
            </div>

        </aside>

        <!-- MAIN CONTENT AREA -->
        <div class="flex-1 flex flex-col min-w-0">

            <!-- TOP HEADER -->
            <header class="sticky top-0 z-30 bg-white border-b border-gray-200/80 shadow-sm">
                <div class="px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
                    
                    <!-- Left: Sidebar Toggle & Breadcrumb / Search -->
                    <div class="flex items-center gap-4 flex-1">
                        <button @click="sidebarOpen = true" 
                                class="lg:hidden p-2 rounded-xl text-navy hover:bg-navy-50 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>

                        <!-- Search Box (searches staff accounts) -->
                        @canroute('admin.users.index')
                        <div class="relative w-full max-w-md hidden sm:block">
                            <form action="{{ route('admin.users.index') }}" method="GET">
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                    </div>
                                    <input type="text" 
                                           name="search"
                                           placeholder="Search flight controllers, users, roles or logs..." 
                                           class="w-full pl-10 pr-4 py-2 text-sm bg-gray-50/80 border border-gray-200 rounded-xl focus:outline-none focus:border-navy focus:ring-2 focus:ring-navy/20 transition-all placeholder:text-gray-400">
                                </div>
                            </form>
                        </div>
                        @endcanroute
                    </div>

                    <!-- Right: Badges, Time, Profile Dropdown -->
                    <div class="flex items-center gap-3">
                        
                        <!-- Timezone Quick Badge (Australia/Brisbane) -->
                        <div class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-navy-50 border border-navy-100 text-navy text-xs font-semibold" title="Australian Eastern Standard Time (Australia/Brisbane)">
                            <span class="w-2 h-2 rounded-full bg-accent animate-ping"></span>
                            <span>Australia/Brisbane (AEST): <strong class="font-mono" id="admin-live-clock">{{ now()->setTimezone('Australia/Brisbane')->format('h:i a') }}</strong></span>
                        </div>

                        <!-- User Profile Dropdown -->
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button @click="open = !open" class="flex items-center gap-3 p-1.5 rounded-xl hover:bg-gray-100 transition-colors focus:outline-none">
                                <img src="{{ auth()->user()->avatar_url }}" 
                                     alt="{{ auth()->user()->name }}" 
                                     class="w-10 h-10 rounded-xl object-cover border-2 border-navy shadow-sm">
                                <div class="hidden sm:block text-left">
                                    <div class="text-sm font-bold text-navy leading-tight">{{ auth()->user()->name }}</div>
                                    <div class="text-[11px] font-semibold text-accent-hover">{{ auth()->user()->primary_role }}</div>
                                </div>
                                <svg class="w-4 h-4 text-gray-500 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown Menu -->
                            <div x-show="open" 
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 scale-95"
                                 x-transition:enter-end="opacity-100 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 scale-100"
                                 x-transition:leave-end="opacity-0 scale-95"
                                 class="absolute right-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50">
                                
                                <div class="px-4 py-2 border-b border-gray-100">
                                    <p class="text-xs font-semibold text-gray-400">Signed in as</p>
                                    <p class="text-sm font-bold text-navy truncate">{{ auth()->user()->email }}</p>
                                </div>

                                @canroute('admin.profile.edit')
                                <a href="{{ route('admin.profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-navy-50 hover:text-navy transition-colors">
                                    <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>Account Profile</span>
                                </a>
                                @endcanroute

                                @canroute('admin.system.index')
                                <a href="{{ route('admin.system.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-navy-50 hover:text-navy transition-colors">
                                    <svg class="w-4 h-4 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>System & Cache</span>
                                </a>
                                @endcanroute

                                <div class="border-t border-gray-100 my-1"></div>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        <span>Sign Out</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </div>

                </div>
            </header>

            <!-- SETUP WARNING: website card payments need SHOP_RETURN_HOSTS -->
            @if(empty(config('naoero.shop.return_hosts')))
                <div id="return-hosts-warning" role="alert" x-cloak
                     x-data="{ show: (() => { try { return sessionStorage.getItem('naoero.hideReturnHosts') !== '1'; } catch (e) { return true; } })() }"
                     x-show="show" x-transition
                     class="fixed bottom-5 right-5 z-[60] w-[calc(100%-2.5rem)] max-w-sm rounded-xl border border-rose-200 bg-white shadow-2xl">
                    <div class="flex gap-3 p-4">
                        <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <div class="flex-1 text-sm">
                            <p class="font-bold text-rose-800">Website card payments are off</p>
                            <p class="mt-1 text-slate-600">
                                <code class="rounded bg-slate-100 px-1 text-xs">SHOP_RETURN_HOSTS</code> is not set in the server's
                                <code class="rounded bg-slate-100 px-1 text-xs">.env</code>, so payments started on the website are refused.
                                App payments still work.
                            </p>
                            <p class="mt-2 text-xs text-slate-500">
                                Add <code class="rounded bg-slate-100 px-1">SHOP_RETURN_HOSTS=napacificbridge.info</code>, then run
                                <code class="rounded bg-slate-100 px-1">php artisan config:cache</code>.
                            </p>
                        </div>
                        <button type="button" aria-label="Dismiss" class="self-start text-slate-400 hover:text-slate-600"
                                @click="show = false; try { sessionStorage.setItem('naoero.hideReturnHosts', '1'); } catch (e) {}">&times;</button>
                    </div>
                </div>
            @endif

            <!-- Lists are part of Website & App Pages: say so, and link back -->
            @if(! request()->boolean('embed') && ! request()->routeIs('admin.content-pages.*') && request()->routeIs(...app(\App\Services\Content\PageUsage::class)->routePatterns()))
                @php $usedOn = app(\App\Services\Content\PageUsage::class)->pagesForRoute(request()->route()?->getName()); @endphp
                <div class="mx-4 sm:mx-6 lg:mx-8 mt-4 px-4 py-3 rounded-2xl bg-sky-50 border border-sky-200 text-xs text-sky-900 flex flex-wrap items-center gap-x-2 gap-y-1">
                    <a href="{{ route('admin.content-pages.index') }}" class="font-extrabold underline">&larr; Website &amp; App Pages</a>
                    <span class="text-sky-700">This list is part of the pages.</span>
                    @if($usedOn)
                        <span class="font-bold">Shown on:</span>
                        @foreach($usedOn as $u)<a href="{{ $u['edit'] }}" class="underline font-bold">{{ $u['title'] }}</a>@endforeach
                    @endif
                </div>
            @endif

            <!-- FLASH NOTIFICATIONS -->
            <div class="px-4 sm:px-6 lg:px-8 pt-6">
                @if(session('success'))
                    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 shadow-sm" x-data="{ show: true }" x-show="show">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-medium flex-1">{{ session('success') }}</p>
                        <button @click="show = false" class="text-emerald-500 hover:text-emerald-700">&times;</button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="flex items-center gap-3 p-4 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 shadow-sm" x-data="{ show: true }" x-show="show">
                        <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-medium flex-1">{{ session('error') }}</p>
                        <button @click="show = false" class="text-rose-500 hover:text-rose-700">&times;</button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="p-4 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 shadow-sm" x-data="{ show: true }" x-show="show">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2 font-bold text-sm">
                                <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>Please review the form errors:</span>
                            </div>
                            <button @click="show = false" class="text-rose-500 hover:text-rose-700 font-bold">&times;</button>
                        </div>
                        <ul class="list-disc list-inside text-xs space-y-1">
                            @foreach($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <!-- MAIN BODY CONTENT -->
            <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                @yield('content')
            </main>

            <!-- FOOTER -->
            <footer class="mt-auto border-t border-gray-200 bg-white py-4 px-4 sm:px-6 lg:px-8">
                <div class="text-center text-xs text-gray-500">
                    &copy; {{ date('Y') }} Naoero Airlines. All Rights Reserved.
                </div>
            </footer>

        </div>

    </div>

    <!-- Global Quill WYSIWYG Auto-Initializer -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function shouldAttachQuill(textarea) {
                if (textarea.dataset.noEditor === 'true' || 
                    textarea.dataset.quill === 'false' || 
                    textarea.classList.contains('no-editor') ||
                    textarea.closest('template')) {
                    return false;
                }

                if (textarea.classList.contains('wysiwyg-editor') || 
                    textarea.classList.contains('wysiwyg-compact') || 
                    textarea.classList.contains('rich-editor') || 
                    textarea.dataset.editor === 'true') {
                    return true;
                }

                const name = (textarea.getAttribute('name') || '').toLowerCase();
                const xModel = (textarea.getAttribute('x-model') || '').toLowerCase();

                // Exclude raw multi-line plaintext list helpers
                const plainFields = ['phones', 'emails', 'address', 'admin_notes', 'features'];
                for (const pf of plainFields) {
                    if (name === pf || xModel === pf) return false;
                }

                // Match description, body and content fields
                const richKeywords = [
                    'description', 'short_description', 'intro', 'content', 'banner_text', 
                    'quote', 'terms', 'requirements', 'bio', 'answer', 'details', 
                    'notes', 'subheading', 'summary', 'message', 'banner_description', 
                    'agency_resources_intro'
                ];

                for (const keyword of richKeywords) {
                    if (name.includes(keyword) || xModel.includes(keyword)) {
                        return true;
                    }
                }

                return false;
            }

            function initQuillEditors() {
                const textareas = document.querySelectorAll('textarea');

                textareas.forEach(function(textarea) {
                    if (textarea.dataset.quillInitialized === 'true' || textarea._quill) {
                        return;
                    }

                    if (!shouldAttachQuill(textarea)) {
                        return;
                    }

                    textarea.dataset.quillInitialized = 'true';
                    textarea.style.display = 'none';

                    const name = (textarea.getAttribute('name') || '').toLowerCase();
                    const xModel = (textarea.getAttribute('x-model') || '').toLowerCase();
                    const rows = parseInt(textarea.getAttribute('rows') || '4');

                    const isExplicitFull = textarea.classList.contains('wysiwyg-editor') || 
                                           name === 'intro' || 
                                           name === 'content' || 
                                           name === 'answer' || 
                                           name === 'requirements' || 
                                           name === 'terms' ||
                                           (name === 'description' && !xModel && rows >= 4);

                    const isCompact = textarea.classList.contains('wysiwyg-compact') || !isExplicitFull;

                    const editorWrapper = document.createElement('div');
                    editorWrapper.className = 'w-full quill-wrapper' + (isCompact ? ' quill-compact' : ' quill-full');
                    textarea.parentNode.insertBefore(editorWrapper, textarea.nextSibling);

                    const toolbarOptions = isCompact ? [
                        ['bold', 'italic', 'underline'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['link'],
                        ['clean']
                    ] : [
                        [{ 'header': [1, 2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        [{ 'color': [] }],
                        ['blockquote', 'link'],
                        ['clean']
                    ];

                    const quill = new Quill(editorWrapper, {
                        theme: 'snow',
                        placeholder: textarea.getAttribute('placeholder') || 'Enter formatted content here...',
                        modules: {
                            toolbar: toolbarOptions
                        }
                    });

                    textarea._quill = quill;
                    editorWrapper._quill = quill;

                    // Load initial value
                    if (textarea.value && textarea.value.trim().length > 0) {
                        quill.clipboard.dangerouslyPasteHTML(textarea.value);
                    }

                    // Real-time synchronization
                    let isSyncing = false;
                    quill.on('text-change', function() {
                        if (isSyncing) return;
                        isSyncing = true;
                        const html = quill.root.innerHTML;
                        const cleanVal = (html === '<p><br></p>' || html === '<p></p>') ? '' : html;
                        textarea.value = cleanVal;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                        isSyncing = false;
                    });

                    // Form submit sync protection
                    const parentForm = textarea.closest('form');
                    if (parentForm && !parentForm._quillSubmitAttached) {
                        parentForm._quillSubmitAttached = true;
                        parentForm.addEventListener('submit', function() {
                            parentForm.querySelectorAll('textarea').forEach(function(t) {
                                if (t._quill) {
                                    const h = t._quill.root.innerHTML;
                                    t.value = (h === '<p><br></p>' || h === '<p></p>') ? '' : h;
                                    t.dispatchEvent(new Event('input', { bubbles: true }));
                                    t.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            });
                        }, true);
                    }
                });
            }

            initQuillEditors();

            // Hook Alpine initialization
            document.addEventListener('alpine:initialized', function() {
                setTimeout(initQuillEditors, 50);
            });

            // Smart MutationObserver that only triggers when new textareas are added to DOM
            let mutationTimeout;
            const observer = new MutationObserver(function(mutations) {
                let hasNewTextareas = false;
                for (const mutation of mutations) {
                    if (mutation.type === 'childList') {
                        for (const node of mutation.addedNodes) {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                if (node.tagName === 'TEXTAREA' || (node.querySelector && node.querySelector('textarea'))) {
                                    if (!node.closest?.('.quill-wrapper') && !node.closest?.('template')) {
                                        hasNewTextareas = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    if (hasNewTextareas) break;
                }
                if (hasNewTextareas) {
                    clearTimeout(mutationTimeout);
                    mutationTimeout = setTimeout(initQuillEditors, 40);
                }
            });
            observer.observe(document.body, { childList: true, subtree: true });

            // Australia/Brisbane Live Clock Updater
            function updateAdminLiveClock() {
                const clockEl = document.getElementById('admin-live-clock');
                if (clockEl) {
                    const now = new Date();
                    const formatter = new Intl.DateTimeFormat('en-AU', {
                        timeZone: 'Australia/Brisbane',
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                    clockEl.textContent = formatter.format(now);
                }
            }
            setInterval(updateAdminLiveClock, 1000);
        });
    </script>

    @stack('scripts')
@if(request()->boolean('embed'))
<script>
    // Opened inside the page editor: keep every link and form inside it.
    const only = new URLSearchParams(location.search).get('only');
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href]');
        if (!a || a.target === '_blank' || /^(#|javascript:|mailto:|tel:)/.test(a.getAttribute('href'))) return;
        const u = new URL(a.href, location.href);
        if (u.origin !== location.origin || u.searchParams.has('embed')) return;
        u.searchParams.set('embed', '1');
        if (only && !u.searchParams.has('only')) u.searchParams.set('only', only);
        a.href = u.toString();
    }, true);
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (f.querySelector('input[name=embed]')) return;
        const i = document.createElement('input');
        i.type = 'hidden'; i.name = 'embed'; i.value = '1';
        f.appendChild(i);
        if (only && !f.querySelector('input[name=only]')) {
            const o = document.createElement('input');
            o.type = 'hidden'; o.name = 'only'; o.value = only;
            f.appendChild(o);
        }
    }, true);
</script>
@endif
</body>
</html>
