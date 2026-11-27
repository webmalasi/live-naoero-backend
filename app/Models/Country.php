<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'phone_code',
        'currency',
        'currency_symbol',
        'currency_name',
        'capital',
        'region',
        'subregion',
        'flag_emoji',
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
     * States belonging to this country.
     */
    public function states(): HasMany
    {
        return $this->hasMany(State::class)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Active states belonging to this country.
     */
    public function activeStates(): HasMany
    {
        return $this->hasMany(State::class)->where('is_active', true)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Cities belonging to this country.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Active cities belonging to this country.
     */
    public function activeCities(): HasMany
    {
        return $this->hasMany(City::class)->where('is_active', true)->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope for active countries.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered countries.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }

    /**
     * Scope filter by region.
     */
    public function scopeByRegion($query, string $region)
    {
        return $query->where('region', $region);
    }

    /**
     * Formatted label with emoji.
     */
    public function getDisplayNameAttribute(): string
    {
        return trim(($this->flag_emoji ? $this->flag_emoji . ' ' : '') . $this->name);
    }
}
