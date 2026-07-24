<?php

namespace App\Livewire;

use App\Models\SupportRecord;
use App\Support\InstitutionIssueTeacherGrouper;
use App\Support\SupportRecordCascadeDeleter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
     *     group_key: string,
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

    public ?int $editingIssueId = null;

    public bool $issueModalViewOnly = true;

    public string $editSupportDate = '';

    public string $editSupportTime = '13:00';

    public string $editIssue = '';

    public string $editToAccount = '';

    public bool $editIsUrgent = false;

    public bool $editCompleted = false;

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
        $this->resetIssueEditState();

        $groups = InstitutionIssueTeacherGrouper::group($this->filteredIssueRecords());
        $matched = collect($groups)->firstWhere('group_key', $groupKey);

        if ($matched === null) {
            return;
        }

        $this->applySelectedGroup($matched);
        $this->showDetailModal = true;
    }

    public function toggleExpandedIssue(int $issueId): void
    {
        if ($this->editingIssueId !== null && $this->editingIssueId !== $issueId) {
            $this->resetIssueEditState();
        }

        $this->expandedIssueId = $this->expandedIssueId === $issueId ? null : $issueId;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedGroup = null;
        $this->expandedIssueId = null;
        $this->resetIssueEditState();
    }

    public function startIssueEdit(int $id): void
    {
        $record = $this->findIssueOrFail($id);
        Gate::authorize('updateSupportRecord', $record);

        $this->fillEditForm($record);
        $this->editingIssueId = (int) $record->ID;
        $this->expandedIssueId = (int) $record->ID;
        $this->issueModalViewOnly = false;
    }

    public function cancelIssueEdit(): void
    {
        $this->resetIssueEditState();
    }

    public function saveIssue(): void
    {
        if ($this->editingIssueId === null || $this->issueModalViewOnly) {
            return;
        }

        $record = $this->findIssueOrFail((int) $this->editingIssueId);
        Gate::authorize('updateSupportRecord', $record);

        $this->editSupportTime = $this->normalizeTimeForInput($this->editSupportTime);

        $this->validate([
            'editSupportDate' => ['required', 'date'],
            'editSupportTime' => ['required', 'date_format:H:i'],
            'editIssue' => ['required', 'string'],
            'editToAccount' => ['nullable', 'string'],
            'editIsUrgent' => ['boolean'],
            'editCompleted' => ['boolean'],
        ]);

        $attributes = SupportRecord::filterAttributesForTable([
            'Support_Date' => $this->editSupportDate,
            'Meet_Time' => $this->editSupportTime.':00',
            'Issue' => $this->editIssue,
            'TO_Account' => filled($this->editToAccount) ? $this->editToAccount : null,
            'is_urgent' => $this->editIsUrgent,
            ...SupportRecord::completionAttributes($this->editCompleted),
        ]);

        $record->update($attributes);

        $this->resetIssueEditState();
        $this->refreshSelectedGroup();

        session()->flash('success', '기관 이슈가 저장되었습니다.');
    }

    public function deleteIssue(int $id): void
    {
        $record = $this->findIssueOrFail($id);

        // 기관 이슈만: 관리자 또는 작성자 본인. 전역 deleteSupportRecords(관리자 전용)는 바꾸지 않는다.
        abort_unless($this->userCanDeleteIssue($record), 403);

        app(SupportRecordCascadeDeleter::class)->delete($record);

        $this->resetIssueEditState();

        if ($this->selectedGroup === null) {
            session()->flash('success', '기관 이슈가 삭제되었습니다.');

            return;
        }

        $groupKey = (string) ($this->selectedGroup['group_key'] ?? '');
        $this->refreshSelectedGroup();

        if ($this->selectedGroup === null || $groupKey === '') {
            $this->closeDetailModal();
        }

        session()->flash('success', '기관 이슈가 삭제되었습니다.');
    }

    public function canUpdateIssue(int $id): bool
    {
        $record = SupportRecord::query()->onlyIssues()->find($id);

        return $record instanceof SupportRecord
            && Gate::allows('updateSupportRecord', $record);
    }

    public function canDeleteIssue(int $id): bool
    {
        $record = SupportRecord::query()->onlyIssues()->find($id);

        return $record instanceof SupportRecord
            && $this->userCanDeleteIssue($record);
    }

    private function userCanDeleteIssue(SupportRecord $record): bool
    {
        return Gate::allows('deleteSupportRecords')
            || Gate::allows('updateSupportRecord', $record);
    }

    /**
     * 읽기/수정 모드 공통: 완료처리를 즉시 토글한다.
     */
    public function toggleIssueComplete(int $id): void
    {
        $record = $this->findIssueOrFail($id);
        Gate::authorize('updateSupportRecord', $record);

        $wasCompleted = $record->isCompleted();
        $record->toggleComplete(! $wasCompleted);

        if ($this->editingIssueId === $id) {
            $this->editCompleted = ! $wasCompleted;
        }

        $this->refreshSelectedGroup();
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
     * @param  array{
     *     group_key: string,
     *     sk_code: string,
     *     account_name: string,
     *     teacher_label: string,
     *     is_institution_common: bool,
     *     issue_count: int,
     *     issues: list<SupportRecord>
     * }  $matched
     */
    private function applySelectedGroup(array $matched): void
    {
        $issues = array_map(
            fn (SupportRecord $record): array => $this->issueSnapshot($record),
            $matched['issues']
        );

        $this->selectedGroup = [
            'group_key' => (string) $matched['group_key'],
            'sk_code' => $matched['sk_code'],
            'account_name' => $matched['account_name'],
            'teacher_label' => $matched['teacher_label'],
            'is_institution_common' => $matched['is_institution_common'],
            'issue_count' => $matched['issue_count'],
            'issues' => $issues,
        ];
        $this->expandedIssueId = isset($issues[0]['id']) ? (int) $issues[0]['id'] : null;
    }

    private function refreshSelectedGroup(): void
    {
        $groupKey = (string) ($this->selectedGroup['group_key'] ?? '');
        if ($groupKey === '') {
            $this->selectedGroup = null;

            return;
        }

        $groups = InstitutionIssueTeacherGrouper::group($this->filteredIssueRecords());
        $matched = collect($groups)->firstWhere('group_key', $groupKey);

        if ($matched === null) {
            $this->selectedGroup = null;
            $this->expandedIssueId = null;

            return;
        }

        $previousExpanded = $this->expandedIssueId;
        $this->applySelectedGroup($matched);

        $issueIds = collect($this->selectedGroup['issues'] ?? [])->pluck('id')->all();
        if ($previousExpanded !== null && in_array($previousExpanded, $issueIds, true)) {
            $this->expandedIssueId = $previousExpanded;
        } else {
            // 접혀 있던 상태(null)를 유지한다. applySelectedGroup 기본값(첫 이슈 펼침)을 덮어쓴다.
            $this->expandedIssueId = null;
        }
    }

    private function findIssueOrFail(int $id): SupportRecord
    {
        return SupportRecord::query()->onlyIssues()->findOrFail($id);
    }

    private function fillEditForm(SupportRecord $record): void
    {
        $this->editSupportDate = $record->Support_Date?->format('Y-m-d') ?? '';
        $this->editSupportTime = $this->normalizeTimeForInput($record->Meet_Time);
        $this->editIssue = (string) ($record->Issue ?? '');
        $this->editToAccount = (string) ($record->TO_Account ?? '');
        $this->editIsUrgent = (bool) ($record->is_urgent ?? false);
        $this->editCompleted = $record->isCompleted();
        $this->resetValidation();
    }

    private function resetIssueEditState(): void
    {
        $this->editingIssueId = null;
        $this->issueModalViewOnly = true;
        $this->editSupportDate = '';
        $this->editSupportTime = '13:00';
        $this->editIssue = '';
        $this->editToAccount = '';
        $this->editIsUrgent = false;
        $this->editCompleted = false;
        $this->resetValidation();
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

    private function normalizeTimeForInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '13:00';
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
