<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FlightSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'period',
        'route',
        'file_path',
        'file_url',
        'effective_from',
        'effective_until',
        'notes',
        'alert_notice',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'download_url',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->latest();
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->file_url) {
            return $this->file_url;
        }

        if (! empty($this->file_path)) {
            if (Str::startsWith($this->file_path, ['http://', 'https://', '//'])) {
                return $this->file_path;
            }

            return asset('storage/'.ltrim($this->file_path, '/'));
        }

        return null;
    }
}
