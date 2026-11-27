<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AgencyContactController;
use App\Http\Controllers\Admin\AgencyResourceController;
use App\Http\Controllers\Admin\AirlineServiceController;
use App\Http\Controllers\Admin\CareerController;
use App\Http\Controllers\Admin\ContentPageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DestinationController;
use App\Http\Controllers\Admin\DropdownOptionController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\FleetController;
use App\Http\Controllers\Admin\FlightBookingController;
use App\Http\Controllers\Admin\FlightScheduleController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\JobApplicationController;
use App\Http\Controllers\Admin\JourneyController;
use App\Http\Controllers\Admin\LegalDocumentController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\PageBannerController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\PushNotificationController;
use App\Http\Controllers\Admin\RefundRequestController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ShopAftercareController;
use App\Http\Controllers\Admin\ShopProductController;
use App\Http\Controllers\Admin\ShopSalesController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SpecialOfferController;
use App\Http\Controllers\Admin\StockCountController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\SystemLogController;
use App\Http\Controllers\Admin\TeamMemberController;
use App\Http\Controllers\Admin\TestimonialController;
use App\Http\Controllers\Admin\TravelInfoController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WhyFlyController;
use App\Http\Controllers\Admin\ZenithEventLogController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Middleware\EmbeddedAdmin;
use App\Http\Middleware\EnforceAdminPermission;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

// Redirect root to dashboard (or login if guest)
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Authentication Routes: the admin panel signs in at /admin/login.
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/admin/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/admin/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// /admin opens the dashboard (guests are sent to /admin/login).
Route::get('/admin', fn () => redirect()->route('admin.dashboard'));

// The earlier sign-in address, for bookmarks and saved passwords.
Route::permanentRedirect('/login', '/admin/login');

// Admin Panel Routes
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', EnsureUserIsActive::class, EnforceAdminPermission::class, EmbeddedAdmin::class])
    ->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Destinations (Flight Network & CMS)
        Route::post('/destinations/upload-image', [DestinationController::class, 'uploadImage'])->name('destinations.upload-image');
        Route::patch('/destinations/{destination}/toggle-active', [DestinationController::class, 'toggleActive'])->name('destinations.toggle-active');
        Route::patch('/destinations/{destination}/toggle-featured', [DestinationController::class, 'toggleFeatured'])->name('destinations.toggle-featured');
        Route::resource('destinations', DestinationController::class);

        // Aircraft Fleet
        Route::patch('/fleets/{fleet}/toggle-active', [FleetController::class, 'toggleActive'])->name('fleets.toggle-active');
        Route::resource('fleets', FleetController::class);

        // Page Banners & Hero Images
        Route::patch('/banners/{banner}/toggle-active', [PageBannerController::class, 'toggleActive'])->name('banners.toggle-active');
        Route::resource('banners', PageBannerController::class)->parameters(['banners' => 'banner']);

        // Airline Services & Offerings
        Route::patch('/services/{service}/toggle-active', [AirlineServiceController::class, 'toggleActive'])->name('services.toggle-active');
        Route::resource('services', AirlineServiceController::class)->parameters(['services' => 'service']);

        // Posts / News / Blog
        Route::resource('posts', PostController::class);

        // Testimonials
        Route::patch('/testimonials/{testimonial}/toggle-active', [TestimonialController::class, 'toggleActive'])->name('testimonials.toggle-active');
        Route::resource('testimonials', TestimonialController::class);

        // Why Fly With Us Cards (Homepage Selling Points)
        Route::post('/why-fly/update-headings', [WhyFlyController::class, 'updateHeadings'])->name('why-fly.update-headings');
        Route::patch('/why-fly/{why_fly}/toggle-active', [WhyFlyController::class, 'toggleActive'])->name('why-fly.toggle-active');
        Route::resource('why-fly', WhyFlyController::class)->parameters(['why-fly' => 'why_fly']);

        // Journey Hub (Our Journey History Timeline & Enhance Your Journey Cards)
        Route::get('/journey', [JourneyController::class, 'index'])->name('journey.index');
        Route::post('/journey/update-headings', [JourneyController::class, 'updateHeadings'])->name('journey.update-headings');
        Route::get('/journey/milestones/create', [JourneyController::class, 'createMilestone'])->name('journey.milestones.create');
        Route::post('/journey/milestones', [JourneyController::class, 'storeMilestone'])->name('journey.milestones.store');
        Route::get('/journey/milestones/{milestone}/edit', [JourneyController::class, 'editMilestone'])->name('journey.milestones.edit');
        Route::put('/journey/milestones/{milestone}', [JourneyController::class, 'updateMilestone'])->name('journey.milestones.update');
        Route::patch('/journey/milestones/{milestone}/toggle-active', [JourneyController::class, 'toggleActiveMilestone'])->name('journey.milestones.toggle-active');
        Route::delete('/journey/milestones/{milestone}', [JourneyController::class, 'destroyMilestone'])->name('journey.milestones.destroy');
        Route::get('/journey/enhance/create', [JourneyController::class, 'createEnhance'])->name('journey.enhance.create');
        Route::post('/journey/enhance', [JourneyController::class, 'storeEnhance'])->name('journey.enhance.store');
        Route::get('/journey/enhance/{item}/edit', [JourneyController::class, 'editEnhance'])->name('journey.enhance.edit');
        Route::put('/journey/enhance/{item}', [JourneyController::class, 'updateEnhance'])->name('journey.enhance.update');
        Route::patch('/journey/enhance/{item}/toggle-active', [JourneyController::class, 'toggleActiveEnhance'])->name('journey.enhance.toggle-active');
        Route::delete('/journey/enhance/{item}', [JourneyController::class, 'destroyEnhance'])->name('journey.enhance.destroy');

        // Team Members
        Route::resource('team-members', TeamMemberController::class);

        // Careers & Job Postings
        Route::resource('careers', CareerController::class);

        // Job Applications
        Route::get('/job-applications', [JobApplicationController::class, 'index'])->name('job-applications.index');
        Route::patch('/job-applications/{application}/status', [JobApplicationController::class, 'updateStatus'])->name('job-applications.status');
        Route::delete('/job-applications/{application}', [JobApplicationController::class, 'destroy'])->name('job-applications.destroy');

        // Special Flight Deals & Offers
        Route::resource('specials', SpecialOfferController::class);

        // FAQs
        Route::resource('faqs', FaqController::class);

        // Customer Inquiries & Feedback Inbox
        Route::get('/inquiries', [InquiryController::class, 'index'])->name('inquiries.index');
        Route::get('/inquiries/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
        Route::patch('/inquiries/{inquiry}/status', [InquiryController::class, 'updateStatus'])->name('inquiries.status');
        Route::delete('/inquiries/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');

        // Travel Agency Contacts
        Route::patch('/agencies/{agency}/toggle-active', [AgencyContactController::class, 'toggleActive'])->name('agencies.toggle-active');
        Route::resource('agencies', AgencyContactController::class)->parameters(['agencies' => 'agency'])->except(['show']);

        // Travel Agency Resources & Policies
        Route::post('/agency-resources/update-headings', [AgencyResourceController::class, 'updateHeadings'])->name('agency-resources.update-headings');
        Route::patch('/agency-resources/{agencyResource}/toggle-active', [AgencyResourceController::class, 'toggleActive'])->name('agency-resources.toggle-active');
        Route::resource('agency-resources', AgencyResourceController::class)->parameters(['agency-resources' => 'agencyResource']);

        // Travel Information Hub & Airport Check-In / Dangerous Goods Console
        Route::get('/travel-info', [TravelInfoController::class, 'index'])->name('travel-info.index');
        Route::get('/travel-info/check-in/create', [TravelInfoController::class, 'createCheckIn'])->name('travel-info.check-in.create');
        Route::post('/travel-info/check-in', [TravelInfoController::class, 'storeCheckIn'])->name('travel-info.check-in.store');
        Route::get('/travel-info/check-in/{checkInTime}/edit', [TravelInfoController::class, 'editCheckIn'])->name('travel-info.check-in.edit');
        Route::put('/travel-info/check-in/{checkInTime}', [TravelInfoController::class, 'updateCheckIn'])->name('travel-info.check-in.update');
        Route::patch('/travel-info/check-in/{checkInTime}/toggle-active', [TravelInfoController::class, 'toggleCheckIn'])->name('travel-info.check-in.toggle-active');
        Route::delete('/travel-info/check-in/{checkInTime}', [TravelInfoController::class, 'destroyCheckIn'])->name('travel-info.check-in.destroy');

        Route::get('/travel-info/dangerous-goods/create', [TravelInfoController::class, 'createDangerousGoods'])->name('travel-info.dangerous-goods.create');
        Route::post('/travel-info/dangerous-goods', [TravelInfoController::class, 'storeDangerousGoods'])->name('travel-info.dangerous-goods.store');
        Route::get('/travel-info/dangerous-goods/{dangerousGood}/edit', [TravelInfoController::class, 'editDangerousGoods'])->name('travel-info.dangerous-goods.edit');
        Route::put('/travel-info/dangerous-goods/{dangerousGood}', [TravelInfoController::class, 'updateDangerousGoods'])->name('travel-info.dangerous-goods.update');
        Route::patch('/travel-info/dangerous-goods/{dangerousGood}/toggle-active', [TravelInfoController::class, 'toggleDangerousGoods'])->name('travel-info.dangerous-goods.toggle-active');
        Route::delete('/travel-info/dangerous-goods/{dangerousGood}', [TravelInfoController::class, 'destroyDangerousGoods'])->name('travel-info.dangerous-goods.destroy');

        Route::get('/travel-info/cards/create', [TravelInfoController::class, 'createCard'])->name('travel-info.cards.create');
        Route::post('/travel-info/cards', [TravelInfoController::class, 'storeCard'])->name('travel-info.cards.store');
        Route::get('/travel-info/cards/{travelInfoCard}/edit', [TravelInfoController::class, 'editCard'])->name('travel-info.cards.edit');
        Route::put('/travel-info/cards/{travelInfoCard}', [TravelInfoController::class, 'updateCard'])->name('travel-info.cards.update');
        Route::patch('/travel-info/cards/{travelInfoCard}/toggle-active', [TravelInfoController::class, 'toggleCard'])->name('travel-info.cards.toggle-active');
        Route::delete('/travel-info/cards/{travelInfoCard}', [TravelInfoController::class, 'destroyCard'])->name('travel-info.cards.destroy');

        // Legal & Regulatory Documents (Privacy Policy, Terms of Use, Conditions of Carriage)
        Route::get('/legal-documents', [LegalDocumentController::class, 'index'])->name('legal-documents.index');
        Route::get('/legal-documents/{legalDocument}/edit', [LegalDocumentController::class, 'edit'])->name('legal-documents.edit');
        Route::put('/legal-documents/{legalDocument}', [LegalDocumentController::class, 'update'])->name('legal-documents.update');

        // Website & app pages (Charter, Freight, Baggage …), section by section, with history
        Route::get('/content-pages/lists/{key}', [ContentPageController::class, 'listPage'])->name('content-pages.list');
        Route::get('/content-pages/feed-entries', [ContentPageController::class, 'feedEntries'])->name('content-pages.feed-entries');
        Route::post('/content-pages/upload-image', [ContentPageController::class, 'uploadImage'])->name('content-pages.upload-image');
        Route::patch('/content-pages/{contentPage}/toggle-published', [ContentPageController::class, 'togglePublished'])->name('content-pages.toggle-published');
        Route::post('/content-pages/{contentPage}/revisions/{revision}/restore', [ContentPageController::class, 'restore'])->name('content-pages.restore');
        Route::resource('content-pages', ContentPageController::class)->parameters(['content-pages' => 'contentPage'])->only(['index', 'create', 'store', 'edit', 'update']);

        // Flight Schedules & Timetables
        Route::patch('/schedules/{schedule}/toggle-active', [FlightScheduleController::class, 'toggleActive'])->name('schedules.toggle-active');
        Route::resource('schedules', FlightScheduleController::class)->except(['show']);

        // Media & Photo Gallery
        Route::patch('/gallery/{gallery}/toggle-active', [GalleryController::class, 'toggleActive'])->name('gallery.toggle-active');
        Route::resource('gallery', GalleryController::class)->parameters(['gallery' => 'gallery'])->except(['show']);

        // Newsletter Subscribers
        Route::get('/newsletter', [NewsletterSubscriberController::class, 'index'])->name('newsletter.index');
        Route::get('/newsletter/export', [NewsletterSubscriberController::class, 'export'])->name('newsletter.export');
        Route::patch('/newsletter/{subscriber}/toggle-status', [NewsletterSubscriberController::class, 'toggleStatus'])->name('newsletter.toggle-status');
        Route::delete('/newsletter/{subscriber}', [NewsletterSubscriberController::class, 'destroy'])->name('newsletter.destroy');

        // Header Notice Bar & Flight Announcements Management
        Route::post('/notices/toggle-master', [NoticeController::class, 'toggleMaster'])->name('notices.toggle-master');
        Route::post('/notices/bulk', [NoticeController::class, 'bulkAction'])->name('notices.bulk');
        Route::patch('/notices/{notice}/toggle-active', [NoticeController::class, 'toggleActive'])->name('notices.toggle-active');
        Route::patch('/notices/{notice}/extend-time', [NoticeController::class, 'extendTime'])->name('notices.extend-time');
        Route::resource('notices', NoticeController::class)->except(['show']);

        // Shop Merchandise & Gift Cards Management
        Route::get('/shop/orders', [ShopProductController::class, 'orders'])->name('shop.orders');
        Route::patch('/shop/orders/{order}/status', [ShopProductController::class, 'updateOrderStatus'])->name('shop.orders.status');
        Route::post('/shop/orders/{order}/regenerate-voucher', [ShopProductController::class, 'regenerateVoucher'])->name('shop.orders.regenerate-voucher');
        Route::match(['post', 'put'], '/shop/banners', [ShopProductController::class, 'updateBanners'])->name('shop.banners.update');
        Route::patch('/shop/{product}/toggle-active', [ShopProductController::class, 'toggleActive'])->name('shop.toggle-active');
        Route::patch('/shop/{product}/toggle-recommended', [ShopProductController::class, 'toggleRecommended'])->name('shop.toggle-recommended');
        Route::patch('/shop/{product}/toggle-featured', [ShopProductController::class, 'toggleFeatured'])->name('shop.toggle-featured');
        Route::resource('shop', ShopProductController::class)->parameters(['shop' => 'product']);

        // Shop Sales: orders paid in the app through NAB (payments, refunds, shipping, gift cards)
        Route::prefix('sales')->name('sales.')->group(function () {
            Route::get('/', [ShopSalesController::class, 'index'])->name('index');
            Route::get('/payments', [ShopSalesController::class, 'payments'])->name('payments');
            Route::get('/activity', [ShopSalesController::class, 'activity'])->name('activity');
            Route::post('/gift-cards/{card}/resend', [ShopSalesController::class, 'resendGiftCard'])->whereNumber('card')->name('gift-cards.resend');
            Route::post('/gift-cards/{card}/cancel', [ShopSalesController::class, 'cancelGiftCard'])->whereNumber('card')->name('gift-cards.cancel');
            Route::get('/support', [ShopAftercareController::class, 'support'])->name('support');
            Route::get('/support/{id}', [ShopAftercareController::class, 'supportShow'])->whereUuid('id')->name('support.show');
            Route::post('/support/{id}/reply', [ShopAftercareController::class, 'supportReply'])->whereUuid('id')->name('support.reply');
            Route::post('/support/{id}/status', [ShopAftercareController::class, 'supportStatus'])->whereUuid('id')->name('support.status');
            Route::get('/returns', [ShopAftercareController::class, 'returns'])->name('returns');
            Route::get('/returns/{id}', [ShopAftercareController::class, 'returnShow'])->whereUuid('id')->name('returns.show');
            Route::post('/returns/{id}/approve', [ShopAftercareController::class, 'returnApprove'])->whereUuid('id')->name('returns.approve');
            Route::post('/returns/{id}/reject', [ShopAftercareController::class, 'returnReject'])->whereUuid('id')->name('returns.reject');
            Route::post('/returns/{id}/receive', [ShopAftercareController::class, 'returnReceive'])->whereUuid('id')->name('returns.receive');
            Route::post('/returns/{id}/refund', [ShopAftercareController::class, 'returnRefund'])->whereUuid('id')->name('returns.refund');
            Route::post('/returns/{id}/exchange', [ShopAftercareController::class, 'returnExchange'])->whereUuid('id')->name('returns.exchange');
            Route::get('/reviews', [ShopAftercareController::class, 'reviews'])->name('reviews');
            Route::post('/reviews/{id}/moderate', [ShopAftercareController::class, 'reviewModerate'])->whereUuid('id')->name('reviews.moderate');
            Route::post('/reviews/{id}/reply', [ShopAftercareController::class, 'reviewReply'])->whereUuid('id')->name('reviews.reply');
            Route::post('/{order}/shipment', [ShopSalesController::class, 'shipment'])->whereUuid('order')->name('shipment');
            Route::get('/{order}/documents/{kind}', [ShopSalesController::class, 'document'])->whereUuid('order')->whereIn('kind', ['invoice', 'receipt'])->name('document');
            Route::get('/{order}', [ShopSalesController::class, 'show'])->whereUuid('order')->name('show');
            Route::post('/{order}/refund', [ShopSalesController::class, 'refund'])->whereUuid('order')->name('refund');
            Route::post('/{order}/ship', [ShopSalesController::class, 'ship'])->whereUuid('order')->name('ship');
            Route::post('/{order}/deliver', [ShopSalesController::class, 'deliver'])->whereUuid('order')->name('deliver');
            Route::post('/{order}/note', [ShopSalesController::class, 'note'])->whereUuid('order')->name('note');
            Route::post('/{order}/cancel', [ShopSalesController::class, 'cancel'])->whereUuid('order')->name('cancel');
            Route::post('/{order}/reconcile', [ShopSalesController::class, 'reconcile'])->whereUuid('order')->name('reconcile');
        });

        // Shop Inventory: stock per variant, the movement ledger, suppliers, purchase orders, stock counts
        Route::prefix('inventory')->name('inventory.')->group(function () {
            Route::get('/', [InventoryController::class, 'index'])->name('index');
            Route::get('/stock', [InventoryController::class, 'stock'])->name('stock');
            Route::get('/movements', [InventoryController::class, 'movements'])->name('movements');
            Route::get('/alerts', [InventoryController::class, 'alerts'])->name('alerts');
            Route::get('/products/{product}', [InventoryController::class, 'product'])->name('product');
            Route::post('/products/{product}/options', [InventoryController::class, 'setOptions'])->name('product.options');
            Route::patch('/variants/{variant}', [InventoryController::class, 'updateVariant'])->whereNumber('variant')->name('variants.update');
            Route::delete('/variants/{variant}', [InventoryController::class, 'deleteVariant'])->whereNumber('variant')->name('variants.delete');
            Route::post('/variants/{variant}/adjust', [InventoryController::class, 'adjust'])->whereNumber('variant')->name('variants.adjust');

            Route::get('/suppliers', [PurchaseOrderController::class, 'suppliers'])->name('suppliers');
            Route::post('/suppliers', [PurchaseOrderController::class, 'storeSupplier'])->name('suppliers.store');
            Route::put('/suppliers/{supplier}', [PurchaseOrderController::class, 'updateSupplier'])->whereNumber('supplier')->name('suppliers.update');

            Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
            Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
            Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
            Route::get('/purchase-orders/{po}', [PurchaseOrderController::class, 'show'])->whereNumber('po')->name('purchase-orders.show');
            Route::get('/purchase-orders/{po}/edit', [PurchaseOrderController::class, 'edit'])->whereNumber('po')->name('purchase-orders.edit');
            Route::put('/purchase-orders/{po}', [PurchaseOrderController::class, 'update'])->whereNumber('po')->name('purchase-orders.update');
            Route::post('/purchase-orders/{po}/ordered', [PurchaseOrderController::class, 'markOrdered'])->whereNumber('po')->name('purchase-orders.ordered');
            Route::post('/purchase-orders/{po}/receive', [PurchaseOrderController::class, 'receive'])->whereNumber('po')->name('purchase-orders.receive');
            Route::post('/purchase-orders/{po}/close', [PurchaseOrderController::class, 'close'])->whereNumber('po')->name('purchase-orders.close');

            Route::get('/counts', [StockCountController::class, 'index'])->name('counts.index');
            Route::post('/counts', [StockCountController::class, 'store'])->name('counts.store');
            Route::get('/counts/{count}', [StockCountController::class, 'show'])->whereNumber('count')->name('counts.show');
            Route::post('/counts/{count}', [StockCountController::class, 'save'])->whereNumber('count')->name('counts.save');
            Route::post('/counts/{count}/cancel', [StockCountController::class, 'cancel'])->whereNumber('count')->name('counts.cancel');
        });

        // Dynamic Dropdowns & Master Taxonomies
        Route::get('/dropdowns', [DropdownOptionController::class, 'index'])->name('dropdowns.index');
        Route::post('/dropdowns', [DropdownOptionController::class, 'store'])->name('dropdowns.store');
        Route::put('/dropdowns/{dropdownOption}', [DropdownOptionController::class, 'update'])->name('dropdowns.update');
        Route::delete('/dropdowns/{dropdownOption}', [DropdownOptionController::class, 'destroy'])->name('dropdowns.destroy');
        Route::post('/dropdowns/{dropdownOption}/toggle', [DropdownOptionController::class, 'toggleActive'])->name('dropdowns.toggle');
        Route::post('/dropdowns/quick-store', [DropdownOptionController::class, 'quickStore'])->name('dropdowns.quick-store');
        Route::get('/dropdowns/api/{group}', [DropdownOptionController::class, 'getGroupJson'])->name('dropdowns.api-group');

        // Location Master (Countries, States & Cities)
        Route::get('/locations', [LocationController::class, 'index'])->name('locations.index');
        Route::post('/locations/countries', [LocationController::class, 'storeCountry'])->name('locations.countries.store');
        Route::put('/locations/countries/{country}', [LocationController::class, 'updateCountry'])->name('locations.countries.update');
        Route::delete('/locations/countries/{country}', [LocationController::class, 'destroyCountry'])->name('locations.countries.destroy');
        Route::patch('/locations/countries/{country}/toggle', [LocationController::class, 'toggleCountry'])->name('locations.countries.toggle');
        Route::get('/locations/countries/{country}/states-json', [LocationController::class, 'getStatesJson'])->name('locations.countries.states-json');

        Route::post('/locations/states', [LocationController::class, 'storeState'])->name('locations.states.store');
        Route::put('/locations/states/{state}', [LocationController::class, 'updateState'])->name('locations.states.update');
        Route::delete('/locations/states/{state}', [LocationController::class, 'destroyState'])->name('locations.states.destroy');
        Route::patch('/locations/states/{state}/toggle', [LocationController::class, 'toggleState'])->name('locations.states.toggle');

        Route::post('/locations/cities', [LocationController::class, 'storeCity'])->name('locations.cities.store');
        Route::put('/locations/cities/{city}', [LocationController::class, 'updateCity'])->name('locations.cities.update');
        Route::delete('/locations/cities/{city}', [LocationController::class, 'destroyCity'])->name('locations.cities.destroy');
        Route::patch('/locations/cities/{city}/toggle', [LocationController::class, 'toggleCity'])->name('locations.cities.toggle');

        // Site Settings & Notice Bar
        Route::get('/settings', [SiteSettingController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SiteSettingController::class, 'updateSettings'])->name('settings.update');
        Route::patch('/settings/notice/{notice}', [SiteSettingController::class, 'updateNotice'])->name('settings.notice');

        // Users Directory: the public's accounts (website and app customers)
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->whereUuid('customer')->name('customers.show');
        Route::get('/customers/{customer}/avatar', [CustomerController::class, 'avatar'])->whereUuid('customer')->name('customers.avatar');
        Route::patch('/customers/{customer}/status', [CustomerController::class, 'toggleStatus'])->whereUuid('customer')->name('customers.toggle-status');
        Route::post('/customers/{customer}/sign-out', [CustomerController::class, 'signOut'])->whereUuid('customer')->name('customers.sign-out');
        // Account deletion requests: completed by disabling the account (nothing is deleted), or declined
        Route::post('/customers/{customer}/deletion/{deletionRequest}/complete', [CustomerController::class, 'completeDeletion'])->whereUuid(['customer', 'deletionRequest'])->name('customers.deletion.complete');
        Route::post('/customers/{customer}/deletion/{deletionRequest}/decline', [CustomerController::class, 'declineDeletion'])->whereUuid(['customer', 'deletionRequest'])->name('customers.deletion.decline');

        // Flight Sales & Bookings (made through Zenith)
        Route::get('/flight-bookings', [FlightBookingController::class, 'index'])->name('flight-bookings.index');
        Route::get('/flight-bookings/{booking}', [FlightBookingController::class, 'show'])->whereUuid('booking')->name('flight-bookings.show');
        Route::get('/refund-requests', [RefundRequestController::class, 'index'])->name('refund-requests.index');
        Route::get('/refund-requests/{refundRequest}', [RefundRequestController::class, 'show'])->whereUuid('refundRequest')->name('refund-requests.show');
        Route::post('/refund-requests/{refundRequest}', [RefundRequestController::class, 'update'])->whereUuid('refundRequest')->name('refund-requests.update');

        // API, app and sign-in logs
        Route::get('/system-logs', [SystemLogController::class, 'index'])->name('system-logs.index');

        // Flight events Zenith posts to us, production and preproduction
        Route::get('/zenith-events', [ZenithEventLogController::class, 'index'])->name('zenith-events.index');
        Route::get('/zenith-events/calls/{call}', [ZenithEventLogController::class, 'call'])->whereUuid('call')->name('zenith-events.call');
        Route::get('/zenith-events/{event}', [ZenithEventLogController::class, 'show'])->whereUuid('event')->name('zenith-events.show');
        Route::post('/zenith-events/{event}/retry', [ZenithEventLogController::class, 'retry'])->whereUuid('event')->name('zenith-events.retry');

        // Phone pushes: Apple and Firebase keys, registered phones, pushes sent
        Route::get('/push-notifications', [PushNotificationController::class, 'index'])->name('push.index');
        Route::post('/push-notifications/apns', [PushNotificationController::class, 'saveApns'])->name('push.apns');
        Route::post('/push-notifications/fcm', [PushNotificationController::class, 'saveFcm'])->name('push.fcm');
        Route::post('/push-notifications/devices/{device}/test', [PushNotificationController::class, 'test'])->whereUuid('device')->name('push.test');

        // Admin Users & Staff (panel accounts)
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::resource('users', UserController::class);

        // Role Management & Permission Matrix
        Route::get('/roles/matrix', [RoleController::class, 'matrix'])->name('roles.matrix');
        Route::post('/roles/matrix', [RoleController::class, 'updateMatrix'])->name('roles.matrix.update');
        Route::resource('roles', RoleController::class);

        // Permission Management
        Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'update', 'destroy']);

        // Profile & Account Settings
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

        // Activity / Audit Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::delete('/activity-logs/clear', [ActivityLogController::class, 'clear'])->name('activity-logs.clear');

        // System & Redis Management
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/cache/flush', [SystemController::class, 'flushCache'])->name('system.cache.flush');
        Route::post('/system/optimize', [SystemController::class, 'optimize'])->name('system.optimize');
    });
