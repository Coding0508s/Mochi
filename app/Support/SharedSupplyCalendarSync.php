<?php

namespace App\Support;

use App\Models\SharedSupply;
use App\Models\TeamSchedule;
use Illuminate\Support\Facades\Schema;

class SharedSupplyCalendarSync
{
    private ?bool $isSourceColumnsReady = null;

    public function sync(SharedSupply $sharedSupply): void
    {
        if (! $this->canSync() || $sharedSupply->id === null || $sharedSupply->user_id === null) {
            return;
        }

        $payload = [
            'user_id' => (int) $sharedSupply->user_id,
            'title' => (string) $sharedSupply->title,
            'description' => (string) ($sharedSupply->purpose ?? ''),
            'starts_at' => $sharedSupply->starts_at,
            'ends_at' => $sharedSupply->ends_at,
            'is_all_day' => false,
            'type' => 'etc',
            'visibility' => 'team',
            'status' => 'planned',
            'location' => (string) ($sharedSupply->item?->name ?? ''),
            'updated_by' => $sharedSupply->updated_by ?? $sharedSupply->user_id,
        ];

        TeamSchedule::query()->updateOrCreate(
            [
                'source_type' => 'shared_supply',
                'source_id' => (int) $sharedSupply->id,
            ],
            $payload + [
                'created_by' => $sharedSupply->created_by ?? $sharedSupply->user_id,
            ]
        );
    }

    public function delete(SharedSupply $sharedSupply): void
    {
        if (! $this->canSync() || $sharedSupply->id === null) {
            return;
        }

        TeamSchedule::query()
            ->where('source_type', 'shared_supply')
            ->where('source_id', (int) $sharedSupply->id)
            ->delete();
    }

    private function canSync(): bool
    {
        if ($this->isSourceColumnsReady !== null) {
            return $this->isSourceColumnsReady;
        }

        $this->isSourceColumnsReady = Schema::hasColumn('team_schedules', 'source_type')
            && Schema::hasColumn('team_schedules', 'source_id');

        return $this->isSourceColumnsReady;
    }
}
