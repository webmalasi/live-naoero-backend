<?php

namespace Tests\Feature\Api\V1;

use App\Models\ShopProduct;
use App\Models\User;
use App\Services\Shop\Inventory;
use App\Services\Shop\ProductVariants;
use App\Services\Shop\Purchasing;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;

/**
 * The shop's inventory: stock per variant through an order's life
 * (reserved, sold, released, put back), sizes in the app, the cart check,
 * back-in-stock alerts, and what staff do in the admin panel.
 */
class ShopInventoryTest extends AppApiTestCase
{
    private FakeNab $nab;

    private string $token;

    private User $admin;

    private ShopProduct $tee;

    private int $giftCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nab = (new FakeNab)->install();
        Mail::fake();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Admin');
        $this->token = $this->signUp()['accessToken'];
        $this->tee = ShopProduct::create([
            'title' => 'Naoero Tee', 'category' => 'Apparel', 'type' => 'merch', 'price' => 30, 'currency' => 'AUD',
            'sku' => 'TEE', 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 1,
        ]);
        $now = now();
        $this->giftCard = DB::table('shop_products')->insertGetId([
            'title' => 'AUD 100 Gift Card', 'slug' => 'aud-100-gift-card', 'category' => 'Gift Card', 'type' => 'gift_card',
            'price' => 100, 'currency' => 'AUD', 'stock_quantity' => 999, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** Sizes S, M, L with 2, 5 and 0 in stock; M costs more. */
    private function sizes(): array
    {
        app(ProductVariants::class)->setOptions($this->tee, [['name' => 'Size', 'values' => 'S, M, L']], $this->admin->id);
        $variants = DB::table('shop_product_variants')->where('shop_product_id', $this->tee->id)->where('is_active', true)->orderBy('sort_order')->get()->keyBy('title');
        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $variants['S']->id), ['mode' => 'add', 'quantity' => 2, 'reason' => 'received']);
        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $variants['M']->id), ['mode' => 'set', 'quantity' => 5, 'reason' => 'received']);
        DB::table('shop_product_variants')->where('id', $variants['M']->id)->update(['price' => 35]);

        return $variants->map(fn ($v) => $v->id)->all();
    }

    private function variant(int $id): object
    {
        return DB::table('shop_product_variants')->find($id);
    }

    private function address(): array
    {
        return ['name' => 'Ana Citizen', 'phone' => '+61 400 000 001', 'line1' => '1 Main St', 'city' => 'Brisbane', 'postcode' => '4000', 'country' => 'AU'];
    }

    private function order(array $items): array
    {
        return $this->api('POST', 'shop/orders', ['items' => $items, 'shippingAddress' => $this->address()], $this->token)->assertCreated()->json('order');
    }

    private function pay(array $order): void
    {
        $payment = $this->api('POST', "shop/orders/{$order['id']}/pay", [], $this->token)->assertCreated()->json('payment');
        parse_str(parse_url($payment['payUrl'], PHP_URL_QUERY), $q);
        $tx = '70000000000000000000'.random_int(10, 99);
        $this->nab->sale($tx, $order['orderNumber'], number_format($order['total'], 2, '.', ''));
        $this->postJson(parse_url($payment['payUrl'], PHP_URL_PATH).'/complete', ['t' => $q['t'], 'completeResponse' => $this->nab->jwt(['id' => $tx, 'status' => 'AUTHORIZED'])])->assertOk();
    }

    private function movements(int $variantId): array
    {
        return DB::table('shop_inventory_movements')->where('variant_id', $variantId)->orderBy('id')->get()
            ->map(fn ($m) => "{$m->type}:{$m->on_hand_change}/{$m->reserved_change}")->all();
    }

    public function test_the_catalogue_lists_each_size_with_its_price_and_stock(): void
    {
        $ids = $this->sizes();
        $tee = collect($this->api('GET', 'shop/products')->assertOk()->json('products'))->firstWhere('title', 'Naoero Tee');

        $this->assertTrue($tee['hasVariants']);
        $this->assertSame([['name' => 'Size', 'values' => ['S', 'M', 'L']]], $tee['options']);
        $this->assertSame(['S', 'M', 'L'], array_column($tee['variants'], 'title'));
        $this->assertSame([30, 35, 30], array_column($tee['variants'], 'price'));
        $this->assertSame([true, true, false], array_column($tee['variants'], 'inStock'));
        $this->assertSame([2, 5, 0], array_column($tee['variants'], 'stockLeft'));
        $this->assertSame(['min' => 30, 'max' => 35], $tee['priceRange']);
        $this->assertSame(7, $tee['stockLeft']);
        $this->assertSame((string) $ids['M'], $tee['variants'][1]['id']);
        $this->assertSame(7, DB::table('shop_products')->where('id', $this->tee->id)->value('stock_quantity'));

        $giftCard = collect($this->api('GET', 'shop/products')->json('products'))->firstWhere('type', 'gift_card');
        $this->assertSame([], $giftCard['variants']);
        $this->assertFalse($giftCard['hasVariants']);
    }

    public function test_a_size_must_be_picked_and_is_held_paid_and_sold(): void
    {
        $ids = $this->sizes();
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->tee->id, 'quantity' => 1]], 'shippingAddress' => $this->address()], $this->token)
            ->assertStatus(400)->assertJsonPath('error.code', 'variant_required')->assertJsonPath('error.message', 'Choose a size for Naoero Tee.');
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['L'], 'quantity' => 1]], 'shippingAddress' => $this->address()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'out_of_stock')->assertJsonPath('error.message', 'Naoero Tee (L) is sold out.')
            ->assertJsonPath('error.variantId', (string) $ids['L']);
        $this->api('POST', 'shop/orders', ['items' => [['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['S'], 'quantity' => 3]], 'shippingAddress' => $this->address()], $this->token)
            ->assertStatus(409)->assertJsonPath('error.message', 'Only 2 × Naoero Tee (S) left. Please lower the quantity.');

        $order = $this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M'], 'quantity' => 2]]);
        $this->assertSame(70, $order['itemsTotal']);
        $this->assertSame('M', $order['items'][0]['variantTitle']);
        $this->assertSame('2 × Naoero Tee (M)', $order['itemsSummary']);
        $this->assertSame([5, 2], [(int) $this->variant($ids['M'])->on_hand, (int) $this->variant($ids['M'])->reserved]);
        $this->assertSame(5, DB::table('shop_products')->where('id', $this->tee->id)->value('stock_quantity'));

        $this->pay($order);
        $this->assertSame([3, 0], [(int) $this->variant($ids['M'])->on_hand, (int) $this->variant($ids['M'])->reserved]);
        $this->assertSame('sold', DB::table('shop_order_items')->where('shop_order_id', $order['id'])->value('stock_state'));
        $this->assertSame(['adjustment:5/0', 'reserve:0/2', 'sale:-2/-2'], $this->movements($ids['M']));
    }

    public function test_unpaid_orders_release_what_they_held(): void
    {
        $ids = $this->sizes();
        $order = $this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['S'], 'quantity' => 2]]);
        $this->assertSame(0, Inventory::available($this->variant($ids['S'])));

        $this->api('POST', "shop/orders/{$order['id']}/cancel", [], $this->token)->assertOk()->assertJsonPath('order.status', 'cancelled');
        $this->assertSame([2, 0], [(int) $this->variant($ids['S'])->on_hand, (int) $this->variant($ids['S'])->reserved]);
        // S was the product's first variant: its history starts at the opening stock.
        $this->assertSame(['opening:0/0', 'adjustment:2/0', 'reserve:0/2', 'release:0/-2'], $this->movements($ids['S']));

        $expiring = $this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['S'], 'quantity' => 1]]);
        $this->travel(31)->minutes();
        $this->artisan('naoero:shop-reconcile')->assertSuccessful();
        $this->assertSame('released', DB::table('shop_order_items')->where('shop_order_id', $expiring['id'])->value('stock_state'));
        $this->assertSame(0, (int) $this->variant($ids['S'])->reserved);
    }

    public function test_a_product_without_options_needs_no_variant(): void
    {
        $now = now();
        $mug = DB::table('shop_products')->insertGetId([
            'title' => 'Mug', 'slug' => 'mug', 'category' => 'Drinkware', 'type' => 'merch', 'price' => 15, 'currency' => 'AUD',
            'stock_quantity' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $product = collect($this->api('GET', 'shop/products')->json('products'))->firstWhere('title', 'Mug');
        $this->assertFalse($product['hasVariants']);
        $this->assertCount(1, $product['variants']);
        $this->assertSame(4, $product['variants'][0]['stockLeft']);

        $this->order([['productId' => (string) $mug, 'quantity' => 3]]);
        $this->assertSame(1, DB::table('shop_products')->where('id', $mug)->value('stock_quantity'));
    }

    public function test_the_cart_check_says_what_can_be_bought(): void
    {
        $ids = $this->sizes();
        $check = $this->api('POST', 'shop/cart/check', ['items' => [
            ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M'], 'quantity' => 2],
            ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['S'], 'quantity' => 4],
            ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['L'], 'quantity' => 1],
            ['productId' => (string) $this->tee->id, 'quantity' => 1],
            ['productId' => (string) $this->giftCard, 'quantity' => 1],
            ['productId' => '999999', 'quantity' => 1],
        ]])->assertOk()->json();

        $this->assertFalse($check['ok']);
        $this->assertSame([true, false, false, false, true, false], array_column($check['lines'], 'ok'));
        $this->assertSame([null, 'not_enough_stock', 'out_of_stock', 'variant_required', null, 'product_unavailable'], array_column($check['lines'], 'problem'));
        $this->assertSame('Only 2 × Naoero Tee (S) left.', $check['lines'][1]['message']);
        $this->assertSame([5, 2, 0, 0, 10, 0], array_column($check['lines'], 'maxQuantity'));
        // 2 × 35 + 2 × 30 (what is left of S) + a gift card, and delivery.
        $this->assertSame(230, $check['itemsTotal']);
        $this->assertSame(240, $check['total']);
        // Nothing is held by a check.
        $this->assertSame(0, (int) $this->variant($ids['M'])->reserved);
    }

    public function test_customers_hear_when_a_sold_out_size_is_back(): void
    {
        $ids = $this->sizes();
        $this->api('POST', 'shop/stock-alerts', ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['L']])->assertStatus(401);
        $this->api('POST', 'shop/stock-alerts', ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M']], $this->token)
            ->assertStatus(409)->assertJsonPath('error.code', 'in_stock');
        $alert = $this->api('POST', 'shop/stock-alerts', ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['L']], $this->token)
            ->assertCreated()->assertJsonPath('alert.status', 'waiting')->assertJsonPath('alert.title', 'Naoero Tee (L)')->json('alert');
        // Asking twice keeps one alert.
        $this->api('POST', 'shop/stock-alerts', ['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['L']], $this->token)->assertCreated()->assertJsonPath('alert.id', $alert['id']);

        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $ids['L']), ['mode' => 'add', 'quantity' => 3, 'reason' => 'received'])->assertSessionHas('success');

        $this->api('GET', 'me/stock-alerts', [], $this->token)->assertOk()->assertJsonPath('alerts.0.status', 'notified');
        $notice = $this->api('GET', 'me/notifications', [], $this->token)->assertOk()->json('notifications.0');
        $this->assertSame('back_in_stock', $notice['type']);
        $this->assertSame('Back in stock: Naoero Tee (L)', $notice['title']);

        // Once only: more stock sends nothing more.
        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $ids['L']), ['mode' => 'add', 'quantity' => 1, 'reason' => 'found']);
        $this->assertSame(1, DB::table('customer_notifications')->where('type', 'back_in_stock')->count());
        $this->api('DELETE', "me/stock-alerts/{$alert['id']}", [], $this->token)->assertOk();
        $this->api('GET', 'me/stock-alerts', [], $this->token)->assertJsonCount(0, 'alerts');
    }

    public function test_staff_adjustments_are_checked_and_recorded(): void
    {
        $ids = $this->sizes();
        $this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M'], 'quantity' => 4]]);

        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $ids['M']), ['mode' => 'remove', 'quantity' => 2, 'reason' => 'damaged'])
            ->assertSessionHas('error', '4 are held for orders awaiting payment, so on hand can\'t go below 4.');
        $this->actingAs($this->admin)->post(route('admin.inventory.variants.adjust', $ids['M']), ['mode' => 'remove', 'quantity' => 1, 'reason' => 'damaged', 'note' => 'Torn seam'])
            ->assertSessionHas('success');
        $last = DB::table('shop_inventory_movements')->where('variant_id', $ids['M'])->orderByDesc('id')->first();
        $this->assertSame(['adjustment', -1, 4, 'damaged', 'Torn seam', $this->admin->id], [$last->type, (int) $last->on_hand_change, (int) $last->on_hand_after, $last->reason, $last->note, (int) $last->staff_user_id]);

        $clerk = User::factory()->create(['status' => 'active']);
        $clerk->givePermissionTo(Permission::findOrCreate('shop.inventory.view', 'web'));
        $this->actingAs($clerk)->get(route('admin.inventory.stock'))->assertOk()->assertSee('Naoero Tee');
        $this->actingAs($clerk)->post(route('admin.inventory.variants.adjust', $ids['M']), ['mode' => 'add', 'quantity' => 1, 'reason' => 'found'])->assertForbidden();
        $this->actingAs(User::factory()->create(['status' => 'active']))->get(route('admin.inventory.index'))->assertForbidden();
    }

    public function test_editing_a_product_never_changes_its_stock(): void
    {
        $ids = $this->sizes();
        $this->actingAs($this->admin)->put(route('admin.shop.update', $this->tee), [
            'title' => 'Naoero Tee', 'category' => 'Apparel', 'type' => 'merch', 'price' => 32, 'currency' => 'AUD', 'is_active' => 1, 'stock_quantity' => 100,
        ])->assertRedirect();
        $this->assertSame(7, DB::table('shop_products')->where('id', $this->tee->id)->value('stock_quantity'));
        $this->assertSame(5, (int) $this->variant($ids['M'])->on_hand);
    }

    public function test_options_keep_existing_stock_and_switch_off_what_is_removed(): void
    {
        $ids = $this->sizes();
        $this->actingAs($this->admin)->post(route('admin.inventory.product.options', $this->tee), ['options' => [
            ['name' => 'Size', 'values' => 'S, M'], ['name' => 'Colour', 'values' => 'Navy'],
        ]])->assertSessionHas('success');

        $active = DB::table('shop_product_variants')->where('shop_product_id', $this->tee->id)->where('is_active', true)->orderBy('sort_order')->get();
        $this->assertSame(['S / Navy', 'M / Navy'], $active->pluck('title')->all());
        // The S and M variants carried on, with their stock.
        $this->assertSame([$ids['S'], $ids['M']], $active->pluck('id')->all());
        $this->assertSame([2, 5], $active->pluck('on_hand')->map(fn ($n) => (int) $n)->all());
        $this->assertFalse((bool) $this->variant($ids['L'])->is_active);
    }

    public function test_purchase_orders_receive_stock_at_average_cost(): void
    {
        $ids = $this->sizes();
        DB::table('shop_product_variants')->where('id', $ids['M'])->update(['cost_price' => 10]);
        $this->actingAs($this->admin)->post(route('admin.inventory.suppliers.store'), ['name' => 'Pacific Prints', 'email' => 'sales@pacificprints.test', 'is_active' => 1])->assertSessionHas('success');
        $supplier = DB::table('shop_suppliers')->value('id');

        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.store'), [
            'supplier_id' => $supplier, 'shipping_cost' => 20,
            'lines' => [['variant_id' => $ids['M'], 'quantity' => 10, 'unit_cost' => 13], ['variant_id' => $ids['L'], 'quantity' => 4, 'unit_cost' => 12]],
        ])->assertRedirect();
        $po = DB::table('shop_purchase_orders')->first();
        $items = DB::table('shop_purchase_order_items')->where('purchase_order_id', $po->id)->orderBy('id')->get();
        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.ordered', $po->id))->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.receive', $po->id), ['receive' => [$items[0]->id => 5]])->assertSessionHas('success', 'Received 5 unit(s) into stock.');
        $this->assertSame('partially_received', DB::table('shop_purchase_orders')->value('status'));
        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.receive', $po->id), ['receive' => [$items[0]->id => 6]])
            ->assertSessionHas('error', 'Naoero Tee (M): only 5 still to come, not 6.');
        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.receive', $po->id), ['receive' => [$items[0]->id => 5, $items[1]->id => 4]])->assertSessionHas('success');

        $this->assertSame('received', DB::table('shop_purchase_orders')->value('status'));
        $m = $this->variant($ids['M']);
        $this->assertSame(15, (int) $m->on_hand);
        // 5 at 10, then 5 at 13 (11.50), then 5 more at 13: 12.00.
        $this->assertSame('12.00', number_format((float) $m->cost_price, 2));
        $this->assertSame(['receipt', 'receipt'], DB::table('shop_inventory_movements')->where('variant_id', $ids['M'])->where('type', 'receipt')->pluck('type')->all());
        $this->assertSame(4, (int) $this->variant($ids['L'])->on_hand);

        $this->actingAs($this->admin)->get(route('admin.inventory.purchase-orders.show', $po->id))->assertOk()->assertSee($po->po_number)->assertSee('Pacific Prints');
    }

    public function test_reorder_suggestions_fill_a_purchase_order(): void
    {
        $ids = $this->sizes();
        DB::table('shop_product_variants')->where('id', $ids['S'])->update(['reorder_point' => 3, 'reorder_quantity' => 12]);
        DB::table('shop_product_variants')->where('id', $ids['M'])->update(['reorder_point' => 2]);
        // L is sold out (5 below the default threshold), S is at its reorder point, M has plenty.
        $suggested = collect(Purchasing::suggestions())->mapWithKeys(fn ($s) => [$s['variant']->id => $s['suggested']]);
        $this->assertSame([$ids['S'] => 12, $ids['L'] => 10], $suggested->sortKeys()->all());

        // Stock already on order counts: no need to order L twice.
        $this->actingAs($this->admin)->post(route('admin.inventory.purchase-orders.store'), ['lines' => [['variant_id' => $ids['L'], 'quantity' => 10, 'unit_cost' => 8]]]);
        $this->assertSame([$ids['S']], array_map(fn ($s) => $s['variant']->id, Purchasing::suggestions()));
        $this->actingAs($this->admin)->get(route('admin.inventory.purchase-orders.create', ['from' => 'suggestions']))->assertOk()
            ->assertSee('item(s) are at or below their reorder point')->assertSee('Their suggested quantities are filled in below.');
    }

    public function test_a_stock_count_sets_stock_to_what_was_counted(): void
    {
        $ids = $this->sizes();
        $this->actingAs($this->admin)->post(route('admin.inventory.counts.store'), ['category' => 'Apparel'])->assertRedirect();
        $count = DB::table('shop_stock_counts')->first();
        $items = DB::table('shop_stock_count_items')->where('stock_count_id', $count->id)->get()->keyBy('variant_id');
        $this->assertCount(3, $items);

        // A sale during the count: the difference is taken against stock when applied.
        $this->pay($this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M'], 'quantity' => 1]]));
        $this->actingAs($this->admin)->post(route('admin.inventory.counts.save', $count->id), [
            'counted' => [$items[$ids['S']]->id => 2, $items[$ids['M']]->id => 3, $items[$ids['L']]->id => null],
            'then' => 'complete',
        ])->assertSessionHas('success', 'Count applied: 2 item(s) counted, 1 different (1 unit(s) corrected).');

        $this->assertSame([2, 3, 0], [(int) $this->variant($ids['S'])->on_hand, (int) $this->variant($ids['M'])->on_hand, (int) $this->variant($ids['L'])->on_hand]);
        $this->assertSame(-1, (int) DB::table('shop_stock_count_items')->where('id', $items[$ids['M']]->id)->value('variance'));
        $this->assertSame('completed', DB::table('shop_stock_counts')->value('status'));
        $this->actingAs($this->admin)->post(route('admin.inventory.counts.save', $count->id), ['counted' => [$items[$ids['S']]->id => 9]])->assertSessionHas('error', 'This count is closed.');
    }

    public function test_a_refund_can_put_merchandise_back_in_stock(): void
    {
        $ids = $this->sizes();
        $order = $this->order([['productId' => (string) $this->tee->id, 'variantId' => (string) $ids['M'], 'quantity' => 2]]);
        $this->pay($order);
        $item = DB::table('shop_order_items')->where('shop_order_id', $order['id'])->first();

        $this->actingAs($this->admin)->post(route('admin.sales.refund', $order['id']), ['amount' => 35, 'reason' => 'One returned unworn', 'restock' => [$item->id => 1]])
            ->assertSessionHas('success');
        $this->assertSame(4, (int) $this->variant($ids['M'])->on_hand);
        $this->assertSame(1, (int) DB::table('shop_order_items')->where('id', $item->id)->value('returned_quantity'));
        $this->assertSame(['adjustment:5/0', 'reserve:0/2', 'sale:-2/-2', 'return:1/0'], $this->movements($ids['M']));
    }

    public function test_the_inventory_pages_open(): void
    {
        $this->sizes();
        foreach (['admin.inventory.index', 'admin.inventory.stock', 'admin.inventory.movements', 'admin.inventory.alerts', 'admin.inventory.suppliers',
            'admin.inventory.purchase-orders.index', 'admin.inventory.purchase-orders.create', 'admin.inventory.counts.index'] as $route) {
            $this->actingAs($this->admin)->get(route($route))->assertOk();
        }
        $this->actingAs($this->admin)->get(route('admin.inventory.product', $this->tee))->assertOk()->assertSee('TEE-M');
        $this->actingAs($this->admin)->get(route('admin.inventory.stock', ['export' => 'csv']))->assertOk()->assertDownload();
        $this->actingAs($this->admin)->get(route('admin.inventory.movements', ['export' => 'csv']))->assertOk()->assertDownload();
        $this->actingAs($this->admin)->get(route('admin.inventory.product', $this->giftCard))->assertNotFound();
    }
}
