<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'badge_text',
        'message',
        'link',
        'type',
        'is_active',
        'duration_hours',
        'starts_at',
        'expires_at',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration_hours' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'priority' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Notice $notice) {
            // If duration in hours is specified but expires_at is not explicitly provided, calculate it
            if ($notice->duration_hours && $notice->duration_hours > 0) {
                $baseTime = $notice->starts_at ? Carbon::parse($notice->starts_at) : now();
                $notice->expires_at = $baseTime->copy()->addHours($notice->duration_hours);
            }
        });
    }

    /**
     * Scope to filter only currently active & unexpired notices.
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Scope for display order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderByDesc('is_active')->latest();
    }

    /**
     * Scope for expired notices.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    /**
     * Scope for scheduled future notices.
     */
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->whereNotNull('starts_at')->where('starts_at', '>', now());
    }

    /**
     * Check if the notice is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Check if the notice is scheduled for future.
     */
    public function isScheduled(): bool
    {
        return $this->starts_at !== null && $this->starts_at->isFuture();
    }

    /**
     * Check if the notice is active right now.
     */
    public function isActiveNow(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if ($this->isScheduled()) {
            return false;
        }

        return true;
    }

    /**
     * Get computed status string.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->isScheduled()) {
            return 'scheduled';
        }

        return 'active';
    }

    /**
     * Human-readable status label.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Active & Live',
            'expired' => 'Expired',
            'scheduled' => 'Scheduled',
            'inactive' => 'Deactivated / Off',
            default => 'Unknown',
        };
    }

    /**
     * Status badge Tailwind CSS classes.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            'active' => 'bg-emerald-100 text-emerald-800 border-emerald-300',
            'expired' => 'bg-rose-100 text-rose-800 border-rose-300',
            'scheduled' => 'bg-blue-100 text-blue-800 border-blue-300',
            'inactive' => 'bg-slate-100 text-slate-600 border-slate-300',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    /**
     * Remaining time formatted string.
     */
    public function getRemainingTimeTextAttribute(): string
    {
        if (! $this->is_active) {
            return 'Turned Off';
        }

        if ($this->isScheduled()) {
            return 'Starts ' . $this->starts_at->diffForHumans();
        }

        if ($this->isExpired()) {
            return 'Expired ' . $this->expires_at->diffForHumans();
        }

        if ($this->expires_at) {
            $diffHours = (int) now()->diffInHours($this->expires_at, false);
            $diffMinutes = (int) now()->diffInMinutes($this->expires_at, false) % 60;

            if ($diffHours > 24) {
                $days = round($diffHours / 24, 1);
                return "{$days} days remaining ({$this->expires_at->format('M d, H:i')})";
            }

            if ($diffHours > 0) {
                return "{$diffHours}h {$diffMinutes}m remaining";
            }

            $totalMins = (int) now()->diffInMinutes($this->expires_at, false);
            return "{$totalMins} mins remaining";
        }

        return 'Permanent / Always Active';
    }

    /**
     * Alert type badge styling.
     */
    public function getTypeBadgeClassAttribute(): string
    {
        return match ($this->type) {
            'warning' => 'bg-amber-100 text-amber-900 border-amber-300',
            'alert', 'danger' => 'bg-rose-100 text-rose-900 border-rose-300',
            'success' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
            default => 'bg-blue-100 text-blue-900 border-blue-300',
        };
    }
}
