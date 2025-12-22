<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlightPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'flight_search_id',
        'source',
        'origin',
        'destination',
        'return_origin',
        'departure_date',
        'return_date',
        'nights',
        'price_per_person',
        'passengers',
        'price_total',
        'currency',
        'airline',
        'connections',
        'baggage_included',
        'flight_url',
        'additional_data',
        'expires_at',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'price_per_person' => 'decimal:2',
        'price_total' => 'decimal:2',
        'baggage_included' => 'boolean',
        'additional_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function flightSearch(): BelongsTo
    {
        return $this->belongsTo(FlightSearch::class);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeByRoute($query, string $origin, string $destination)
    {
        return $query->where('origin', $origin)
            ->where('destination', $destination);
    }

    public function scopeByPrice($query, string $order = 'asc')
    {
        return $query->orderBy('price_total', $order);
    }

    public function scopeWithBaggage($query)
    {
        return $query->where('baggage_included', true);
    }

    public function scopeDirectFlights($query)
    {
        return $query->where('connections', 0);
    }

    public function scopeOneStop($query)
    {
        return $query->where('connections', '<=', 1);
    }

    public function getRouteAttribute(): string
    {
        return "{$this->origin} → {$this->destination}";
    }

    public function getDateRangeAttribute(): string
    {
        return "{$this->departure_date->format('d/m')} → {$this->return_date->format('d/m')}";
    }

    public function getPricePerPersonFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->price_per_person, 2, ',', '.');
    }

    public function getPriceTotalFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->price_total, 2, ',', '.');
    }

    public function getSourceLabelAttribute(): string
    {
        return match($this->source) {
            'skyscanner' => 'Skyscanner',
            'google_flights' => 'Google Flights',
            'other' => 'Outro',
            default => ucfirst($this->source),
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isCheaperThan(FlightPrice $other): bool
    {
        return $this->price_total < $other->price_total;
    }
}