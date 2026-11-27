<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgencyResource extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category',
        'file_url',
        'external_url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->slug)) {
                $item->slug = Str::slug($item->title);
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->file_url) {
            if (str_starts_with($this->file_url, 'http://') || str_starts_with($this->file_url, 'https://')) {
                return $this->file_url;
            }

            return url($this->file_url);
        }

        return $this->external_url;
    }

    /**
     * Does this entry really hold a PDF? A file of ours must exist on disk
     * (a path saved by hand can point at nothing); a link elsewhere counts
     * when it names a .pdf.
     */
    public function getHasPdfAttribute(): bool
    {
        foreach ([$this->file_url, $this->external_url] as $link) {
            $link = trim((string) $link);
            if ($link === '') {
                continue;
            }
            $path = (string) parse_url($link, PHP_URL_PATH);
            if (! str_ends_with(strtolower($path), '.pdf')) {
                continue;
            }
            if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
                return true;
            }
            if (str_starts_with($path, '/storage/')) {
                if (Storage::disk('public')->exists(substr($path, strlen('/storage/')))) {
                    return true;
                }

                continue;
            }
            if (is_file(public_path(ltrim($path, '/')))) {
                return true;
            }
        }

        return false;
    }
}
