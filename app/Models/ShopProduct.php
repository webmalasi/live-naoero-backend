<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\Shop\Inventory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'category',
        'type',
        'price',
        'compare_at_price',
        'currency',
        'image',
        'description',
        'short_description',
        'sku',
        'stock_quantity',
        'rating',
        'reviews_count',
        'is_recommended',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'rating' => 'decimal:1',
        'reviews_count' => 'integer',
        'is_recommended' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'options' => 'array',
    ];

    /** Sizes, colours...: each with its own SKU and stock (see ProductVariants). */
    public function variants(): HasMany
    {
        return $this->hasMany(ShopProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::creating(function (ShopProduct $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->title);
                $count = static::where('slug', 'like', "{$product->slug}%")->count();
                if ($count > 0) {
                    $product->slug .= '-' . ($count + 1);
                }
            }
            if (empty($product->sku)) {
                $prefix = ($product->type === 'gift_card' || $product->category === 'Gift Card') ? 'GC' : 'NA';
                $product->sku = $prefix . '-' . strtoupper(Str::random(6));
            }
            if (empty($product->currency)) {
                $product->currency = 'AUD';
            }
        });

        // Merchandise is stocked per variant: a new product starts with one,
        // holding its opening stock.
        static::created(function (ShopProduct $product) {
            app(Inventory::class)->createDefaultVariant($product, DB::table('shop_products')->where('id', $product->id)->value('stock_quantity'));
        });
    }

    /**
     * Scope for active products.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for recommended items (Recommended For U section).
     */
    public function scopeRecommended(Builder $query): Builder
    {
        return $query->where('is_recommended', true);
    }

    /**
     * Scope for featured items.
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for gift cards specifically.
     */
    public function scopeGiftCards(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('type', 'gift_card')
              ->orWhere('category', 'Gift Card')
              ->orWhere('category', 'Gift Cards');
        });
    }

    /**
     * Scope for merchandise items.
     */
    public function scopeMerch(Builder $query): Builder
    {
        return $query->where('type', '!=', 'gift_card')
                     ->where('category', '!=', 'Gift Card')
                     ->where('category', '!=', 'Gift Cards');
    }

    /**
     * Scope for ordering.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id', 'desc');
    }

    /**
     * Format currency display.
     */
    public function getFormattedPriceAttribute(): string
    {
        $currency = $this->currency ?: 'AUD';
        return '$' . number_format((float) $this->price, 2) . ' ' . $currency;
    }

    /**
     * Format compare-at price display.
     */
    public function getFormattedCompareAtPriceAttribute(): ?string
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }
        $currency = $this->currency ?: 'AUD';
        return '$' . number_format((float) $this->compare_at_price, 2) . ' ' . $currency;
    }

    /**
     * Calculate discount percentage if original price is set.
     */
    public function getDiscountPercentAttribute(): ?int
    {
        if (!$this->compare_at_price || $this->compare_at_price <= $this->price) {
            return null;
        }
        $saving = $this->compare_at_price - $this->price;
        return (int) round(($saving / $this->compare_at_price) * 100);
    }

    /**
     * Check stock availability.
     */
    public function getIsInStockAttribute(): bool
    {
        if ($this->type === 'gift_card' || $this->category === 'Gift Card') {
            return true;
        }
        return ($this->stock_quantity === null || $this->stock_quantity > 0);
    }

    /**
     * Accessor for full image URL.
     */
    public function getImageUrlAttribute(): string
    {
        if (empty($this->image)) {
            return asset('assets/giftcard/merch.png');
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        if (str_starts_with($this->image, '/storage') || str_starts_with($this->image, 'storage/')) {
            return asset(ltrim($this->image, '/'));
        }

        return asset($this->image);
    }
}
