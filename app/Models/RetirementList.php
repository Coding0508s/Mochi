<?php

namespace App\Models;

use App\Casts\LegacyDateTimeCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $ID
 * @property int|null $TearcherID Legacy FK to Teachers.ID (column name typo in DB)
 * @property string|null $Name
 * @property string|null $SK_Code
 * @property string|null $Account_Name
 * @property string|null $TR_Name
 * @property Carbon|null $RetirementDate
 * @property bool|null $RecommendYN
 * @property string|null $RecommendDescription
 * @property string|null $Description
 * @property string|null $Status
 */
class RetirementList extends Model
{
    protected $table = 'S_RetirementList';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'TearcherID',
        'Name',
        'SK_Code',
        'Account_Name',
        'TR_Name',
        'RetirementDate',
        'RecommendYN',
        'RecommendDescription',
        'Description',
        'Status',
        'FGC_Creator',
        'FGC_CreateDate',
        'FGC_LastModifier',
        'FGC_LastModifyDate',
    ];

    protected function casts(): array
    {
        return [
            'RetirementDate' => LegacyDateTimeCast::class,
            'FGC_CreateDate' => 'datetime',
            'FGC_LastModifyDate' => 'datetime',
            'RecommendYN' => 'boolean',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'TearcherID', 'ID');
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SK_Code', 'SKcode');
    }

    public function displayAccountName(): string
    {
        $fromInstitution = trim($this->institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        return trim((string) ($this->Account_Name ?? ''));
    }

    public function displayPosition(): ?string
    {
        $position = trim((string) ($this->teacher?->Position ?? ''));

        return $position !== '' ? $position : null;
    }

    /**
     * @param  Builder<RetirementList>  $query
     */
    public function scopeForYear(Builder $query, int $year): void
    {
        $column = config('coach_retired_teachers.columns.retirement_date', 'RetirementDate');
        $query->whereYear($column, $year);
    }
}
