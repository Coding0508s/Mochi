<?php

namespace App\Livewire;

use App\DataTransferObjects\InstitutionListFilters;
use App\Support\InstitutionAccountListQuery;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class InstitutionTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = 'all';

    public string $assignmentFilter = '';

    public string $filterCo = '';

    public string $filterTr = '';

    public string $filterCs = '';

    public string $sortField = 'FGC_CreateDate';

    public string $sortDirection = 'asc';

    #[On('filter-updated')]
    public function onFilterUpdated(): void
    {
        $this->resetPage();
    }

    #[On('institution-filter-assignment-cleared')]
    public function onAssignmentFilterCleared(): void
    {
        $this->resetPage();
    }

    #[On('institution-table-reset-page')]
    public function resetTablePage(): void
    {
        $this->resetPage();
    }

    public function updatedAssignmentFilter(): void
    {
        $this->resetPage();
    }

    public function selectRow(int $institutionId): void
    {
        $this->dispatch('institution-row-selected', institutionId: $institutionId);
    }

    public function render(InstitutionAccountListQuery $accountListQuery)
    {
        $filters = InstitutionListFilters::fromComponent($this);
        $institutions = $accountListQuery->paginate($filters, 20);

        return view('livewire.institution-table', [
            'institutions' => $institutions,
        ]);
    }
}
