<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Destination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'country',
        'flag',
        'thumbnail',
        'banner_image',
        'short_description',
        'quote',
        'discover_heading',
        'intro',
        'banner_text',
        'closing_paragraph',
        'why_visit',
        'info_sections',
        'detailed_info',
        'things_to_do_intro',
        'things_to_do',
        'stays_intro',
        'stays',
        'is_featured',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'why_visit' => 'array',
        'info_sections' => 'array',
        'detailed_info' => 'array',
        'things_to_do' => 'array',
        'stays' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'thumbnail_url',
        'banner_image_url',
    ];

    /**
     * Automatically generate slug from name if not provided.
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($destination) {
            if (empty($destination->slug)) {
                $destination->slug = Str::slug($destination->name);
            } else {
                $destination->slug = Str::slug($destination->slug);
            }
        });
    }

    /**
     * Scope a query to only include active destinations.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include featured destinations (for Explore section).
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope a query to order by sort_order and name.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Get the full URL for the thumbnail.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        return static::formatImageUrl($this->thumbnail);
    }

    /**
     * Get the full URL for the banner image.
     */
    public function getBannerImageUrlAttribute(): ?string
    {
        return static::formatImageUrl($this->banner_image);
    }

    /**
     * Format an image path or URL into a full valid public URL.
     */
    public static function formatImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//', 'data:'])) {
            return $path;
        }

        if (Str::startsWith($path, ['assets/', '/assets/'])) {
            return asset(ltrim($path, '/'));
        }

        if (Str::startsWith($path, ['storage/', '/storage/'])) {
            return asset(ltrim($path, '/'));
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}
