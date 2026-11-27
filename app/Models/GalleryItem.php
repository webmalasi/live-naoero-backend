<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GalleryItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'destination',
        'category',
        'image',
        'image_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'src',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->latest();
    }

    public function getSrcAttribute(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        if (! empty($this->image)) {
            if (Str::startsWith($this->image, ['http://', 'https://', '//'])) {
                return $this->image;
            }

            return asset('storage/'.ltrim($this->image, '/'));
        }

        return 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=800&q=80';
    }
}
