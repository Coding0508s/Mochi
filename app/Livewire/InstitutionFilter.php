<?php

namespace App\Livewire;

use Livewire\Attributes\Reactive;
use Livewire\Component;

class InstitutionFilter extends Component
{
    public string $search = '';

    public string $statusFilter = 'all';

    public string $filterCo = '';

    public string $filterTr = '';

    public string $filterCs = '';

    /** 상단 요약(배정) 필터 칩 표시용 — 상태는 부모가 소유합니다. */
    #[Reactive]
    public string $assignmentFilter = '';

    /** @var list<string> */
    public array $coManagerOptions = [];

    /** @var list<string> */
    public array $trManagerOptions = [];

    /** @var list<string> */
    public array $csManagerOptions = [];

    public function mount(
        string $search = '',
        string $statusFilter = 'all',
        string $filterCo = '',
        string $filterTr = '',
        string $filterCs = '',
    ): void {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->filterCo = $filterCo;
        $this->filterTr = $filterTr;
        $this->filterCs = $filterCs;
    }

    public function updatedSearch(): void
    {
        $this->dispatchFilterUpdated();
    }

    public function updatedStatusFilter(): void
    {
        $this->dispatchFilterUpdated();
    }

    public function updatedFilterCo(): void
    {
        $this->dispatchFilterUpdated();
    }

    public function updatedFilterTr(): void
    {
        $this->dispatchFilterUpdated();
    }

    public function updatedFilterCs(): void
    {
        $this->dispatchFilterUpdated();
    }

    public function clearListFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->filterCo = '';
        $this->filterTr = '';
        $this->filterCs = '';

        $this->dispatch(
            'filter-updated',
            search: '',
            statusFilter: 'all',
            filterCo: '',
            filterTr: '',
            filterCs: '',
            resetAssignment: true,
        );
    }

    public function clearSearchFilter(): void
    {
        $this->search = '';
        $this->dispatchFilterUpdated();
    }

    public function clearStatusFilter(): void
    {
        $this->statusFilter = 'all';
        $this->dispatchFilterUpdated();
    }

    public function clearCoFilter(): void
    {
        $this->filterCo = '';
        $this->dispatchFilterUpdated();
    }

    public function clearTrFilter(): void
    {
        $this->filterTr = '';
        $this->dispatchFilterUpdated();
    }

    public function clearCsFilter(): void
    {
        $this->filterCs = '';
        $this->dispatchFilterUpdated();
    }

    public function clearAssignmentFilter(): void
    {
        $this->dispatch('institution-filter-assignment-cleared');
    }

    public function render()
    {
        return view('livewire.institution-filter');
    }

    private function dispatchFilterUpdated(): void
    {
        $this->dispatch(
            'filter-updated',
            search: $this->search,
            statusFilter: $this->statusFilter,
            filterCo: $this->filterCo,
            filterTr: $this->filterTr,
            filterCs: $this->filterCs,
            resetAssignment: false,
        );
    }
}
