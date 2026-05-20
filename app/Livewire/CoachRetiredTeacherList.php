<?php

namespace App\Livewire;

use App\Models\RetirementList;
use App\Support\CoachRetirementListScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;

class CoachRetiredTeacherList extends Component
{
    use WithPagination;

    public int $filterYear;

    public string $search = '';

    public bool $showDetailModal = false;

    public ?array $selectedRetirement = null;

    public function mount(): void
    {
        $this->filterYear = now()->year;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
    {
        $maxYear = now()->year;
        $minYear = $maxYear - 10;

        $this->filterYear = max($minYear, min($maxYear, $this->filterYear));
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterYear = now()->year;
        $this->resetPage();
    }

    public function openDetailModal(int $retirementId): void
    {
        if (! Schema::hasTable('S_RetirementList')) {
            return;
        }

        $record = $this->buildBaseQuery()
            ->with(['teacher', 'institution.accountInfo'])
            ->where('ID', $retirementId)
            ->first();

        if (! $record) {
            return;
        }

        $this->selectedRetirement = [
            'id' => $record->ID,
            'teacher_id' => $record->TearcherID,
            'name' => $record->Name,
            'sk_code' => $record->SK_Code,
            'account_name' => $record->displayAccountName(),
            'position' => $record->displayPosition(),
            'tr_name' => $record->TR_Name,
            'retirement_date' => $record->RetirementDate?->format('Y-m-d'),
            'recommend_yn' => (bool) $record->RecommendYN,
            'recommend_description' => $record->RecommendDescription,
            'description' => $record->Description,
            'status' => $record->Status,
        ];
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedRetirement = null;
    }

    public function render()
    {
        if (! Schema::hasTable('S_RetirementList')) {
            return view('livewire.coach-retired-teacher-list', [
                'retirements' => collect(),
                'tableMissing' => true,
            ]);
        }

        $retirements = $this->buildBaseQuery()
            ->with(['teacher', 'institution.accountInfo'])
            ->tap(fn (Builder $query) => $this->applyYearFilter($query))
            ->tap(fn (Builder $query) => $this->applySearchFilter($query))
            ->orderByDesc(config('coach_retired_teachers.columns.retirement_date', 'RetirementDate'))
            ->orderBy('Name')
            ->paginate(50);

        return view('livewire.coach-retired-teacher-list', [
            'retirements' => $retirements,
            'tableMissing' => false,
        ]);
    }

    /**
     * @return Builder<RetirementList>
     */
    private function buildBaseQuery(): Builder
    {
        $query = RetirementList::query();
        CoachRetirementListScope::apply($query);

        return $query;
    }

    /**
     * @param  Builder<RetirementList>  $query
     */
    private function applyYearFilter(Builder $query): void
    {
        $query->forYear($this->filterYear);
    }

    /**
     * @param  Builder<RetirementList>  $query
     */
    private function applySearchFilter(Builder $query): void
    {
        if (! filled($this->search)) {
            return;
        }

        $term = '%'.preg_replace('/\s+/u', '', $this->search).'%';
        $query->where(function (Builder $q) use ($term): void {
            $q->whereRaw("REPLACE(Name, ' ', '') LIKE ?", [$term])
                ->orWhereRaw("REPLACE(Account_Name, ' ', '') LIKE ?", [$term])
                ->orWhere('SK_Code', 'LIKE', $term);
        });
    }
}
