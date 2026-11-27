<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One block of a content page. The type says how it is shown (see
 * App\Services\Content\ContentPages::TYPES); the key stays the same when the
 * text changes, so the website and the app know which block is which.
 */
class ContentPageSection extends Model
{
    protected $fillable = [
        'content_page_id',
        'position',
        'key',
        'type',
        'heading',
        'subheading',
        'body',
        'image',
        'image_alt',
        'items',
        'columns',
        'button_label',
        'button_url',
        'note',
        'feed',
        'show_on',
        'is_visible',
    ];

    protected $casts = [
        'items' => 'array',
        'columns' => 'array',
        'is_visible' => 'boolean',
        'position' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'content_page_id');
    }
}
