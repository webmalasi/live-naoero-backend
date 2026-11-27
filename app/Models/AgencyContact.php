<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgencyContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'country',
        'flag',
        'name',
        'address',
        'phones',
        'emails',
        'website',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'phones' => 'array',
        'emails' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
