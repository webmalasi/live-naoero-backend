<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'type', // 'blog' or 'news'
        'category',
        'excerpt',
        'content',
        'image',
        'author',
        'published_at',
        'is_featured',
        'is_published',
        'views_count',
    ];

    protected $attributes = [
        'type' => 'blog',
        'is_published' => true,
        'is_featured' => false,
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'is_published' => 'boolean',
        'views_count' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'formatted_date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            } else {
                $post->slug = Str::slug($post->slug);
            }

            if (empty($post->type)) {
                $post->type = 'blog';
            }

            if (empty($post->published_at) && $post->is_published) {
                $post->published_at = now();
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeBlog(Builder $query): Builder
    {
        return $query->where('type', 'blog');
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->where('type', 'news');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderBy('published_at', 'desc')->orderBy('id', 'desc');
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

    public function getFormattedDateAttribute(): string
    {
        return $this->published_at ? $this->published_at->format('M d, Y') : '';
    }
}
