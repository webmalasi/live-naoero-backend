<?php

namespace App\Services\Shop;

use App\Exceptions\ApiException;
use App\Models\ActivityLog;
use App\Services\Notifications\CustomerNotices;
use App\Services\Support\Js;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Help with the shop: a customer writes about an order (or the shop in
 * general), staff answer in the admin panel, and the conversation goes on
 * until it is closed. Every reply from staff is a notice to the customer;
 * requests about an order are on its timeline.
 *
 * open (waiting for us) -> answered (waiting for the customer) -> closed
 */
class ShopSupport
{
    public const TOPICS = [
        'order_status' => 'Where is my order?',
        'delivery' => 'Delivery or address',
        'damaged_item' => 'Damaged or faulty item',
        'wrong_item' => 'Wrong item received',
        'payment' => 'Payment or refund',
        'gift_card' => 'Gift card',
        'return' => 'Return or exchange',
        'cancellation' => 'Cancel my order',
        'other' => 'Something else',
    ];

    public const STATUSES = ['open' => 'Waiting for us', 'answered' => 'Answered', 'closed' => 'Closed'];

    public function __construct(
        private readonly ShopEvents $events,
        private readonly CustomerNotices $notices,
    ) {}

    private static function number(): string
    {
        do {
            $number = 'SR-'.now()->utc()->format('ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (DB::table('shop_support_requests')->where('number', $number)->exists());

        return $number;
    }

    /** A new request; about one of the customer's orders when $orderId is given. */
    public function create(object $customer, ?string $orderId, string $topic, string $message, string $channel, string $author = 'customer'): array
    {
        if (! isset(self::TOPICS[$topic])) {
            throw ApiException::invalid('topic must be one of: '.implode(', ', array_keys(self::TOPICS)).'.');
        }
        $order = null;
        if ($orderId !== null) {
            $order = Str::isUuid($orderId) ? DB::table('shop_orders')->where('id', $orderId)->where('customer_id', $customer->id)->first() : null;
            if (! $order) {
                throw ShopOrders::notFound();
            }
        }
        $open = DB::table('shop_support_requests')->where('customer_id', $customer->id)->where('status', '!=', 'closed')->count();
        if ($open >= 10) {
            throw new ApiException(429, 'too_many_requests', 'You have 10 open help requests. Reply in one of them, or wait for our answer.');
        }
        $id = (string) Str::uuid();
        $number = self::number();
        DB::transaction(function () use ($id, $number, $customer, $order, $topic, $message, $channel, $author) {
            DB::table('shop_support_requests')->insert([
                'id' => $id, 'number' => $number, 'customer_id' => $customer->id, 'shop_order_id' => $order?->id,
                'topic' => $topic, 'subject' => self::TOPICS[$topic].($order ? " ({$order->order_number})" : ''), 'status' => 'open',
                'channel' => $channel, 'last_message_at' => Js::dbTime(), 'created_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
            DB::table('shop_support_messages')->insert(['support_request_id' => $id, 'author' => $author, 'body' => mb_substr($message, 0, 4000), 'created_at' => Js::dbTime()]);
        });
        if ($order) {
            $this->events->record($order->id, 'support_requested', 'info', "Help request {$number}: ".self::TOPICS[$topic], $author === 'system' ? 'system' : 'customer', detail: ['requestId' => $id]);
        }

        return $this->show($customer, $id);
    }

    /** @return list<array<string, mixed>> */
    public function list(object $customer, ?string $orderId = null): array
    {
        return DB::table('shop_support_requests')->where('customer_id', $customer->id)
            ->when($orderId, fn ($q) => $q->where('shop_order_id', $orderId))
            ->orderByRaw("case when status = 'closed' then 1 else 0 end")->orderByDesc('last_message_at')->limit(100)->get()
            ->map(fn ($r) => self::present($r))->all();
    }

    public function show(object $customer, string $id): array
    {
        $r = $this->owned($customer, $id);

        return self::present($r, DB::table('shop_support_messages')->where('support_request_id', $id)->orderBy('created_at')->orderBy('id')->get());
    }

    public function reply(object $customer, string $id, string $message): array
    {
        $r = $this->owned($customer, $id);
        DB::transaction(function () use ($r, $message) {
            DB::table('shop_support_messages')->insert(['support_request_id' => $r->id, 'author' => 'customer', 'body' => mb_substr($message, 0, 4000), 'created_at' => Js::dbTime()]);
            // A reply reopens a closed request.
            DB::table('shop_support_requests')->where('id', $r->id)->update(['status' => 'open', 'closed_at' => null, 'last_message_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);
        });

        return $this->show($customer, $id);
    }

    public function close(object $customer, string $id): array
    {
        $r = $this->owned($customer, $id);
        DB::table('shop_support_requests')->where('id', $r->id)->update(['status' => 'closed', 'closed_at' => Js::dbTime(), 'updated_at' => Js::dbTime()]);

        return $this->show($customer, $id);
    }

    // ------------------------------------------------------------ staff

    public function staffReply(string $id, string $message, int $staffUserId, bool $close = false): void
    {
        $r = DB::table('shop_support_requests')->where('id', $id)->first() ?? throw new RuntimeException('Help request not found.');
        DB::transaction(function () use ($r, $message, $staffUserId, $close) {
            DB::table('shop_support_messages')->insert(['support_request_id' => $r->id, 'author' => 'staff', 'staff_user_id' => $staffUserId, 'body' => mb_substr($message, 0, 4000), 'created_at' => Js::dbTime()]);
            DB::table('shop_support_requests')->where('id', $r->id)->update([
                'status' => $close ? 'closed' : 'answered', 'closed_at' => $close ? Js::dbTime() : null,
                'last_message_at' => Js::dbTime(), 'updated_at' => Js::dbTime(),
            ]);
        });
        $count = DB::table('shop_support_messages')->where('support_request_id', $r->id)->where('author', 'staff')->count();
        $this->notices->send($r->customer_id, 'shop_support_reply', "Reply to your help request {$r->number}", mb_strimwidth($message, 0, 300, '…'),
            ['requestId' => $r->id], shopOrderId: $r->shop_order_id, dedupeKey: "support:{$r->id}:{$count}");
        if ($r->shop_order_id) {
            $this->events->record($r->shop_order_id, 'support_replied', 'info', "Replied to help request {$r->number}".($close ? ' and closed it' : ''), 'staff', detail: ['requestId' => $r->id], staffUserId: $staffUserId);
        }
        $this->audit("Replied to help request {$r->number}");
    }

    public function staffSetStatus(string $id, string $status, int $staffUserId): void
    {
        if (! isset(self::STATUSES[$status])) {
            throw new RuntimeException('Choose a status.');
        }
        $r = DB::table('shop_support_requests')->where('id', $id)->first() ?? throw new RuntimeException('Help request not found.');
        DB::table('shop_support_requests')->where('id', $id)->update(['status' => $status, 'closed_at' => $status === 'closed' ? Js::dbTime() : null, 'updated_at' => Js::dbTime()]);
        $this->audit("Help request {$r->number} marked ".self::STATUSES[$status]);
    }

    private function owned(object $customer, string $id): object
    {
        return (Str::isUuid($id) ? DB::table('shop_support_requests')->where('id', $id)->where('customer_id', $customer->id)->first() : null)
            ?? throw new ApiException(404, 'support_request_not_found', "We couldn't find that help request on your account.");
    }

    /** @return array<string, mixed> */
    public static function present(object $r, $messages = null): array
    {
        $orderNumber = $r->shop_order_id ? DB::table('shop_orders')->where('id', $r->shop_order_id)->value('order_number') : null;
        $out = [
            'id' => $r->id,
            'number' => $r->number,
            'orderId' => $r->shop_order_id,
            'orderNumber' => $orderNumber,
            'topic' => $r->topic,
            'topicLabel' => self::TOPICS[$r->topic] ?? $r->topic,
            'subject' => $r->subject,
            'status' => $r->status,
            'statusLabel' => self::STATUSES[$r->status] ?? $r->status,
            'createdAt' => Js::iso($r->created_at),
            'lastMessageAt' => Js::iso($r->last_message_at),
            'closedAt' => Js::iso($r->closed_at),
        ];
        if ($messages !== null) {
            $out['messages'] = collect($messages)->map(fn ($m) => [
                'author' => $m->author,
                'authorName' => match ($m->author) {
                    'staff' => 'Naoero Shop team', 'system' => 'Naoero Shop', default => 'You'
                },
                'body' => $m->body,
                'at' => Js::iso($m->created_at),
            ])->values()->all();
        }

        return $out;
    }

    private function audit(string $description): void
    {
        try {
            ActivityLog::log('updated', 'Shop Sales', $description);
        } catch (Throwable) {
            // The audit log never blocks the step.
        }
    }
}
