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

    /**
     * 상세 모달 조회용: 목록 검색·상태 필터와 무관하게 권한 범위 안 기관을 찾습니다.
     */
    public function forDetailLookup(): self
    {
        return new self(
            search: '',
            statusFilter: 'all',
            assignmentFilter: '',
            filterCo: '',
            filterTr: '',
            filterCs: '',
            sortField: $this->sortField,
            sortDirection: $this->sortDirection,
        );
    }
}
