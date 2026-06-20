<?php

namespace App\DataTransferObjects;

readonly class InstitutionListFilters
{
    public function __construct(
        public string $search,
        public string $statusFilter,
        public string $assignmentFilter,
        public string $filterCo,
        public string $filterTr,
        public string $filterCs,
        public string $sortField,
        public string $sortDirection,
    ) {}

    public static function fromComponent(object $component): self
    {
        return new self(
            search: $component->search,
            statusFilter: $component->statusFilter,
            assignmentFilter: $component->assignmentFilter,
            filterCo: $component->filterCo,
            filterTr: $component->filterTr,
            filterCs: $component->filterCs,
            sortField: $component->sortField,
            sortDirection: $component->sortDirection,
        );
    }
}
