<?php

namespace App\Livewire;

use App\Models\SupportRecord;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * CS 전용 "기관 이슈" 목록.
 * record_kind='issue'로 저장된 SupportRecord만 보여준다.
 */
class InstitutionIssueList extends Component
{
    use WithPagination;

    public string $filterYear = '';

    public string $search = '';

    public bool $filterUrgentOnly = false;

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

    public function render(): View
    {
        $records = SupportRecord::query()
            ->onlyIssues()
            ->ofYear($this->filterYear ? (int) $this->filterYear : null)
            ->keyword($this->search)
            ->when(
                $this->filterUrgentOnly && SupportRecord::tableHasColumn('is_urgent'),
                fn ($query) => $query->urgent()
            )
            ->withInstitutionWhenPossible()
            ->orderedForList()
            ->paginate(20);

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
            'records' => $records,
            'years' => $years,
        ]);
    }
}
