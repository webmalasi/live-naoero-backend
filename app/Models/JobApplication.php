<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'career_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'cover_letter',
        'resume_path',
        'status',
        'admin_notes',
    ];

    protected $appends = [
        'full_name',
        'resume_url',
    ];

    public function career(): BelongsTo
    {
        return $this->belongsTo(Career::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getResumeUrlAttribute(): ?string
    {
        if (empty($this->resume_path)) {
            return null;
        }

        if (Str::startsWith($this->resume_path, ['http://', 'https://', '//'])) {
            return $this->resume_path;
        }

        return asset('storage/'.ltrim($this->resume_path, '/'));
    }
}
