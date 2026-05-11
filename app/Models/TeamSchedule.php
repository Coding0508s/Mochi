<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamSchedule extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'is_all_day',
        'type',
        'visibility',
        'status',
        'location',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        $start = now()->parse($month.'-01')->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return $query->where(function (Builder $monthQuery) use ($start, $end): void {
            $monthQuery->whereBetween('starts_at', [$start, $end])
                ->orWhereBetween('ends_at', [$start, $end])
                ->orWhere(function (Builder $overlapQuery) use ($start, $end): void {
                    $overlapQuery->where('starts_at', '<=', $start)
                        ->where(function (Builder $endQuery) use ($end): void {
                            $endQuery->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', $end);
                        });
                });
        });
    }
}
