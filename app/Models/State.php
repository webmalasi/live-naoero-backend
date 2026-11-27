<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    use HasFactory;

    protected $table = 'states';

    protected $fillable = [
        'country_id',
        'name',
        'state_code',
        'type',
        'latitude',
        'longitude',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /**
     * Country this state belongs to.
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Cities belonging to this state.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Active cities belonging to this state.
     */
    public function activeCities(): HasMany
    {
        return $this->hasMany(City::class)->where('is_active', true)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope for active states.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered states.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope to filter by country.
     */
    public function scopeForCountry($query, int $countryId)
    {
        return $query->where('country_id', $countryId);
    }
}
