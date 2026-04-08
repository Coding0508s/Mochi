<?php

namespace App\Models;

use App\Enums\LeadStage;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'institution_name',
        'status',
        'stage',
        'institution_type_id',
        'region_id',
        'lead_source_id',
        'owner_user_id',
        'registered_by_user_id',
        'lost_reason_id',
        'hold_reason_id',
        'interest_level',
        'priority_level',
        'lead_score',
        'expected_start_date',
        'expected_revenue',
        'last_contacted_at',
        'next_action_date',
        'next_action_type',
        'next_action_note',
        'converted_institution_id',
        'converted_at',
        'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'stage' => LeadStage::class,
            'interest_level' => 'integer',
            'priority_level' => 'integer',
            'lead_score' => 'integer',
            'expected_start_date' => 'date',
            'expected_revenue' => 'decimal:2',
            'last_contacted_at' => 'datetime',
            'next_action_date' => 'date',
            'converted_at' => 'datetime',
        ];
    }

    public function institutionType(): BelongsTo
    {
        return $this->belongsTo(InstitutionType::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function lostReason(): BelongsTo
    {
        return $this->belongsTo(LeadLostReason::class, 'lost_reason_id');
    }

    public function holdReason(): BelongsTo
    {
        return $this->belongsTo(LeadHoldReason::class, 'hold_reason_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(LeadContact::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->orderByDesc('occurred_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(LeadTask::class);
    }

    public function stageHistories(): HasMany
    {
        return $this->hasMany(LeadStageHistory::class)->orderByDesc('changed_at');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->visibleOwnerUserIdsForLeadScope();

        return $query->whereIn('owner_user_id', $ids);
    }

    public function scopeSearchInstitution(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $t = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where('institution_name', 'like', $t);
    }
}
