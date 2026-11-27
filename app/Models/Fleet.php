<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Fleet extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'registration',
        'category',
        'seating',
        'configuration',
        'image',
        'caption',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'image_url',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePassenger(Builder $query): Builder
    {
        return $query->where('category', 'passenger');
    }

    public function scopeFreight(Builder $query): Builder
    {
        return $query->where('category', 'freight');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('registration', 'asc');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (Str::startsWith($this->image, ['http://', 'https://', '//'])) {
            return $this->image;
        }

        return asset('storage/'.ltrim($this->image, '/'));
    }
}
