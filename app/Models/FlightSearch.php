<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlightSearch extends Model
{
    use HasFactory;

    protected $fillable = [
        'search_rule_id',
        'status',
        'started_at',
        'completed_at',
        'duration_seconds',
        'sources_used',
        'combinations_tested',
        'results_found',
        'errors_count',
        'lowest_price_found',
        'best_combination',
        'error_message',
        'error_details',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'sources_used' => 'array',
        'best_combination' => 'array',
        'error_details' => 'array',
        'lowest_price_found' => 'decimal:2',
    ];

    public function searchRule(): BelongsTo
    {
        return $this->belongsTo(SearchRule::class);
    }

    public function flightPrices(): HasMany
    {
        return $this->hasMany(FlightPrice::class);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function markAsRunning(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $completedAt = now();
        $startedAt = $this->started_at;

        $this->update([
            'status' => 'completed',
            'completed_at' => $completedAt,
            'duration_seconds' => $startedAt ? $completedAt->diffInSeconds($startedAt) : 0,
        ]);
    }

    public function markAsFailed(string $message, ?array $details = null): void
    {
        $completedAt = now();
        $startedAt = $this->started_at;

        $this->update([
            'status' => 'failed',
            'completed_at' => $completedAt,
            'duration_seconds' => $startedAt ? $completedAt->diffInSeconds($startedAt) : 0,
            'error_message' => $message,
            'error_details' => $details,
        ]);
    }
}