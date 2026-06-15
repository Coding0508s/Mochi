<?php

namespace App\Models;

use App\Support\VehicleArrivalLocation;
use App\Support\VehicleUsageLogRemark;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class SharedSupply extends Model
{
    protected $fillable = [
        'user_id',
        'starts_at',
        'ends_at',
        'shared_supply_item_id',
        'shared_supply_label_id',
        'schedule_category_code',
        'item_name',
        'label',
        'title',
        'purpose',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SharedSupplyItem::class, 'shared_supply_item_id');
    }

    public function label(): BelongsTo
    {
        return $this->belongsTo(SharedSupplyLabel::class, 'shared_supply_label_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vehicleUsageLog(): HasOne
    {
        return $this->hasOne(VehicleUsageLog::class, 'shared_supply_id');
    }

    public function scopeForMonth(Builder $query, string $month): Builder
    {
        $start = now()->parse($month.'-01')->startOfMonth()->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return $query->whereBetween('starts_at', [$start, $end]);
    }

    public function isVehicleDispatchTitle(): bool
    {
        return str_contains((string) $this->title, '차량배차');
    }

    public function isMeetingRoomTitle(): bool
    {
        return str_contains((string) $this->title, '회의실') || str_contains((string) $this->title, '[팀회의]');
    }

    public function isLeaveTitle(): bool
    {
        return str_starts_with((string) $this->title, '[휴가]');
    }

    public function isBusinessTripTitle(): bool
    {
        if ($this->isVehicleDispatchTitle()) {
            return false;
        }

        $title = (string) $this->title;

        return str_starts_with($title, '[출장]') || str_starts_with($title, '[해외출장]');
    }

    public function isReservationTitle(): bool
    {
        return str_contains((string) $this->title, '신청 및 예약');
    }

    public function reservationCategoryBadgeLabel(): ?string
    {
        if ($this->isVehicleDispatchTitle()) {
            return '차량 배차';
        }

        if ($this->isMeetingRoomTitle()) {
            return '회의실';
        }

        if ($this->isLeaveTitle()) {
            return '연차';
        }

        if ($this->isBusinessTripTitle()) {
            return '출장';
        }

        return null;
    }

    /**
     * @return 'complete'|'pending_post_use'|null
     */
    public function vehicleRowStatus(): ?string
    {
        if (! $this->isVehicleDispatchTitle()) {
            return null;
        }

        $log = $this->vehicleUsageLog;
        if ($log === null || ! $this->isVehicleUsageLogComplete($log)) {
            return 'pending_post_use';
        }

        return 'complete';
    }

    public function vehicleRowPrimaryRemark(): string
    {
        $log = $this->vehicleUsageLog;
        $arrivalLocation = VehicleArrivalLocation::forDisplay($log?->arrival_location);
        $reason = trim((string) ($this->purpose ?? ''));

        if ($reason === '' && $log !== null) {
            $reason = VehicleUsageLogRemark::forDisplay($log->remarks);
        }

        if ($reason === '' && $log !== null) {
            $reason = $this->vehicleInstitutionDisplayName($log);
        }

        return $arrivalLocation !== '' ? $arrivalLocation : $reason;
    }

    public function vehicleRowSecondaryRemark(): string
    {
        $log = $this->vehicleUsageLog;
        $status = $this->vehicleRowStatus();
        $summaryParts = $this->vehicleRowPostUseSummaryParts($log);

        if ($status === 'complete' && $summaryParts !== []) {
            return implode(' · ', $summaryParts);
        }

        $usagePurpose = trim((string) ($log?->usage_purpose_name ?? ''));
        if ($usagePurpose === '') {
            $usagePurpose = $this->usagePurposeFromPurposeText();
        }

        if ($status === 'pending_post_use') {
            $segments = [];

            if ($summaryParts !== []) {
                $segments[] = implode(' · ', $summaryParts);
            } elseif ($usagePurpose !== '') {
                $segments[] = $usagePurpose;
            }

            $hint = $this->pendingPostUseHint($log);
            if ($hint !== '') {
                $segments[] = $hint;
            }

            return implode(' · ', $segments);
        }

        return $usagePurpose;
    }

    private function isVehicleUsageLogComplete(VehicleUsageLog $log): bool
    {
        if ($log->odometer_after === null) {
            return false;
        }

        if (! Schema::hasColumn('vehicle_usage_logs', 'arrival_location')) {
            return true;
        }

        return $this->hasRecordedDestination($log);
    }

    /**
     * @return array<int, string>
     */
    private function vehicleRowPostUseSummaryParts(?VehicleUsageLog $log): array
    {
        if ($log === null) {
            return [];
        }

        $parts = [];

        if ($log->distance !== null && $log->distance > 0) {
            $parts[] = number_format((int) $log->distance).'km';
        }

        if ($log->odometer_after !== null) {
            $parts[] = '주행후 '.number_format((int) $log->odometer_after);
        }

        return $parts;
    }

    private function pendingPostUseHint(?VehicleUsageLog $log): string
    {
        $needsOdometerAfter = $log === null || $log->odometer_after === null;
        $needsArrival = ! $this->hasRecordedDestination($log);

        if ($needsOdometerAfter && $needsArrival) {
            return '입력 대기: 주행후/도착';
        }

        if ($needsOdometerAfter) {
            return '입력 대기: 주행후';
        }

        if ($needsArrival) {
            return '입력 대기: 도착';
        }

        return '';
    }

    private function hasRecordedDestination(?VehicleUsageLog $log): bool
    {
        if ($log !== null && trim((string) ($log->arrival_location ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($this->purpose ?? '')) !== '') {
            return true;
        }

        if ($log !== null) {
            return VehicleUsageLogRemark::forDisplay($log->remarks) !== '';
        }

        return false;
    }

    private function usagePurposeFromPurposeText(): string
    {
        $purpose = trim((string) ($this->purpose ?? ''));
        if ($purpose === '') {
            return '';
        }

        $segments = preg_split('/\s*\/\s*/u', $purpose) ?: [];
        $firstSegment = trim((string) ($segments[0] ?? ''));

        return $firstSegment;
    }

    private function vehicleInstitutionDisplayName(?VehicleUsageLog $log): string
    {
        if ($log === null || ! Schema::hasColumn('vehicle_usage_logs', 'institution_sk_code')) {
            return '';
        }

        $skCode = trim((string) ($log->institution_sk_code ?? ''));
        if ($skCode === '') {
            return '';
        }

        if (! Schema::hasTable('S_AccountName')) {
            return $skCode;
        }

        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();

        if ($institution !== null) {
            return $institution->resolvedAccountName();
        }

        return $skCode;
    }
}
