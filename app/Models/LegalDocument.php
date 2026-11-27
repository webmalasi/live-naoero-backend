<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LegalDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'heading',
        'subheading',
        'badge_text',
        'banner_image',
        'pdf_url',
        'contact_email',
        'summary',
        'sections',
        'content',
        'last_reviewed_at',
        'next_review_at',
        'is_active',
    ];

    protected $casts = [
        'sections' => 'array',
        'last_reviewed_at' => 'date:Y-m-d',
        'next_review_at' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public function getBannerImageUrlAttribute(): ?string
    {
        if (empty($this->banner_image)) {
            return null;
        }

        if (str_starts_with($this->banner_image, 'http://') || str_starts_with($this->banner_image, 'https://')) {
            return $this->banner_image;
        }

        return url($this->banner_image);
    }

    public function getDownloadPdfUrlAttribute(): ?string
    {
        if (empty($this->pdf_url)) {
            return null;
        }

        if (str_starts_with($this->pdf_url, 'http://') || str_starts_with($this->pdf_url, 'https://')) {
            return $this->pdf_url;
        }

        return url($this->pdf_url);
    }
}
