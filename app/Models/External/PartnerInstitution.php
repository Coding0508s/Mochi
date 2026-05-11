<?php

namespace App\Models\External;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PartnerInstitution extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection((string) config('services.partner_institutions.connection', 'partner'));
        $this->setTable((string) config('services.partner_institutions.table', 'institutions'));
        $this->setKeyName((string) config('services.partner_institutions.primary_key', 'id'));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePendingForInstitutionSync(Builder $query): Builder
    {
        $statusColumn = $this->statusColumn();
        if ($statusColumn !== null) {
            return $query->where($statusColumn, (string) config('services.partner_institutions.pending_status', 'pending'));
        }

        $changedAtColumn = $this->changedAtColumn();
        $lastChangedAt = cache()->get((string) config('services.partner_institutions.state_cache_key', 'partner_institution_sync:last_changed_at'));

        if ($changedAtColumn !== null && is_string($lastChangedAt) && $lastChangedAt !== '') {
            $query->where($changedAtColumn, '>', $lastChangedAt);
        }

        return $query;
    }

    public function syncSk(): ?string
    {
        return $this->stringFromConfiguredColumn('sk');
    }

    public function syncReplacesSk(): ?string
    {
        return $this->stringFromConfiguredColumn('replaces_sk');
    }

    /**
     * @return array<string, mixed>
     */
    public function toInstitutionPatch(): array
    {
        $patch = [];

        foreach ($this->syncColumns() as $apiKey => $column) {
            if ($apiKey === 'sk' || $apiKey === 'replaces_sk' || $column === null || $column === '') {
                continue;
            }

            if (! array_key_exists($column, $this->attributes)) {
                continue;
            }

            $patch[$apiKey] = $this->attributes[$column];
        }

        return $patch;
    }

    public function markInstitutionSyncApplied(): void
    {
        $this->markInstitutionSyncStatus((string) config('services.partner_institutions.applied_status', 'applied'));
    }

    public function markInstitutionSyncFailed(): void
    {
        $this->markInstitutionSyncStatus((string) config('services.partner_institutions.failed_status', 'failed'));
    }

    private function markInstitutionSyncStatus(string $status): void
    {
        if (! (bool) config('services.partner_institutions.mark_remote_rows', false)) {
            return;
        }

        $statusColumn = $this->statusColumn();
        if ($statusColumn === null) {
            return;
        }

        $this->newQuery()
            ->whereKey($this->getKey())
            ->update([$statusColumn => $status]);
    }

    public function syncChangedAt(): ?string
    {
        $changedAtColumn = $this->changedAtColumn();
        if ($changedAtColumn === null || ! array_key_exists($changedAtColumn, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$changedAtColumn];
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function stringFromConfiguredColumn(string $key): ?string
    {
        $column = $this->syncColumns()[$key] ?? null;
        if (! is_string($column) || $column === '' || ! array_key_exists($column, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$column];
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, string|null>
     */
    private function syncColumns(): array
    {
        $columns = config('services.partner_institutions.columns', []);

        return is_array($columns) ? $columns : [];
    }

    private function statusColumn(): ?string
    {
        $column = config('services.partner_institutions.status_column');

        return is_string($column) && $column !== '' ? $column : null;
    }

    private function changedAtColumn(): ?string
    {
        $column = config('services.partner_institutions.changed_at_column');

        return is_string($column) && $column !== '' ? $column : null;
    }
}
