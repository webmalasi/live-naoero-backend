<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageBanner extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_key',
        'page_name',
        'page_route',
        'banner_badge_text',
        'banner_image',
        'banner_heading',
        'banner_description',
        'banner_button_text',
        'banner_button_link',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Scope for active banners.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered banners.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Get full absolute image URL.
     */
    public function getBannerImageUrlAttribute(): string
    {
        if (empty($this->banner_image)) {
            return asset('/assets/banner-default.jpg');
        }

        if (str_starts_with($this->banner_image, 'http://') || str_starts_with($this->banner_image, 'https://')) {
            return $this->banner_image;
        }

        return url($this->banner_image);
    }

    /**
     * Retrieve active banner for a specific page key or route.
     */
    public static function findByPage(string $key): ?self
    {
        $normalizedKey = strtolower(trim(str_replace(['/', '_'], ['', '-'], $key)));

        return static::active()
            ->where(function ($q) use ($key, $normalizedKey) {
                $q->where('page_key', $key)
                  ->orWhere('page_key', $normalizedKey)
                  ->orWhere('page_route', $key)
                  ->orWhere('page_route', '/' . ltrim($key, '/'));
            })
            ->first();
    }
}
