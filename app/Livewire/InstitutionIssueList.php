<?php

namespace App\Livewire;

use App\Models\SupportRecord;
use App\Support\InstitutionIssueTeacherGrouper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CS 전용 "기관 이슈" 목록.
 * 같은 기관+교사는 1행으로 묶고, 클릭 시 해당 그룹의 이슈를 모달로 본다.
 */
class InstitutionIssueList extends Component
{
    use WithPagination;

    public string $filterYear = '';

    public string $search = '';

    public bool $filterUrgentOnly = false;

    public bool $showDetailModal = false;

    /**
     * @var array{
     *     sk_code: string,
     *     account_name: string,
     *     teacher_label: string,
     *     is_institution_common: bool,
     *     issue_count: int,
     *     issues: list<array{
     *         id: int,
     *         tr_name: string,
     *         support_date: string,
     *         meet_time: string,
     *         issue: string,
     *         to_account: string,
     *         is_urgent: bool,
     *         status_label: string,
     *         is_completed: bool
     *     }>
     * }|null
     */
    public ?array $selectedGroup = null;

    public ?int $expandedIssueId = null;

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUrgentOnly(): void
    {
        $this->resetPage();
    }

    public function openGroupDetail(string $groupKey): void
    {
        $groups = InstitutionIssueTeacherGrouper::group($this->filteredIssueRecords());
        $matched = collect($groups)->firstWhere('group_key', $groupKey);

        if ($matched === null) {
            return;
        }

        $issues = array_map(
            fn (SupportRecord $record): array => $this->issueSnapshot($record),
            $matched['issues']
        );

        $this->selectedGroup = [
            'sk_code' => $matched['sk_code'],
            'account_name' => $matched['account_name'],
            'teacher_label' => $matched['teacher_label'],
            'is_institution_common' => $matched['is_institution_common'],
            'issue_count' => $matched['issue_count'],
            'issues' => $issues,
        ];
        $this->expandedIssueId = isset($issues[0]['id']) ? (int) $issues[0]['id'] : null;
        $this->showDetailModal = true;
    }

    public function toggleExpandedIssue(int $issueId): void
    {
        $this->expandedIssueId = $this->expandedIssueId === $issueId ? null : $issueId;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedGroup = null;
        $this->expandedIssueId = null;
    }

    public function render(): View
    {
        $allRecords = $this->filteredIssueRecords();
        $groups = InstitutionIssueTeacherGrouper::group($allRecords);
        $issueTotal = $allRecords->count();

        $perPage = 20;
        $page = max(1, (int) $this->getPage());
        $totalGroups = count($groups);
        $slice = array_slice($groups, ($page - 1) * $perPage, $perPage);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        $paginator = new Paginator(
            $slice,
            $totalGroups,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );

        $institutionRowspans = InstitutionIssueTeacherGrouper::institutionRowspans($slice);

        $years = SupportRecord::query()
            ->onlyIssues()
            ->when(SupportRecord::tableHasColumn('Year'), fn ($query) => $query->whereNotNull('Year'))
            ->get()
            ->map(fn (SupportRecord $record): ?int => $record->Support_Date?->year ?? ($record->Year ? (int) $record->Year : null))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('livewire.institution-issue-list', [
            'groups' => $paginator,
            'institutionRowspans' => $institutionRowspans,
            'issueTotal' => $issueTotal,
            'years' => $years,
        ]);
    }

    /**
     * @return Collection<int, SupportRecord>
     */
    private function filteredIssueRecords()
    {
        return SupportRecord::query()
            ->onlyIssues()
            ->ofYear($this->filterYear ? (int) $this->filterYear : null)
            ->keyword($this->search)
            ->when(
                $this->filterUrgentOnly && SupportRecord::tableHasColumn('is_urgent'),
                fn ($query) => $query->urgent()
            )
            ->withInstitutionWhenPossible()
            ->get();
    }

    public function formatMeetTimeForDisplay(mixed $value): string
    {
        return $this->normalizeMeetTimeForDisplay($value);
    }

    /**
     * @return array{
     *     id: int,
     *     tr_name: string,
     *     support_date: string,
     *     meet_time: string,
     *     issue: string,
     *     to_account: string,
     *     is_urgent: bool,
     *     status_label: string,
     *     is_completed: bool
     * }
     */
    private function issueSnapshot(SupportRecord $record): array
    {
        return [
            'id' => (int) $record->ID,
            'tr_name' => (string) ($record->TR_Name ?? ''),
            'support_date' => $record->Support_Date?->format('Y-m-d') ?? '',
            'meet_time' => $this->normalizeMeetTimeForDisplay($record->Meet_Time),
            'issue' => $this->normalizeMultilineText((string) ($record->Issue ?? '')),
            'to_account' => $this->normalizeMultilineText((string) ($record->TO_Account ?? '')),
            'is_urgent' => (bool) ($record->is_urgent ?? false),
            'status_label' => $record->isCompleted() ? '완료' : '진행중',
            'is_completed' => $record->isCompleted(),
        ];
    }

    private function normalizeMeetTimeForDisplay(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return '';
        }

        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function normalizeMultilineText(string $value): string
    {
        $normalized = preg_replace("/[ \t]+\n/u", "\n", $value) ?? $value;
        $normalized = preg_replace("/\n{3,}/u", "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }
}
