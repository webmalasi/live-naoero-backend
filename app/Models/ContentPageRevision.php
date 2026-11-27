<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A content page as it was before a save, to undo an edit. */
class ContentPageRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'content_page_id',
        'version',
        'snapshot',
        'user_id',
        'note',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'version' => 'integer',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class, 'content_page_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
