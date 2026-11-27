<?php

namespace Database\Seeders;

use App\Models\GiftCardOrder;
use App\Models\ShopBanner;
use App\Models\ShopProduct;
use Illuminate\Database\Seeder;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Homepage Shop & Gift Cards Banner Cards
        $banners = [
            [
                'id' => 1,
                'title' => 'Naoero Airlines Shop',
                'description' => 'Bring a piece of the Pacific home with our exclusive collection of official airline souvenirs and premium gear.',
                'cta' => 'Shop Collection',
                'filter' => 'Merch',
                'link' => '/gift-cards',
                'image' => 'assets/home/Nauruairline.png',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 2,
                'title' => 'Gift Cards',
                'description' => 'The perfect gift for any occasion. Redeemable for both flights and official merchandise.',
                'cta' => 'Buy Gift Cards',
                'filter' => 'Gift Cards',
                'link' => '/gift-cards',
                'image' => 'assets/home/giftcards.png',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($banners as $banner) {
            ShopBanner::updateOrCreate(['id' => $banner['id']], $banner);
        }

        // 2. Shop Products & Gift Cards
        $products = [
            // Recommended For You
            [
                'title' => 'Signature Hoodie',
                'slug' => 'signature-hoodie',
                'category' => 'Apparel',
                'type' => 'merch',
                'price' => 58.00,
                'compare_at_price' => 75.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/hoodie.png',
                'short_description' => 'Heavyweight Pacific blue fleece with gold embroidered 12-point star.',
                'description' => '<p>Designed for comfort during trans-Pacific journeys. Premium 380 GSM organic cotton blend fleece featuring the iconic Naoero 12-point star on the chest and subtle airline typography on the sleeve.</p><ul><li>380 GSM Heavyweight Brushed Cotton</li><li>Double-lined hood with custom metal aglets</li><li>Kangaroo front pocket with reinforced stitching</li><li>Preshrunk fabric for lasting fit</li></ul>',
                'sku' => 'NA-HD-001',
                'stock_quantity' => 150,
                'rating' => 4.9,
                'reviews_count' => 42,
                'is_recommended' => true,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Steel Bottle',
                'slug' => 'steel-bottle',
                'category' => 'Drinkware',
                'type' => 'merch',
                'price' => 28.00,
                'compare_at_price' => 35.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/bottle.png',
                'short_description' => 'Double-wall vacuum insulated 750ml flask with leakproof seal.',
                'description' => '<p>Keep your tropical hydration ice-cold for up to 24 hours or coffee hot for 12 hours. Matte navy powder-coat finish with laser-etched Naoero Airlines crest.</p><ul><li>750ml (25 oz) capacity</li><li>18/8 food-grade stainless steel</li><li>100% BPA and toxin free</li><li>Fits standard aircraft and vehicle cup holders</li></ul>',
                'sku' => 'NA-BT-002',
                'stock_quantity' => 200,
                'rating' => 4.8,
                'reviews_count' => 31,
                'is_recommended' => true,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'Signature Cap',
                'slug' => 'signature-cap',
                'category' => 'Caps',
                'type' => 'merch',
                'price' => 22.00,
                'compare_at_price' => 28.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/cap.png',
                'short_description' => '6-panel structured cotton twill cap with brass adjuster.',
                'description' => '<p>Classic aviation cap constructed from breathable 100% heavy washed chino cotton twill. Features high-definition 3D gold embroidery of the airline crest.</p><ul><li>Adjustable antique brass buckle strap</li><li>Pre-curved visor with contrast gold underside</li><li>Moisture-wicking interior sweatband</li><li>One size fits most</li></ul>',
                'sku' => 'NA-CP-003',
                'stock_quantity' => 180,
                'rating' => 4.7,
                'reviews_count' => 27,
                'is_recommended' => true,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            // Shop Merch Section
            [
                'title' => 'Plush Teddy',
                'slug' => 'plush-teddy',
                'category' => 'Toys',
                'type' => 'merch',
                'price' => 25.00,
                'compare_at_price' => 30.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/teddy.png',
                'short_description' => 'Captain Teddy Bear dressed in official pilot uniform and aviator goggles.',
                'description' => '<p>The beloved Naoero Airlines mascot! High-quality plush captain bear wearing tailored pilot jacket with epaulets and gold flight wings badge. An unforgettable keepsake for young aviators.</p><ul><li>Height: 28cm (11 inches)</li><li>Hypoallergenic soft plush & safety embroidered eyes</li><li>CE and ASTM certified child-safe</li></ul>',
                'sku' => 'NA-TD-004',
                'stock_quantity' => 95,
                'rating' => 5.0,
                'reviews_count' => 56,
                'is_recommended' => false,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'title' => 'Naoero Airlines Vintage Tee',
                'slug' => 'naoero-airlines-vintage-tee',
                'category' => 'Tshirts',
                'type' => 'merch',
                'price' => 32.00,
                'compare_at_price' => 40.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/merch.png',
                'short_description' => 'Vintage Pacific retro route map t-shirt in 100% combed cotton.',
                'description' => '<p>Celebrates over 50 years of Pacific island aviation. Features a vintage illustrated route map graphic bridging Brisbane, Nauru, Tarawa, Majuro, Pohnpei, and Koror.</p><ul><li>100% Combed Ringspun Cotton (190 GSM)</li><li>Soft water-based vintage screen print</li><li>Ribbed collar with reinforced shoulder tape</li></ul>',
                'sku' => 'NA-TS-005',
                'stock_quantity' => 120,
                'rating' => 4.9,
                'reviews_count' => 19,
                'is_recommended' => false,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'title' => 'Pacific Flight Tumbler',
                'slug' => 'pacific-flight-tumbler',
                'category' => 'Drinkware',
                'type' => 'merch',
                'price' => 30.00,
                'compare_at_price' => 36.00,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/merch2.png',
                'short_description' => '500ml stainless steel coffee tumbler with splash-proof slider lid.',
                'description' => '<p>Ergonomic stainless travel tumbler engineered for passenger flights and everyday commutes. Fits into airplane seatback pockets easily.</p><ul><li>500ml / 17oz volume</li><li>Splash-proof magnetic slide lid</li><li>Non-slip silicone base ring</li></ul>',
                'sku' => 'NA-TB-006',
                'stock_quantity' => 85,
                'rating' => 4.8,
                'reviews_count' => 14,
                'is_recommended' => false,
                'is_featured' => false,
                'is_active' => true,
                'sort_order' => 6,
            ],
            // Shop Gift Cards Section
            [
                'title' => 'AUD 500 Gift Card',
                'slug' => 'aud-500-gift-card',
                'category' => 'Gift Card',
                'type' => 'gift_card',
                'price' => 500.00,
                'compare_at_price' => null,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/500aud.png',
                'short_description' => 'AUD $500 travel voucher redeemable for any scheduled flight or merchandise.',
                'description' => '<p>Give the gift of Pacific island exploration. Redeemable online and at all airport ticketing desks for flights across Australia, Nauru, Kiribati, Marshall Islands, Fiji, and Micronesia.</p><ul><li>Valid for 3 years from purchase date</li><li>Instant digital delivery with custom personalized message</li><li>Transferable and combinable with other promotions</li></ul>',
                'sku' => 'GC-500-AUD',
                'stock_quantity' => 999,
                'rating' => 5.0,
                'reviews_count' => 68,
                'is_recommended' => false,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'title' => 'AUD 1000 Gift Card',
                'slug' => 'aud-1000-gift-card',
                'category' => 'Gift Card',
                'type' => 'gift_card',
                'price' => 1000.00,
                'compare_at_price' => null,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/1000AUD.png',
                'short_description' => 'AUD $1000 travel certificate for round-trips and island hopping.',
                'description' => '<p>Ideal for holiday getaways and family reunions across the Pacific island network. Redeemable for flight tickets, extra baggage allowances, and premium seat selections.</p><ul><li>Valid for 3 years from purchase date</li><li>Personalized digital voucher dispatch via email</li><li>No hidden blackout dates</li></ul>',
                'sku' => 'GC-1000-AUD',
                'stock_quantity' => 999,
                'rating' => 5.0,
                'reviews_count' => 84,
                'is_recommended' => false,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'title' => 'AUD 2000 Gift Card',
                'slug' => 'aud-2000-gift-card',
                'category' => 'Gift Card',
                'type' => 'gift_card',
                'price' => 2000.00,
                'compare_at_price' => null,
                'currency' => 'AUD',
                'image' => 'assets/giftcard/2000AUD.png',
                'short_description' => 'AUD $2000 executive travel certificate for luxury holidays and charters.',
                'description' => '<p>The ultimate travel gift for milestone celebrations, corporate rewards, and luxury island retreats across Micronesia and the South Pacific.</p><ul><li>Valid for 3 years from purchase date</li><li>Full customer service concierge booking support</li><li>Complimentary physical presentation gift box option</li></ul>',
                'sku' => 'GC-2000-AUD',
                'stock_quantity' => 999,
                'rating' => 5.0,
                'reviews_count' => 39,
                'is_recommended' => false,
                'is_featured' => true,
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($products as $prod) {
            ShopProduct::updateOrCreate(['slug' => $prod['slug']], $prod);
        }

        // 3. Seed Sample Gift Card Orders & Vouchers
        $sampleOrders = [
            [
                'order_number' => 'GC-2026-N74821',
                'buyer_name' => 'Dr. Michael Chen',
                'buyer_email' => 'michael.chen@pacific-corp.com',
                'buyer_phone' => '+61 412 345 678',
                'recipient_name' => 'Amanda Chen',
                'recipient_email' => 'amanda.chen@example.com',
                'amount' => 1000.00,
                'balance_remaining' => 1000.00,
                'currency' => 'AUD',
                'quantity' => 1,
                'personal_message' => 'Happy 30th Birthday Amanda! Can not wait for our Pacific diving holiday in Palau!',
                'delivery_type' => 'digital',
                'payment_method' => 'credit_card',
                'status' => 'issued',
                'voucher_code' => 'ON-1000-MC30-PALAU',
                'admin_notes' => 'Payment verified via Stripe Gateway. Voucher issued automatically.',
            ],
            [
                'order_number' => 'GC-2026-K92014',
                'buyer_name' => 'Sarah Jenkins',
                'buyer_email' => 'sjenkins@brisbane-law.com.au',
                'buyer_phone' => '+61 408 987 654',
                'recipient_name' => 'David Jenkins',
                'recipient_email' => 'david.j@example.com',
                'amount' => 500.00,
                'balance_remaining' => 500.00,
                'currency' => 'AUD',
                'quantity' => 1,
                'personal_message' => 'Enjoy your fishing expedition in Nauru and Tarawa!',
                'delivery_type' => 'digital',
                'payment_method' => 'credit_card',
                'status' => 'confirmed',
                'voucher_code' => 'ON-500-DJ26-NAURU',
                'admin_notes' => 'Corporate account booking. Invoice paid.',
            ],
        ];

        foreach ($sampleOrders as $order) {
            GiftCardOrder::updateOrCreate(['order_number' => $order['order_number']], $order);
        }
    }
}
