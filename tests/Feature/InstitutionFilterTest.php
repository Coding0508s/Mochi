<?php

namespace Tests\Feature;

use App\Livewire\InstitutionFilter;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionFilterTest extends TestCase
{
    public function test_dispatches_filter_updated_when_status_changes(): void
    {
        Livewire::test(InstitutionFilter::class, [
            'statusFilter' => 'active',
        ])
            ->set('statusFilter', 'terminated')
            ->assertDispatched('filter-updated', function (string $event, array $params): bool {
                return $event === 'filter-updated'
                    && ($params['statusFilter'] ?? null) === 'terminated'
                    && ($params['resetAssignment'] ?? null) === false;
            });
    }

    public function test_clear_list_filters_dispatches_reset_payload(): void
    {
        Livewire::test(InstitutionFilter::class, [
            'search' => '기관',
            'statusFilter' => 'active',
            'filterCo' => 'Peter Kim',
        ])
            ->call('clearListFilters')
            ->assertSet('search', '')
            ->assertSet('statusFilter', 'all')
            ->assertSet('filterCo', '')
            ->assertDispatched('filter-updated', function (string $event, array $params): bool {
                return $event === 'filter-updated'
                    && ($params['search'] ?? null) === ''
                    && ($params['statusFilter'] ?? null) === 'all'
                    && ($params['filterCo'] ?? null) === ''
                    && ($params['resetAssignment'] ?? null) === true;
            });
    }

    public function test_clear_assignment_filter_dispatches_dedicated_event(): void
    {
        Livewire::test(InstitutionFilter::class, [
            'assignmentFilter' => 'my_assigned',
        ])
            ->call('clearAssignmentFilter')
            ->assertDispatched('institution-filter-assignment-cleared');
    }

    public function test_toggle_view_all_dispatches_requested_event(): void
    {
        Livewire::test(InstitutionFilter::class, [
            'canViewAllInstitutions' => false,
            'canToggleViewAllInstitutions' => true,
        ])
            ->call('toggleViewAllInstitutions')
            ->assertDispatched('institution-view-all-toggle-requested');
    }
}
