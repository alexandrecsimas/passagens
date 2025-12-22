<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SearchRule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'departure_date_min',
        'departure_date_max',
        'return_date_min',
        'return_date_max',
        'min_nights',
        'max_nights',
        'origins',
        'destinations',
        'passengers',
        'cabin_class',
        'max_connections',
        'baggage_included',
        'is_active',
        'priority',
        'user_id',
    ];

    protected $casts = [
        'departure_date_min' => 'date',
        'departure_date_max' => 'date',
        'return_date_min' => 'date',
        'return_date_max' => 'date',
        'origins' => 'array',
        'destinations' => 'array',
        'is_active' => 'boolean',
        'baggage_included' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
        'passengers' => 9,
        'min_nights' => 13,
        'max_nights' => 16,
        'cabin_class' => 'economy',
        'max_connections' => 1,
        'baggage_included' => true,
        'priority' => 0,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flightSearches(): HasMany
    {
        return $this->hasMany(FlightSearch::class);
    }

    public function bestPrices(): HasMany
    {
        return $this->hasMany(BestPrice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    public function getOriginCodesAttribute(): array
    {
        return $this->origins ?? [];
    }

    public function getDestinationCodesAttribute(): array
    {
        return $this->destinations ?? [];
    }

    public function hasOrigin(string $origin): bool
    {
        return in_array($origin, $this->origin_codes);
    }

    public function hasDestination(string $destination): bool
    {
        return in_array($destination, $this->destination_codes);
    }

    public function isValidDateRange(\DateTime $departure, \DateTime $return): bool
    {
        $nights = $departure->diff($return)->days;

        return $departure >= $this->departure_date_min
            && $departure <= $this->departure_date_max
            && $return >= $this->return_date_min
            && $return <= $this->return_date_max
            && $nights >= $this->min_nights
            && $nights <= $this->max_nights;
    }
}