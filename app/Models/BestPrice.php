<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BestPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_rule_id',
        'origin',
        'destination',
        'departure_date',
        'return_date',
        'nights',
        'best_price_per_person',
        'best_price_total',
        'currency',
        'source',
        'flight_price_id',
        'first_seen_at',
        'last_seen_at',
        'times_found',
        'is_still_valid',
        'valid_until',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
        'best_price_per_person' => 'decimal:2',
        'best_price_total' => 'decimal:2',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'valid_until' => 'datetime',
        'is_still_valid' => 'boolean',
    ];

    public function searchRule(): BelongsTo
    {
        return $this->belongsTo(SearchRule::class);
    }

    public function flightPrice(): BelongsTo
    {
        return $this->belongsTo(FlightPrice::class);
    }

    public function scopeValid($query)
    {
        return $query->where('is_still_valid', true)
            ->where(function ($q) {
                $q->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    public function scopeByPrice($query, string $order = 'asc')
    {
        return $query->orderBy('best_price_total', $order);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('last_seen_at', 'desc');
    }

    public function getRouteAttribute(): string
    {
        return "{$this->origin} → {$this->destination}";
    }

    public function getDateRangeAttribute(): string
    {
        return "{$this->departure_date->format('d/m/Y')} → {$this->return_date->format('d/m/Y')}";
    }

    public function getPricePerPersonFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->best_price_per_person, 2, ',', '.');
    }

    public function getPriceTotalFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->best_price_total, 2, ',', '.');
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

    public function isValid(): bool
    {
        return $this->is_still_valid
            && (!$this->valid_until || $this->valid_until->isFuture());
    }

    public function incrementTimesFound(): void
    {
        $this->increment('times_found');
        $this->update(['last_seen_at' => now()]);
    }

    public function markAsInvalid(): void
    {
        $this->update([
            'is_still_valid' => false,
            'valid_until' => now(),
        ]);
    }
}