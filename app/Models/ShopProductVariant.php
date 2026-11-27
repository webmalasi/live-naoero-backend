<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stocked version of a shop product (a size, a colour, or the product
 * itself). Stock only changes through App\Services\Shop\Inventory, which
 * writes every change to the ledger; this model is for reading.
 */
class ShopProductVariant extends Model
{
    protected $guarded = ['*'];

    protected $casts = [
        'option_values' => 'array',
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'on_hand' => 'integer',
        'reserved' => 'integer',
        'track_inventory' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class, 'shop_product_id');
    }
}
