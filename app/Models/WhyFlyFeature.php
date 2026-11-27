<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhyFlyFeature extends Model
{
    use HasFactory;

    protected $table = 'why_fly_features';

    protected $fillable = [
        'title',
        'description',
        'icon',
        'icon_image',
        'link_url',
        'link_text',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope query to only include active features.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query to order features by sort order and creation.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Accessor for full icon image URL.
     */
    public function getIconImageUrlAttribute(): ?string
    {
        if (empty($this->icon_image)) {
            return null;
        }

        if (str_starts_with($this->icon_image, 'http://') || str_starts_with($this->icon_image, 'https://')) {
            return $this->icon_image;
        }

        return url($this->icon_image);
    }
}
