<?php

namespace Tests\Feature\Admin;

use App\Models\GiftCardOrder;
use App\Models\ShopBanner;
use App\Models\ShopProduct;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ShopSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@naoero-airlines.com',
            'status' => 'active',
        ]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_admin_can_view_shop_dashboard_and_products()
    {
        $this->seed(ShopSeeder::class);

        $response = $this->actingAs($this->admin)->get(route('admin.shop.index'));
        $response->assertStatus(200);
        $response->assertSee('Shop & Gift Cards');
        $response->assertSee('Signature Hoodie');
        $response->assertSee('AUD 2000 Gift Card');
    }

    public function test_admin_can_create_shop_product_with_extended_fields()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.shop.store'), [
            'title' => 'Naoero Pilot Aviator Sunglasses',
            'category' => 'Merch',
            'type' => 'merch',
            'price' => 75.00,
            'compare_at_price' => 95.00,
            'currency' => 'AUD',
            'short_description' => 'UV400 polarized classic aviators with gold finish.',
            'description' => '<p>Classic pilot sunglasses engineered for cockpit glare protection.</p>',
            'is_active' => 1,
            'is_recommended' => 1,
            'is_featured' => 0,
            'sort_order' => 1,
            'stock_quantity' => 50,
        ]);

        $response->assertRedirect(route('admin.shop.index'));
        $this->assertDatabaseHas('shop_products', [
            'title' => 'Naoero Pilot Aviator Sunglasses',
            'category' => 'Merch',
            'type' => 'merch',
            'price' => 75.00,
            'compare_at_price' => 95.00,
            'is_active' => true,
            'is_recommended' => true,
        ]);

        $product = ShopProduct::where('title', 'Naoero Pilot Aviator Sunglasses')->first();
        $this->assertEquals(21, $product->discount_percent);
        $this->assertTrue($product->is_in_stock);
    }

    public function test_admin_can_update_shop_product()
    {
        $product = ShopProduct::create([
            'title' => 'Original Cap',
            'slug' => 'original-cap',
            'category' => 'Caps',
            'type' => 'merch',
            'price' => 20.00,
            'currency' => 'AUD',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.shop.update', $product), [
            'title' => 'Premium Navy Cap',
            'category' => 'Caps',
            'type' => 'merch',
            'price' => 25.00,
            'compare_at_price' => 30.00,
            'currency' => 'AUD',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('admin.shop.index'));
        $this->assertDatabaseHas('shop_products', [
            'id' => $product->id,
            'title' => 'Premium Navy Cap',
            'price' => 25.00,
        ]);
    }

    public function test_admin_can_toggle_product_status_and_flags()
    {
        $product = ShopProduct::create([
            'title' => 'Test Merch Item',
            'slug' => 'test-merch-item',
            'category' => 'Merch',
            'type' => 'merch',
            'price' => 30.00,
            'currency' => 'AUD',
            'is_active' => true,
            'is_recommended' => false,
            'is_featured' => false,
        ]);

        // Toggle active
        $this->actingAs($this->admin)->patch(route('admin.shop.toggle-active', $product));
        $this->assertFalse($product->fresh()->is_active);

        // Toggle recommended
        $this->actingAs($this->admin)->patch(route('admin.shop.toggle-recommended', $product));
        $this->assertTrue($product->fresh()->is_recommended);

        // Toggle featured
        $this->actingAs($this->admin)->patch(route('admin.shop.toggle-featured', $product));
        $this->assertTrue($product->fresh()->is_featured);
    }

    public function test_admin_can_update_homepage_banners()
    {
        $this->seed(ShopSeeder::class);

        $banner = ShopBanner::first();

        $response = $this->actingAs($this->admin)->put(route('admin.shop.banners.update'), [
            'banners' => [
                $banner->id => [
                    'title' => 'Updated Shop Title',
                    'description' => 'Special Pacific Souvenirs',
                    'cta' => 'Shop Now',
                    'filter' => 'Merch',
                    'link' => '/gift-cards',
                    'is_active' => '1',
                ]
            ]
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('shop_banners', [
            'id' => $banner->id,
            'title' => 'Updated Shop Title',
            'description' => 'Special Pacific Souvenirs',
        ]);
    }

    public function test_admin_can_view_and_manage_gift_card_orders()
    {
        $order = GiftCardOrder::create([
            'buyer_name' => 'Jane Smith',
            'buyer_email' => 'jane@example.com',
            'recipient_name' => 'Bob Smith',
            'recipient_email' => 'bob@example.com',
            'amount' => 500,
            'currency' => 'AUD',
            'status' => 'pending',
            'voucher_code' => 'ON-500-TEST01',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.shop.orders'));
        $response->assertStatus(200);
        $response->assertSee('Jane Smith');
        $response->assertSee('ON-500-TEST01');

        // Update status to confirmed
        $updateResponse = $this->actingAs($this->admin)->patch(route('admin.shop.orders.status', $order), [
            'status' => 'confirmed',
            'admin_notes' => 'Verified bank transfer.',
        ]);

        $updateResponse->assertRedirect();
        $this->assertEquals('confirmed', $order->fresh()->status);
    }

    public function test_admin_can_regenerate_voucher_code()
    {
        $order = GiftCardOrder::create([
            'buyer_name' => 'Captain Morgan',
            'buyer_email' => 'morgan@example.com',
            'amount' => 1000,
            'currency' => 'AUD',
            'status' => 'confirmed',
            'voucher_code' => 'ON-1000-OLD1',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.shop.orders.regenerate-voucher', $order));
        $response->assertRedirect();

        $freshCode = $order->fresh()->voucher_code;
        $this->assertNotEquals('ON-1000-OLD1', $freshCode);
        $this->assertStringStartsWith('ON-1000-', $freshCode);
    }

    public function test_admin_can_export_products_and_orders_csv()
    {
        $this->seed(ShopSeeder::class);

        $prodExport = $this->actingAs($this->admin)->get(route('admin.shop.index', ['export' => 'csv']));
        $prodExport->assertStatus(200);
        $this->assertStringContainsString('text/csv', $prodExport->headers->get('content-type'));

        $orderExport = $this->actingAs($this->admin)->get(route('admin.shop.orders', ['export' => 'csv']));
        $orderExport->assertStatus(200);
        $this->assertStringContainsString('text/csv', $orderExport->headers->get('content-type'));
    }

    public function test_public_api_shop_homepage_endpoint()
    {
        $this->seed(ShopSeeder::class);

        $response = $this->getJson(route('api.shop.homepage'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'count',
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'description',
                    'cta',
                    'filter',
                    'link',
                    'image',
                ]
            ],
            'recommended_preview',
        ]);
        $this->assertTrue($response->json('success'));
    }

    public function test_public_api_shop_products_filtering_and_sorting()
    {
        $this->seed(ShopSeeder::class);

        // Filter by Gift Cards
        $response = $this->getJson(route('api.shop.products', ['category' => 'Gift Cards']));
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('gift_card', $item['type']);
        }

        // Recommended items
        $recResponse = $this->getJson(route('api.shop.products', ['recommended' => 1]));
        $recResponse->assertStatus(200);
        $this->assertNotEmpty($recResponse->json('data'));
        $this->assertTrue($recResponse->json('data.0.isRecommended'));

        // Search query
        $searchResponse = $this->getJson(route('api.shop.products', ['search' => 'Hoodie']));
        $searchResponse->assertStatus(200);
        $searchData = $searchResponse->json('data');
        $this->assertNotEmpty($searchData);
        $this->assertStringContainsString('Hoodie', $searchData[0]['title']);

        // Sorting by price desc
        $sortResponse = $this->getJson(route('api.shop.products', ['sort' => 'price_desc']));
        $sortResponse->assertStatus(200);
        $sortedItems = $sortResponse->json('data');
        $this->assertGreaterThanOrEqual($sortedItems[1]['price'], $sortedItems[0]['price']);
    }

    public function test_public_api_shop_categories_endpoint()
    {
        $this->seed(ShopSeeder::class);

        $response = $this->getJson(route('api.shop.categories'));
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'counts' => [
                'All',
                'Merch',
                'Gift Cards',
            ],
            'tiles',
        ]);
    }

    public function test_public_api_shop_product_show_endpoint()
    {
        $product = ShopProduct::create([
            'title' => 'Naoero Airline Travel Pillow',
            'slug' => 'naoero-airline-travel-pillow',
            'category' => 'Merch',
            'type' => 'merch',
            'price' => 35.00,
            'compare_at_price' => 45.00,
            'currency' => 'AUD',
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.shop.show', ['identifier' => 'naoero-airline-travel-pillow']));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'title' => 'Naoero Airline Travel Pillow',
                'slug' => 'naoero-airline-travel-pillow',
                'discountPercent' => 22,
                'isInStock' => true,
            ]
        ]);
    }

    public function test_public_api_gift_card_order_submission_and_verification()
    {
        $response = $this->postJson(route('api.gift-cards.order'), [
            'buyer_name' => 'John Doe',
            'buyer_email' => 'john@example.com',
            'buyer_phone' => '+61 400 123 456',
            'recipient_name' => 'Sarah Doe',
            'recipient_email' => 'sarah@example.com',
            'amount' => 1000,
            'currency' => 'AUD',
            'delivery_type' => 'digital',
            'personal_message' => 'Happy travels across the Pacific!',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);
        $voucherCode = $response->json('data.voucher_code');
        $this->assertNotNull($voucherCode);
        $this->assertNotNull($response->json('data.order_number'));

        // Confirm the order
        $order = GiftCardOrder::where('voucher_code', $voucherCode)->first();
        $order->update(['status' => 'issued']);

        // Verify voucher endpoint
        $verifyResponse = $this->getJson(route('api.gift-cards.verify', ['voucher_code' => $voucherCode]));
        $verifyResponse->assertStatus(200);
        $verifyResponse->assertJson([
            'success' => true,
            'valid' => true,
            'data' => [
                'voucher_code' => $voucherCode,
                'status' => 'issued',
                'amount' => 1000,
                'balance_remaining' => 1000,
                'currency' => 'AUD',
            ]
        ]);
    }
}
