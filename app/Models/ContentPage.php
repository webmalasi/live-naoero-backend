<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A website and app page edited in the admin panel (Charter, Freight,
 * Baggage …): its title, search-engine text and an ordered list of
 * sections. Served by `GET /api/pages/{slug}`.
 */
class ContentPage extends Model
{
    public const GROUPS = [
        'pages' => 'Pages',
        'travel-info' => 'Travel Info',
        'news' => 'News Hub',
    ];

    protected $fillable = [
        'slug',
        'title',
        'group',
        'path',
        'summary',
        'seo_title',
        'seo_description',
        'banner_key',
        'is_published',
        'sort_order',
        'version',
        'updated_by',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
        'version' => 'integer',
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(ContentPageSection::class)->orderBy('position')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentPageRevision::class)->orderByDesc('version');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('group')->orderBy('sort_order')->orderBy('title');
    }
}
