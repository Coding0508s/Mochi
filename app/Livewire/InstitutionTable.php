<?php

namespace App\Livewire;

use App\DataTransferObjects\InstitutionListFilters;
use App\Support\InstitutionAccountListQuery;
use Livewire\Attributes\On;
use Livewire\Component;

class InstitutionTable extends Component
{
    public string $search = '';

    public string $statusFilter = 'active';

    public string $assignmentFilter = '';

    public string $filterCo = '';

    public string $filterTr = '';

    public string $filterCs = '';

    public string $sortField = 'FGC_CreateDate';

    public string $sortDirection = 'asc';

    #[On('filter-updated')]
    public function onFilterUpdated(
        string $search,
        string $statusFilter,
        string $filterCo,
        string $filterTr,
        string $filterCs,
        bool $resetAssignment = false,
    ): void {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->filterCo = $filterCo;
        $this->filterTr = $filterTr;
        $this->filterCs = $filterCs;

        if ($resetAssignment) {
            $this->assignmentFilter = '';
        }
    }

    public function selectRow(int $institutionId): void
    {
        $this->dispatch('institution-row-selected', institutionId: $institutionId);
    }

    public function render(InstitutionAccountListQuery $accountListQuery)
    {
        $filters = InstitutionListFilters::fromComponent($this);
        $institutions = $accountListQuery->list($filters);

        return view('livewire.institution-table', [
            'institutions' => $institutions,
        ]);
    }
}
