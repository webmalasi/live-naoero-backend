<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GiftCardOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'shop_product_id',
        'buyer_name',
        'buyer_email',
        'buyer_phone',
        'recipient_name',
        'recipient_email',
        'amount',
        'balance_remaining',
        'currency',
        'quantity',
        'personal_message',
        'delivery_type',
        'payment_method',
        'postal_address',
        'status',
        'voucher_code',
        'expires_at',
        'admin_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_remaining' => 'decimal:2',
        'quantity' => 'integer',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (GiftCardOrder $order) {
            if (empty($order->order_number)) {
                $order->order_number = 'GC-' . date('Y') . '-' . strtoupper(Str::random(6));
            }
            if (empty($order->voucher_code)) {
                $denom = (int) $order->amount;
                $prefix = $denom > 0 ? "ON-{$denom}" : 'ON-GC';
                $order->voucher_code = $prefix . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            }
            if ($order->balance_remaining === null) {
                $order->balance_remaining = $order->amount * ($order->quantity ?: 1);
            }
            if (empty($order->currency)) {
                $order->currency = 'AUD';
            }
            if (empty($order->expires_at)) {
                // Default: 3 years validity from issue date
                $order->expires_at = now()->addYears(3);
            }
        });
    }

    public function product()
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', 'issued');
    }

    public function scopeActiveVouchers(Builder $query): Builder
    {
        return $query->whereIn('status', ['confirmed', 'issued'])
                     ->where('balance_remaining', '>', 0)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                     });
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'confirmed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'issued' => 'bg-blue-50 text-blue-700 border-blue-200',
            'redeemed' => 'bg-purple-50 text-purple-700 border-purple-200',
            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
            default => 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }

    public function getFormattedAmountAttribute(): string
    {
        $currency = $this->currency ?: 'AUD';
        return '$' . number_format((float) $this->amount, 2) . ' ' . $currency;
    }

    public function getFormattedBalanceAttribute(): string
    {
        $bal = $this->balance_remaining !== null ? $this->balance_remaining : $this->amount;
        $currency = $this->currency ?: 'AUD';
        return '$' . number_format((float) $bal, 2) . ' ' . $currency;
    }
}
