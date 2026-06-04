<?php

namespace App\Console\Commands;

use App\Models\SharedSupply;
use App\Models\SharedSupplyItem;
use App\Models\SharedSupplyLabel;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

#[Signature('shared-supplies:backfill-label-ids {--apply : 실제 업데이트를 수행합니다 (기본은 dry-run)} {--chunk=500 : 청크 크기}')]
#[Description('shared_supplies.shared_supply_label_id 누락/끊김을 레거시 label/아이템명 규칙으로 안전하게 백필합니다.')]
class BackfillSharedSupplyLabelIds extends Command
{
    public function handle(): int
    {
        if (! Schema::hasTable('shared_supplies')
            || ! Schema::hasTable('shared_supply_labels')
            || ! Schema::hasTable('shared_supply_items')) {
            $this->error('필수 테이블(shared_supplies, shared_supply_labels, shared_supply_items)이 없습니다.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('shared_supplies', 'shared_supply_label_id')) {
            $this->error('shared_supplies.shared_supply_label_id 컬럼이 없어 백필을 수행할 수 없습니다.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $chunkSize = max(1, (int) $this->option('chunk'));

        $labelIdByCode = SharedSupplyLabel::query()
            ->whereIn('code', ['01', '02'])
            ->pluck('id', 'code');

        $vehicleLabelId = (int) ($labelIdByCode['01'] ?? 0);
        $meetingLabelId = (int) ($labelIdByCode['02'] ?? 0);
        if ($vehicleLabelId <= 0 || $meetingLabelId <= 0) {
            $this->error('라벨 마스터 코드(01 차량배차, 02 회의실)가 누락되어 중단합니다.');

            return self::FAILURE;
        }

        $validLabelIds = SharedSupplyLabel::query()->pluck('id')->values()->all();
        $itemNameById = SharedSupplyItem::query()->pluck('name', 'id');
        $hasLegacyLabelColumn = Schema::hasColumn('shared_supplies', 'label');

        $stats = [
            'target' => 0,
            'resolved' => 0,
            'unresolved' => 0,
            'would_update' => 0,
            'updated' => 0,
        ];

        SharedSupply::query()
            ->select(['id', 'shared_supply_item_id', 'shared_supply_label_id', 'label'])
            ->where(function ($query) use ($validLabelIds): void {
                $query->whereNull('shared_supply_label_id');
                if ($validLabelIds !== []) {
                    $query->orWhereNotIn('shared_supply_label_id', $validLabelIds);
                }
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $rows) use (
                &$stats,
                $apply,
                $itemNameById,
                $hasLegacyLabelColumn,
                $vehicleLabelId,
                $meetingLabelId
            ): void {
                /** @var SharedSupply $row */
                foreach ($rows as $row) {
                    $stats['target']++;

                    $legacyLabel = $hasLegacyLabelColumn ? (string) ($row->label ?? '') : '';
                    $itemName = (string) ($itemNameById[$row->shared_supply_item_id] ?? '');
                    $resolvedCode = $this->resolveLabelCode($legacyLabel, $itemName);
                    if ($resolvedCode === null) {
                        $stats['unresolved']++;

                        continue;
                    }

                    $stats['resolved']++;
                    $resolvedLabelId = $resolvedCode === '02' ? $meetingLabelId : $vehicleLabelId;

                    if ((int) $row->shared_supply_label_id === $resolvedLabelId) {
                        continue;
                    }

                    $stats['would_update']++;
                    if (! $apply) {
                        continue;
                    }

                    SharedSupply::query()
                        ->whereKey($row->id)
                        ->update(['shared_supply_label_id' => $resolvedLabelId]);
                    $stats['updated']++;
                }
            }, column: 'id');

        $this->table(
            ['항목', '건수'],
            [
                ['대상 행(누락/끊김)', $stats['target']],
                ['규칙으로 라벨 결정 가능', $stats['resolved']],
                ['규칙으로 미결정', $stats['unresolved']],
                ['변경 대상', $stats['would_update']],
                ['실제 변경', $stats['updated']],
            ]
        );

        if (! $apply) {
            $this->comment('dry-run 완료: 실제 변경 없이 대상만 계산했습니다. (--apply로 반영)');
        }

        return self::SUCCESS;
    }

    private function resolveLabelCode(string $legacyLabel, string $itemName): ?string
    {
        $legacy = mb_strtolower(trim($legacyLabel));
        if ($legacy !== '') {
            if (str_contains($legacy, '회의실')) {
                return '02';
            }
            if (str_contains($legacy, '차량') || str_contains($legacy, '배차')) {
                return '01';
            }
        }

        $normalizedItem = $this->normalize($itemName);
        if ($normalizedItem === '') {
            return null;
        }

        $meetingItems = [
            'graperoom',
            'jennyroom',
            'jonnyroom',
            'grapeseedservices',
        ];
        if (in_array($normalizedItem, $meetingItems, true) || str_contains($normalizedItem, 'room')) {
            return '02';
        }

        return '01';
    }

    private function normalize(string $value): string
    {
        $lower = mb_strtolower(trim($value));

        return preg_replace('/[\s\p{P}\p{S}]+/u', '', $lower) ?? $lower;
    }
}
