<?php

use App\Http\Controllers\Api\AgencyContactApiController;
use App\Http\Controllers\Api\AgencyResourceApiController;
use App\Http\Controllers\Api\AirlineServiceApiController;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\BlogApiController;
use App\Http\Controllers\Api\CareerApiController;
use App\Http\Controllers\Api\ContentPageApiController;
use App\Http\Controllers\Api\DestinationApiController;
use App\Http\Controllers\Api\DropdownApiController;
use App\Http\Controllers\Api\FaqApiController;
use App\Http\Controllers\Api\FleetApiController;
use App\Http\Controllers\Api\FlightScheduleApiController;
use App\Http\Controllers\Api\GalleryApiController;
use App\Http\Controllers\Api\InquiryApiController;
use App\Http\Controllers\Api\JourneyApiController;
use App\Http\Controllers\Api\LegalApiController;
use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\Api\NewsApiController;
use App\Http\Controllers\Api\NewsletterApiController;
use App\Http\Controllers\Api\NoticeApiController;
use App\Http\Controllers\Api\PageBannerApiController;
use App\Http\Controllers\Api\PostApiController;
use App\Http\Controllers\Api\ShopApiController;
use App\Http\Controllers\Api\SiteDocsController;
use App\Http\Controllers\Api\SiteSettingApiController;
use App\Http\Controllers\Api\SpecialOfferApiController;
use App\Http\Controllers\Api\TeamApiController;
use App\Http\Controllers\Api\TestimonialApiController;
use App\Http\Controllers\Api\TravelInfoApiController;
use App\Http\Controllers\Api\WhyFlyApiController;
use App\Http\Middleware\RequireStaffToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public REST API Routes for Naoero Airlines Frontend
|--------------------------------------------------------------------------
|
| Complete dynamic API coverage for every page, form, and module:
| 1. Destinations & Explore Hub
| 2. Fleet Aircraft (Passenger & Cargo)
| 3. News & Blog Posts
| 4. Testimonials / Passenger Reviews
| 5. Team Members & Executive Leadership
| 6. Careers & Job Application Submissions
| 7. Special Flight Deals & Offers
| 8. Passenger FAQs
| 9. Forms & Inquiries (Contact, Feedback, Groups, Charter, ACMI, Freight)
| 10. Travel Agency Contacts & Global PSAs
| 11. Flight Schedules & PDF Timetables
| 12. Media & Photo Gallery
| 13. Newsletter Subscriptions
| 14. Site Settings & Emergency Notice Bar
| 15. User Authentication (Login, Me, Logout)
|
*/

// 1. Destinations API
Route::prefix('destinations')->group(function () {
    Route::get('/', [DestinationApiController::class, 'index'])->name('api.destinations.index');
    Route::get('/featured', [DestinationApiController::class, 'featured'])->name('api.destinations.featured');
    Route::get('/{identifier}', [DestinationApiController::class, 'show'])->name('api.destinations.show');
});

// 2. Fleet API
Route::prefix('fleet')->group(function () {
    Route::get('/', [FleetApiController::class, 'index'])->name('api.fleet.index');
    Route::get('/{identifier}', [FleetApiController::class, 'show'])->name('api.fleet.show');
});

// 2.1 Airline Services API (Passenger, Cargo, Charter, ACMI, etc.)
Route::prefix('services')->group(function () {
    Route::get('/', [AirlineServiceApiController::class, 'index'])->name('api.services.index');
    Route::get('/{identifier}', [AirlineServiceApiController::class, 'show'])->name('api.services.show');
});

// 3. Blog Articles API
Route::prefix('blogs')->group(function () {
    Route::get('/', [BlogApiController::class, 'index'])->name('api.blogs.index');
    Route::get('/categories', [BlogApiController::class, 'categories'])->name('api.blogs.categories');
    Route::get('/{identifier}', [BlogApiController::class, 'show'])->name('api.blogs.show');
});

// 4. News & Media Hub API
Route::prefix('news')->group(function () {
    Route::get('/', [NewsApiController::class, 'index'])->name('api.news.index');
    Route::get('/categories', [NewsApiController::class, 'categories'])->name('api.news.categories');
    Route::get('/{identifier}', [NewsApiController::class, 'show'])->name('api.news.show');
});

// 5. Unified Posts API (Backward compatibility)
Route::prefix('posts')->group(function () {
    Route::get('/', [PostApiController::class, 'index'])->name('api.posts.index');
    Route::get('/categories', [PostApiController::class, 'categories'])->name('api.posts.categories');
    Route::get('/{identifier}', [PostApiController::class, 'show'])->name('api.posts.show');
});

// 4. Testimonials API
Route::prefix('testimonials')->group(function () {
    Route::get('/', [TestimonialApiController::class, 'index'])->name('api.testimonials.index');
    Route::get('/{id}', [TestimonialApiController::class, 'show'])->name('api.testimonials.show');
});

// 4.1 Why Fly With Us Features API (Homepage Selling Points)
Route::prefix('why-fly')->group(function () {
    Route::get('/', [WhyFlyApiController::class, 'index'])->name('api.why-fly.index');
    Route::get('/{id}', [WhyFlyApiController::class, 'show'])->name('api.why-fly.show');
});
Route::get('/why-fly-with-us', [WhyFlyApiController::class, 'index'])->name('api.why-fly-with-us.index');

// 4.2 Our Journey Timeline & Enhance Your Journey API
Route::get('/our-journey', [JourneyApiController::class, 'ourJourney'])->name('api.our-journey.index');
Route::get('/enhance-journey', [JourneyApiController::class, 'enhanceJourney'])->name('api.enhance-journey.index');
Route::prefix('journey')->group(function () {
    Route::get('/', [JourneyApiController::class, 'all'])->name('api.journey.index');
    Route::get('/all', [JourneyApiController::class, 'all'])->name('api.journey.all');
    Route::get('/milestones', [JourneyApiController::class, 'ourJourney'])->name('api.journey.milestones');
    Route::get('/enhance', [JourneyApiController::class, 'enhanceJourney'])->name('api.journey.enhance');
});

// 5. Team Members API
Route::get('/team', [TeamApiController::class, 'index'])->name('api.team.index');

// 6. Careers API & Job Application Submission
Route::prefix('careers')->group(function () {
    Route::get('/', [CareerApiController::class, 'index'])->name('api.careers.index');
    Route::get('/{identifier}', [CareerApiController::class, 'show'])->name('api.careers.show');
    Route::post('/{id}/apply', [CareerApiController::class, 'apply'])->name('api.careers.apply');
});

// 7. Special Offers API
Route::prefix('specials')->group(function () {
    Route::get('/', [SpecialOfferApiController::class, 'index'])->name('api.specials.index');
    Route::get('/{identifier}', [SpecialOfferApiController::class, 'show'])->name('api.specials.show');
});

// 8. FAQs API
Route::get('/faqs', [FaqApiController::class, 'index'])->name('api.faqs.index');

// 9. Forms & Inquiries Submission API
Route::post('/contact', [InquiryApiController::class, 'submitContact'])->name('api.contact.submit');
Route::post('/feedback', [InquiryApiController::class, 'submitFeedback'])->name('api.feedback.submit');
Route::post('/group-bookings', [InquiryApiController::class, 'submitGroupBooking'])->name('api.group-bookings.submit');
Route::post('/charter-inquiries', [InquiryApiController::class, 'submitCharter'])->name('api.charters.submit');
Route::post('/acmi-inquiries', [InquiryApiController::class, 'submitAcmi'])->name('api.acmi.submit');
Route::post('/freight-inquiries', [InquiryApiController::class, 'submitFreight'])->name('api.freight.submit');

// 10. Travel Agency Contacts API
Route::get('/agency-contacts', [AgencyContactApiController::class, 'index'])->name('api.agencies.index');

// 11. Flight Schedules & Timetables API
Route::prefix('flight-schedules')->group(function () {
    Route::get('/', [FlightScheduleApiController::class, 'index'])->name('api.schedules.index');
    Route::get('/routes', [FlightScheduleApiController::class, 'routes'])->name('api.schedules.routes');
});

// 12. Media & Photo Gallery API
Route::prefix('gallery')->group(function () {
    Route::get('/', [GalleryApiController::class, 'index'])->name('api.gallery.index');
    Route::get('/categories', [GalleryApiController::class, 'categories'])->name('api.gallery.categories');
});

// 13. Newsletter Subscriptions API
Route::prefix('newsletter')->group(function () {
    Route::post('/subscribe', [NewsletterApiController::class, 'subscribe'])->name('api.newsletter.subscribe');
    Route::post('/unsubscribe', [NewsletterApiController::class, 'unsubscribe'])->name('api.newsletter.unsubscribe');
});

// 14. Global Site Settings, Branding & Top Header Navigation API
Route::get('/global', [SiteSettingApiController::class, 'settings'])->name('api.global.index');
Route::get('/global-settings', [SiteSettingApiController::class, 'settings'])->name('api.global-settings.index');
Route::get('/settings', [SiteSettingApiController::class, 'settings'])->name('api.settings.index');
// Changing settings is for admin panel staff only (bearer token from /api/auth/login).
Route::post('/settings', [SiteSettingApiController::class, 'update'])
    ->middleware(RequireStaffToken::class)
    ->name('api.settings.update');
Route::get('/header', [SiteSettingApiController::class, 'settings'])->name('api.header.index');

// 15. Frontend Page Banners & Hero Images API
Route::prefix('banners')->group(function () {
    Route::get('/', [PageBannerApiController::class, 'index'])->name('api.banners.index');
    Route::get('/{identifier}', [PageBannerApiController::class, 'show'])->name('api.banners.show');
});
Route::prefix('page-banners')->group(function () {
    Route::get('/', [PageBannerApiController::class, 'index'])->name('api.page-banners.index');
    Route::get('/{identifier}', [PageBannerApiController::class, 'show'])->name('api.page-banners.show');
});

Route::prefix('notices')->group(function () {
    Route::get('/', [NoticeApiController::class, 'index'])->name('api.notices.index');
    Route::get('/latest', [NoticeApiController::class, 'latest'])->name('api.notices.latest');
    Route::get('/{id}', [NoticeApiController::class, 'show'])->name('api.notices.show');
});
Route::get('/notice-bar', [NoticeApiController::class, 'noticeBar'])->name('api.notice-bar.index');

// 15. Shop & Gift Cards API
Route::prefix('shop')->group(function () {
    Route::get('/homepage', [ShopApiController::class, 'homepage'])->name('api.shop.homepage');
    Route::get('/products', [ShopApiController::class, 'products'])->name('api.shop.products');
    Route::get('/categories', [ShopApiController::class, 'categories'])->name('api.shop.categories');
    Route::get('/products/{identifier}', [ShopApiController::class, 'show'])->name('api.shop.show');
});
Route::post('/gift-cards/order', [ShopApiController::class, 'submitOrder'])->name('api.gift-cards.order');
Route::get('/gift-cards/verify/{voucher_code}', [ShopApiController::class, 'verifyVoucher'])->name('api.gift-cards.verify');
Route::post('/shop/orders', [ShopApiController::class, 'submitOrder'])->name('api.shop.orders.submit');

// 16. Dynamic Master Dropdowns & Taxonomies API
Route::prefix('dropdowns')->group(function () {
    Route::get('/', [DropdownApiController::class, 'all'])->name('api.dropdowns.index');
    Route::get('/{group}', [DropdownApiController::class, 'show'])->name('api.dropdowns.show');
});

// 17. Locations Master API (Countries, States, Cities Cascading)
Route::prefix('locations')->group(function () {
    Route::get('/countries', [LocationApiController::class, 'countries'])->name('api.locations.countries');
    Route::get('/countries/{countryIdentifier}/states', [LocationApiController::class, 'states'])->name('api.locations.states');
    Route::get('/states/{stateId}/cities', [LocationApiController::class, 'cities'])->name('api.locations.cities');
    Route::get('/countries/{countryIdentifier}/cities', [LocationApiController::class, 'countryCities'])->name('api.locations.country-cities');
    Route::get('/summary', [LocationApiController::class, 'summary'])->name('api.locations.summary');
});

// 18. User Authentication API
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthApiController::class, 'login'])->name('api.auth.login');
    Route::get('/me', [AuthApiController::class, 'me'])->name('api.auth.me');
    Route::post('/logout', [AuthApiController::class, 'logout'])->name('api.auth.logout');
});

// 19. Travel Agency Resources & GDS Ticketing Guidelines API
Route::prefix('agency-resources')->group(function () {
    Route::get('/', [AgencyResourceApiController::class, 'index'])->name('api.agency-resources.index');
    Route::get('/{identifier}', [AgencyResourceApiController::class, 'show'])->name('api.agency-resources.show');
});
Route::get('/travel-agency-resources', [AgencyResourceApiController::class, 'index'])->name('api.travel-agency-resources.index');

// 20. Travel Information Hub, Helpful Information & Check-In Times & Dangerous Goods API
Route::prefix('travel-info')->group(function () {
    Route::get('/', [TravelInfoApiController::class, 'overview'])->name('api.travel-info.overview');
    Route::get('/helpful-information', [TravelInfoApiController::class, 'overview'])->name('api.travel-info.helpful-information');
    Route::get('/before-you-fly', [TravelInfoApiController::class, 'beforeYouFly'])->name('api.travel-info.before-you-fly');
    Route::get('/check-in-times', [TravelInfoApiController::class, 'checkInTimes'])->name('api.travel-info.check-in-times');
    Route::get('/before-you-fly/check-in-times', [TravelInfoApiController::class, 'checkInTimes'])->name('api.travel-info.before-you-fly.check-in-times');
    Route::get('/dangerous-goods', [TravelInfoApiController::class, 'dangerousGoods'])->name('api.travel-info.dangerous-goods');
    Route::get('/conditions-of-carriage', [LegalApiController::class, 'conditionsOfCarriage'])->name('api.travel-info.conditions-of-carriage');
});
Route::get('/check-in-times', [TravelInfoApiController::class, 'checkInTimes'])->name('api.check-in-times.index');
Route::get('/dangerous-goods', [TravelInfoApiController::class, 'dangerousGoods'])->name('api.dangerous-goods.index');

// 21. Legal & Regulatory Documents API (Privacy Policy, Terms of Use, Conditions of Carriage)
Route::get('/privacy-policy', [LegalApiController::class, 'privacyPolicy'])->name('api.privacy-policy.index');
Route::get('/privacy', [LegalApiController::class, 'privacyPolicy'])->name('api.privacy.index');
Route::get('/terms-of-use', [LegalApiController::class, 'termsOfUse'])->name('api.terms-of-use.index');
Route::get('/terms', [LegalApiController::class, 'termsOfUse'])->name('api.terms.index');
Route::get('/terms-and-conditions', [LegalApiController::class, 'termsOfUse'])->name('api.terms-and-conditions.index');
Route::get('/conditions-of-carriage', [LegalApiController::class, 'conditionsOfCarriage'])->name('api.conditions-of-carriage.index');
Route::get('/legal/{slug}', [LegalApiController::class, 'show'])->name('api.legal.show');

// 22. Pages edited in the admin panel (Charter, Freight, Baggage …) and the
// content versions clients check to show admin edits within seconds.
Route::get('/pages', [ContentPageApiController::class, 'index'])->name('api.pages.index');
Route::get('/pages/{slug}', [ContentPageApiController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('api.pages.show');
Route::get('/content/versions', [ContentPageApiController::class, 'versions'])->name('api.content.versions');

// 23. API reference of this website content API (Swagger UI + OpenAPI document)
Route::get('/openapi.json', [SiteDocsController::class, 'openapi'])->name('api.openapi');
Route::get('/docs', [SiteDocsController::class, 'docs'])->name('api.docs');
