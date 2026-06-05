<?php

namespace App\Support;

use App\Models\AssignmentChangeRequest;
use App\Models\ContractDocument;
use App\Models\SupportRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

final class InstitutionUnifiedTimelineBuilder
{
    public function __construct(
        private SupportAuthorTeamResolver $teamResolver,
        private TeacherSupportHistoryAggregator $teacherSupportHistoryAggregator,
    ) {}

    /**
     * @param  array{type?: string, range?: string, author?: string}  $filters
     * @return array{
     *     items: list<array<string, mixed>>,
     *     totals: array{
     *         all: int,
     *         support: int,
     *         support_coach: int,
     *         support_cs: int,
     *         assignment_change: int,
     *         contract_document: int
     *     }
     * }
     */
    public function build(string $skCode, array $filters = [], int $limit = 300): array
    {
        $normalizedSk = trim($skCode);
        if ($normalizedSk === '') {
            return [
                'items' => [],
                'totals' => $this->emptyTotals(),
            ];
        }

        $candidateSkCodes = SkCodeNormalizer::candidates($normalizedSk);
        $typeFilter = (string) ($filters['type'] ?? 'all');
        $rangeFilter = (string) ($filters['range'] ?? '6m');
        $authorFilter = trim((string) ($filters['author'] ?? ''));
        $startAt = $this->resolveRangeStart($rangeFilter);

        $items = collect();

        if (in_array($typeFilter, ['all', 'support', 'support_coach', 'support_cs'], true) && Schema::hasTable('S_SupportInfo_Account')) {
            $items = $items->merge($this->supportEvents(
                $candidateSkCodes,
                $startAt,
                $authorFilter,
                $limit,
                $typeFilter
            ));
        }

        if (in_array($typeFilter, ['all', 'support', 'support_coach'], true)) {
            $items = $items->merge($this->coachTeacherSupportEvents(
                $candidateSkCodes,
                $startAt,
                $authorFilter,
                $limit
            ));
        }

        if (in_array($typeFilter, ['all', 'assignment_change'], true) && Schema::hasTable('assignment_change_requests')) {
            $items = $items->merge($this->assignmentEvents(
                $candidateSkCodes,
                $startAt,
                $authorFilter,
                $limit
            ));
        }

        if (in_array($typeFilter, ['all', 'contract_document'], true) && Schema::hasTable('contract_documents')) {
            $items = $items->merge($this->contractEvents(
                $candidateSkCodes,
                $startAt,
                $authorFilter,
                $limit
            ));
        }

        $sorted = $items
            ->sortByDesc('occurred_at_ts')
            ->values();

        $totals = [
            'all' => $sorted->count(),
            'support' => $sorted->whereIn('event_type', ['support', 'support_coach', 'support_cs'])->count(),
            'support_coach' => $sorted->where('event_type', 'support_coach')->count(),
            'support_cs' => $sorted->where('event_type', 'support_cs')->count(),
            'assignment_change' => $sorted->where('event_type', 'assignment_change')->count(),
            'contract_document' => $sorted->where('event_type', 'contract_document')->count(),
        ];

        if ($limit > 0 && $sorted->count() > $limit) {
            $sorted = $sorted->take($limit)->values();
        }

        return [
            'items' => $sorted->all(),
            'totals' => $totals,
        ];
    }

    /**
     * @param  list<string>  $candidateSkCodes
     * @return Collection<int, array<string, mixed>>
     */
    private function supportEvents(array $candidateSkCodes, Carbon $startAt, string $authorFilter, int $limit, string $typeFilter): Collection
    {
        $query = SupportRecord::query()
            ->whereIn('SK_Code', $candidateSkCodes)
            ->whereNotNull('Support_Date')
            ->where('Support_Date', '>=', $startAt)
            ->orderByDesc('Support_Date')
            ->orderByDesc('ID');

        if ($authorFilter !== '') {
            $query->where('TR_Name', 'like', "%{$authorFilter}%");
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function (SupportRecord $record) use ($typeFilter): array {
            $occurredAt = $record->Support_Date;
            $status = $record->isCompleted()
                ? SupportRecord::STATUS_COMPLETED
                : SupportRecord::STATUS_IN_PROGRESS;
            $team = $this->teamResolver->resolve((string) ($record->TR_Name ?? ''));
            $eventType = match ($team) {
                SupportAuthorTeamResolver::TEAM_CS => 'support_cs',
                SupportAuthorTeamResolver::TEAM_COACH => 'support_coach',
                default => 'support',
            };
            $eventTypeLabel = match ($eventType) {
                'support_cs' => 'CS 기관 지원',
                'support_coach' => '코치 교사 지원',
                default => '지원 내역',
            };

            if ($typeFilter === 'support_cs' && $eventType !== 'support_cs') {
                return [];
            }

            if ($typeFilter === 'support_coach' && $eventType !== 'support_coach') {
                return [];
            }

            return [
                'event_type' => $eventType,
                'event_type_label' => $eventTypeLabel,
                'source_id' => (int) $record->ID,
                'title' => trim((string) ($record->Support_Type ?: '기관 지원')),
                'summary' => trim((string) ($record->Issue ?: $record->TO_Account ?: '-')),
                'actor_name' => trim((string) ($record->TR_Name ?: '-')),
                'status' => $status,
                'occurred_at' => $occurredAt?->format('Y-m-d H:i') ?? '-',
                'occurred_date' => $occurredAt?->format('Y-m-d') ?? '-',
                'occurred_time' => $this->formatMeetTime($record->Meet_Time),
                'occurred_at_ts' => $occurredAt?->getTimestamp() ?? 0,
            ];
        })->filter(fn (array $row): bool => $row !== [])->values();
    }

    /**
     * @param  list<string>  $candidateSkCodes
     * @return Collection<int, array<string, mixed>>
     */
    private function coachTeacherSupportEvents(array $candidateSkCodes, Carbon $startAt, string $authorFilter, int $limit): Collection
    {
        $records = collect($this->teacherSupportHistoryAggregator->forInstitution($candidateSkCodes, $limit));

        $filtered = $records->filter(function (array $record) use ($startAt, $authorFilter): bool {
            $coach = trim((string) ($record['coach'] ?? ''));
            if ($authorFilter !== '' && stripos($coach, $authorFilter) === false) {
                return false;
            }

            $timestamp = $this->parseTimelineTimestamp($record['date'] ?? null);

            return $timestamp >= $startAt->getTimestamp();
        });

        return $filtered->map(function (array $record): array {
            $timestamp = $this->parseTimelineTimestamp($record['date'] ?? null);
            $occurredAt = $timestamp > 0 ? Carbon::createFromTimestamp($timestamp) : null;
            $teacherName = trim((string) ($record['teacher'] ?? ''));
            $type = trim((string) ($record['type'] ?? '교사 지원'));
            $status = trim((string) ($record['status'] ?? '-'));
            $detailKey = trim((string) ($record['detail_key'] ?? ''));

            return [
                'event_type' => 'support_coach',
                'event_type_label' => '코치 교사 지원',
                'source_id' => (string) ($record['id'] ?? '-'),
                'title' => $type,
                'summary' => $teacherName !== '' ? "교사: {$teacherName}" : '-',
                'actor_name' => trim((string) ($record['coach'] ?? '-')),
                'status' => $status,
                'occurred_at' => $occurredAt?->format('Y-m-d H:i') ?? '-',
                'occurred_date' => $occurredAt?->format('Y-m-d') ?? '-',
                'occurred_time' => $occurredAt?->format('H:i') ?? '-',
                'occurred_at_ts' => $timestamp,
                'detail_key' => $detailKey !== '' ? $detailKey : null,
                'teacher_id' => isset($record['teacher_id']) ? (int) $record['teacher_id'] : null,
            ];
        })->values();
    }

    /**
     * @param  list<string>  $candidateSkCodes
     * @return Collection<int, array<string, mixed>>
     */
    private function assignmentEvents(array $candidateSkCodes, Carbon $startAt, string $authorFilter, int $limit): Collection
    {
        $query = AssignmentChangeRequest::query()
            ->whereIn('sk_code', $candidateSkCodes)
            ->where(function ($builder) use ($startAt): void {
                $builder->where('requested_at', '>=', $startAt)
                    ->orWhere('applied_at', '>=', $startAt);
            })
            ->orderByDesc('requested_at')
            ->orderByDesc('id');

        if ($authorFilter !== '') {
            $query->where('changed_by', 'like', "%{$authorFilter}%");
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function (AssignmentChangeRequest $record): array {
            $occurredAt = $record->applied_at ?? $record->requested_at;
            $changed = collect([
                'CO' => trim((string) ($record->co ?? '')),
                'TR' => trim((string) ($record->tr ?? '')),
                'CS' => trim((string) ($record->cs ?? '')),
            ])->filter();

            $title = $changed->isEmpty()
                ? '담당자 변경'
                : '담당자 변경 ('.implode(', ', $changed->keys()->all()).')';

            $summary = $changed->isEmpty()
                ? '변경 항목 정보 없음'
                : $changed->map(
                    fn (string $value, string $role): string => "{$role}: {$value}"
                )->implode(' · ');

            return [
                'event_type' => 'assignment_change',
                'event_type_label' => '담당자 변경',
                'source_id' => (int) $record->id,
                'title' => $title,
                'summary' => $summary,
                'actor_name' => trim((string) ($record->changed_by ?: '-')),
                'status' => trim((string) ($record->status ?: '-')),
                'occurred_at' => $occurredAt?->format('Y-m-d H:i') ?? '-',
                'occurred_date' => $occurredAt?->format('Y-m-d') ?? '-',
                'occurred_time' => $occurredAt?->format('H:i') ?? '-',
                'occurred_at_ts' => $occurredAt?->getTimestamp() ?? 0,
            ];
        });
    }

    /**
     * @param  list<string>  $candidateSkCodes
     * @return Collection<int, array<string, mixed>>
     */
    private function contractEvents(array $candidateSkCodes, Carbon $startAt, string $authorFilter, int $limit): Collection
    {
        $query = ContractDocument::query()
            ->whereIn('sk_code', $candidateSkCodes)
            ->where('document_date', '>=', $startAt->toDateString())
            ->orderByDesc('document_date')
            ->orderByDesc('id');

        if ($authorFilter !== '') {
            $query->where(function ($builder) use ($authorFilter): void {
                $builder->where('consultant', 'like', "%{$authorFilter}%")
                    ->orWhere('uploaded_by', 'like', "%{$authorFilter}%");
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        return $query->get()->map(function (ContractDocument $record): array {
            $date = $record->document_date;
            $time = trim((string) ($record->document_time ?? ''));

            $occurredAt = $date?->copy()->startOfDay();
            if ($occurredAt !== null && preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
                [$hour, $minute] = array_map('intval', explode(':', $time));
                $occurredAt->setTime($hour, $minute);
            }

            return [
                'event_type' => 'contract_document',
                'event_type_label' => '계약 문서',
                'source_id' => (int) $record->id,
                'title' => '계약 문서 등록',
                'summary' => trim((string) ($record->original_filename ?: '-')),
                'actor_name' => trim((string) ($record->consultant ?: $record->uploaded_by ?: '-')),
                'status' => '완료',
                'occurred_at' => $occurredAt?->format('Y-m-d H:i') ?? '-',
                'occurred_date' => $occurredAt?->format('Y-m-d') ?? '-',
                'occurred_time' => $time !== '' ? $time : '-',
                'occurred_at_ts' => $occurredAt?->getTimestamp() ?? 0,
            ];
        });
    }

    private function resolveRangeStart(string $rangeFilter): Carbon
    {
        return match ($rangeFilter) {
            '1m' => now()->subMonth(),
            '3m' => now()->subMonths(3),
            '12m' => now()->subMonths(12),
            'all' => now()->subYears(10),
            default => now()->subMonths(6),
        };
    }

    private function formatMeetTime(mixed $meetTime): string
    {
        if ($meetTime === null) {
            return '-';
        }

        if ($meetTime instanceof \DateTimeInterface) {
            return $meetTime->format('H:i');
        }

        $stringValue = trim((string) $meetTime);
        if ($stringValue === '') {
            return '-';
        }

        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '-';
    }

    /**
     * @return array{
     *     all: int,
     *     support: int,
     *     support_coach: int,
     *     support_cs: int,
     *     assignment_change: int,
     *     contract_document: int
     * }
     */
    private function emptyTotals(): array
    {
        return [
            'all' => 0,
            'support' => 0,
            'support_coach' => 0,
            'support_cs' => 0,
            'assignment_change' => 0,
            'contract_document' => 0,
        ];
    }

    private function parseTimelineTimestamp(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        try {
            return Carbon::parse((string) $value)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
