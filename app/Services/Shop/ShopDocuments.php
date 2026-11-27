<?php

namespace App\Services\Shop;

use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * A paid order's invoice and payment receipt: numbered when the payment is
 * confirmed, shown as printable pages. Customers open them through links
 * that expire (signed), staff from the admin panel.
 */
class ShopDocuments
{
    /** How long a document link from the app stays open. */
    public const LINK_MINUTES = 60;

    /** Numbers the invoice and receipt. Runs inside the payment's transaction. */
    public static function assign(object $order): void
    {
        if ($order->invoice_number ?? null) {
            return;
        }
        $suffix = substr($order->order_number, 3); // NS-ymd-CODE -> ymd-CODE
        DB::table('shop_orders')->where('id', $order->id)->whereNull('invoice_number')
            ->update(['invoice_number' => 'INV-'.$suffix, 'receipt_number' => 'RCT-'.$suffix]);
    }

    /** @return array{invoiceNumber: string, receiptNumber: string, invoiceUrl: string, receiptUrl: string, expiresAt: string}|null */
    public static function links(object $o): ?array
    {
        if (! ($o->invoice_number ?? null)) {
            return null;
        }
        $until = now()->addMinutes(self::LINK_MINUTES);

        return [
            'invoiceNumber' => $o->invoice_number,
            'receiptNumber' => $o->receipt_number,
            'invoiceUrl' => ShopCheckout::origin().URL::temporarySignedRoute('api.v1.shop.orders.invoice', $until, ['id' => $o->id], absolute: false),
            'receiptUrl' => ShopCheckout::origin().URL::temporarySignedRoute('api.v1.shop.orders.receipt', $until, ['id' => $o->id], absolute: false),
            'expiresAt' => Js::iso($until),
        ];
    }

    /**
     * What the invoice and receipt pages show.
     *
     * @return array<string, mixed>
     */
    public static function data(string $orderId): ?array
    {
        $o = DB::table('shop_orders as o')->join('customers as c', 'c.id', '=', 'o.customer_id')->where('o.id', $orderId)
            ->select('o.*', 'c.first_name', 'c.last_name', 'c.zenith_login', 'c.email as customer_main_email')->first();
        if (! $o || ! $o->invoice_number) {
            return null;
        }

        return [
            'order' => $o,
            'customerName' => trim("{$o->first_name} {$o->last_name}") ?: $o->contact_email,
            'address' => $o->shipping_address ? json_decode($o->shipping_address, true) : null,
            'items' => DB::table('shop_order_items')->where('shop_order_id', $o->id)->orderBy('id')->get(),
            'payment' => $o->payment_details ? json_decode($o->payment_details, true) : [],
            'refunds' => DB::table('shop_refunds')->where('shop_order_id', $o->id)->where('status', 'succeeded')->orderBy('created_at')->get(),
            'giftCards' => DB::table('gift_card_orders')->where('shop_order_id', $o->id)->orderBy('id')->get(),
            'seller' => config('naoero.shop.seller'),
        ];
    }
}
