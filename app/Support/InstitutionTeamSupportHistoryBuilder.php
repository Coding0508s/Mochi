<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\SupportRecord;
use Illuminate\Support\Facades\Schema;

/**
 * 기관 상세 모달용 — SK(및 alias) 기준 기관·교사 지원 이력을 팀별 bucket 으로 집계.
 */
final class InstitutionTeamSupportHistoryBuilder
{
    public function __construct(
        private SupportAuthorTeamResolver $teamResolver,
        private TeacherSupportHistoryAggregator $teacherSupportHistoryAggregator,
    ) {}

    /**
     * @return array{
     *     co: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     coach: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     cs: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     unknown: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     totals: array{institution: int, teacher: int},
     * }
     */
    public function build(Institution $institution, int $yearWindow = 10, int $limitPerSource = 300): array
    {
        $skCode = trim((string) ($institution->SKcode ?? ''));
        $candidateSkCodes = $skCode !== '' ? SkCodeNormalizer::candidates($skCode) : [];

        $buckets = $this->emptyBuckets();

        if ($candidateSkCodes === []) {
            return $buckets;
        }

        $startYear = now()->year - ($yearWindow - 1);

        if (Schema::hasTable('S_SupportInfo_Account')) {
            $institutionRecords = SupportRecord::query()
                ->whereIn('SK_Code', $candidateSkCodes)
                ->where(function ($query) use ($startYear): void {
                    $query->where('Year', '>=', $startYear)
                        ->orWhereYear('Support_Date', '>=', $startYear);
                })
                ->orderByDesc('Support_Date')
                ->orderByDesc('ID')
                ->limit($limitPerSource)
                ->get();

            foreach ($institutionRecords as $record) {
                $team = $this->teamResolver->resolve((string) ($record->TR_Name ?? ''));
                $row = [
                    'id' => $record->ID,
                    'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
                    'support_time' => $this->formatSupportMeetTime($record->Meet_Time),
                    'tr_name' => $record->TR_Name ?? '-',
                    'support_type' => $record->Support_Type ?? '-',
                    'target' => $record->Target ?? '-',
                    'issue' => $record->Issue ?? '-',
                    'to_account' => $record->TO_Account ?? '-',
                    'status' => $record->isCompleted() ? SupportRecord::STATUS_COMPLETED : SupportRecord::STATUS_IN_PROGRESS,
                    'team' => $team,
                    'report_kind' => 'institution',
                    'detail_key' => null,
                    'teacher_id' => null,
                    'sort_at' => $record->Support_Date?->getTimestamp() ?? 0,
                ];

                $buckets[$team]['institution'][] = $row;
                $buckets['totals']['institution']++;
            }
        }

        $teacherRecords = $this->teacherSupportHistoryAggregator->forInstitution(
            $candidateSkCodes,
            limit: $limitPerSource,
        );

        foreach ($teacherRecords as $record) {
            // 교사 지원 보고서는 Coach(TR) 업무 — 작성자 WORKDEPT와 무관하게 Coach Team 탭에 표시.
            $team = SupportAuthorTeamResolver::TEAM_COACH;
            $sortAt = (int) ($record['sort_at'] ?? 0);

            $row = [
                'id' => $record['id'] ?? '-',
                'coach' => $record['coach'] ?? '-',
                'teacher' => $record['teacher'] ?? '-',
                'date' => $record['date'] ?? '-',
                'status' => $record['status'] ?? '-',
                'type' => $record['type'] ?? '-',
                'team' => $team,
                'report_kind' => 'teacher',
                'detail_key' => $record['detail_key'] ?? null,
                'teacher_id' => $record['teacher_id'] ?? null,
                'sort_at' => $sortAt,
            ];

            $buckets[$team]['teacher'][] = $row;
            $buckets['totals']['teacher']++;
        }

        foreach ([SupportAuthorTeamResolver::TEAM_CO, SupportAuthorTeamResolver::TEAM_COACH, SupportAuthorTeamResolver::TEAM_CS, SupportAuthorTeamResolver::TEAM_UNKNOWN] as $teamKey) {
            usort(
                $buckets[$teamKey]['institution'],
                fn (array $a, array $b): int => ($b['sort_at'] ?? 0) <=> ($a['sort_at'] ?? 0),
            );
            usort(
                $buckets[$teamKey]['teacher'],
                fn (array $a, array $b): int => ($b['sort_at'] ?? 0) <=> ($a['sort_at'] ?? 0),
            );
        }

        return $buckets;
    }

    /**
     * @return array{
     *     co: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     coach: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     cs: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     unknown: array{institution: list<array<string, mixed>>, teacher: list<array<string, mixed>>},
     *     totals: array{institution: int, teacher: int},
     * }
     */
    private function emptyBuckets(): array
    {
        $emptyPair = ['institution' => [], 'teacher' => []];

        return [
            SupportAuthorTeamResolver::TEAM_CO => $emptyPair,
            SupportAuthorTeamResolver::TEAM_COACH => $emptyPair,
            SupportAuthorTeamResolver::TEAM_CS => $emptyPair,
            SupportAuthorTeamResolver::TEAM_UNKNOWN => $emptyPair,
            'totals' => ['institution' => 0, 'teacher' => 0],
        ];
    }

    private function formatSupportMeetTime(mixed $meetTime): string
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
}
