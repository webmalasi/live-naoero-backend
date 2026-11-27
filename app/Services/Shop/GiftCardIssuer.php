<?php

namespace App\Services\Shop;

use App\Mail\GiftCardIssued;
use App\Models\GiftCardOrder;
use App\Services\Logging\ServerLog;
use App\Services\Support\Js;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Issues the gift cards of a paid order: one voucher (gift_card_orders,
 * the website's gift card table, so its "verify voucher" endpoint and the
 * admin panel see them) per card bought, each with its own code and
 * balance. Idempotent: an order's cards are issued once.
 */
class GiftCardIssuer
{
    public function __construct(
        private readonly ServerLog $log,
        private readonly ShopEvents $events,
    ) {}

    /**
     * Must run inside the transaction that marks the order paid.
     *
     * @return int the number of cards issued now
     */
    public function issue(object $order, object $customer): int
    {
        if (DB::table('gift_card_orders')->where('shop_order_id', $order->id)->exists()) {
            return 0;
        }
        $profile = DB::table('customers')->where('id', $customer->id)->first(['first_name', 'last_name']);
        $buyerName = trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) ?: $order->contact_email;
        $phone = DB::table('customer_phones')->where('customer_id', $customer->id)->orderBy('position')->value('phone_number');

        $issued = 0;
        $lines = DB::table('shop_order_items')->where('shop_order_id', $order->id)->where('type', 'gift_card')->orderBy('id')->get();
        foreach ($lines as $line) {
            $gift = json_decode((string) $line->gift, true) ?: [];
            for ($i = 0; $i < $line->quantity; $i++) {
                $card = GiftCardOrder::create([
                    'shop_product_id' => $line->shop_product_id,
                    'buyer_name' => $buyerName,
                    'buyer_email' => $order->contact_email,
                    'buyer_phone' => $phone,
                    'recipient_name' => $gift['recipientName'] ?? null,
                    'recipient_email' => $gift['recipientEmail'] ?? null,
                    'personal_message' => $gift['message'] ?? null,
                    'amount' => $line->unit_price,
                    'balance_remaining' => $line->unit_price,
                    'currency' => $order->currency,
                    'quantity' => 1,
                    'delivery_type' => 'digital',
                    'payment_method' => 'nab_card',
                    'status' => 'issued',
                    'expires_at' => now()->addYears((int) config('naoero.shop.gift_card_validity_years', 3)),
                    'admin_notes' => "Paid in the app, order {$order->order_number}.",
                ]);
                DB::table('gift_card_orders')->where('id', $card->id)->update(['shop_order_id' => $order->id, 'customer_id' => $customer->id]);
                $issued++;
                $for = ($gift['recipientName'] ?? null) ? " for {$gift['recipientName']}" : '';
                $this->events->record($order->id, 'gift_card_issued', 'success', "Gift card {$card->voucher_code} issued{$for} ({$order->currency} {$line->unit_price})", 'system', $line->unit_price, [
                    'giftCardId' => $card->id, 'code' => $card->voucher_code, 'recipientEmail' => $gift['recipientEmail'] ?? null,
                ]);

                // After the commit: the card exists before anyone is told about it.
                DB::afterCommit(fn () => $this->notify($card->id));
            }
        }

        return $issued;
    }

    /**
     * Emails the card to its recipient (and the buyer). Never fails the
     * order; the outcome is on the order's timeline.
     */
    public function notify(int $cardId, ?int $staffUserId = null): bool
    {
        $card = GiftCardOrder::find($cardId);
        if (! $card) {
            return false;
        }
        $orderId = DB::table('gift_card_orders')->where('id', $cardId)->value('shop_order_id');
        $to = array_values(array_unique(array_filter([$card->recipient_email, $card->buyer_email])));
        try {
            Mail::to($to)->queue(new GiftCardIssued($card));
        } catch (Throwable $e) {
            $this->log->warn('shop', 'gift card email not queued', ['card' => $cardId, 'error' => $e->getMessage()]);
            if ($orderId) {
                $this->events->record($orderId, 'gift_card_emailed', 'failed', "Email for {$card->voucher_code} could not be sent: {$e->getMessage()}", $staffUserId ? 'staff' : 'system', staffUserId: $staffUserId);
            }

            return false;
        }
        if ($orderId) {
            $this->events->record($orderId, 'gift_card_emailed', 'success', ($staffUserId ? 'Resent' : 'Sent').": {$card->voucher_code} emailed to ".implode(', ', $to), $staffUserId ? 'staff' : 'system', staffUserId: $staffUserId);
        }

        return true;
    }

    /** The gift cards as the app shows them. */
    public static function present(object $card): array
    {
        $expires = $card->expires_at ? CarbonImmutable::parse($card->expires_at) : null;
        $balance = $card->balance_remaining ?? $card->amount;

        return [
            'code' => $card->voucher_code,
            'orderNumber' => $card->order_number,
            'amount' => Js::round2($card->amount),
            'balance' => Js::round2($balance),
            'currency' => $card->currency ?: 'AUD',
            'status' => $expires && $expires->isPast() ? 'expired' : ((float) $balance <= 0 ? 'redeemed' : $card->status),
            'recipientName' => $card->recipient_name,
            'recipientEmail' => $card->recipient_email,
            'message' => $card->personal_message,
            'issuedAt' => Js::iso($card->created_at),
            'expiresAt' => $expires ? $expires->toDateString() : null,
        ];
    }
}
