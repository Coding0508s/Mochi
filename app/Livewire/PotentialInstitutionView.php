<?php

namespace App\Livewire;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class PotentialInstitutionView extends Component
{
    use WithPagination;

    public string $yearMonth = '';

    /** 연도별 조회 시 사용 (4자리 연도 문자열) */
    public string $filterYear = '';

    /** @var 'month'|'year' — 월 단위 또는 연 단위 */
    public string $periodGranularity = 'month';

    /** @var 'created'|'meeting' */
    public string $dateBasis = 'created';

    public string $search = '';

    public function mount(): void
    {
        if ($this->yearMonth === '') {
            $this->yearMonth = now()->format('Y-m');
        }
        if ($this->filterYear === '') {
            $this->filterYear = (string) now()->year;
        }
    }

    public function updatingYearMonth(): void
    {
        $this->resetPage();
    }

    public function updatingFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatingPeriodGranularity(): void
    {
        if ($this->periodGranularity === 'year' && $this->filterYear === '') {
            try {
                $this->filterYear = (string) Carbon::createFromFormat('Y-m', $this->yearMonth)->year;
            } catch (\Throwable) {
                $this->filterYear = (string) now()->year;
            }
        }
        if ($this->periodGranularity === 'month' && $this->yearMonth === '') {
            $this->yearMonth = now()->format('Y-m');
        }
        $this->resetPage();
    }

    public function updatingDateBasis(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function periodBounds(): array
    {
        if ($this->periodGranularity === 'year') {
            $y = (int) $this->filterYear;
            if ($y < 2000 || $y > 2100) {
                $y = (int) now()->year;
                $this->filterYear = (string) $y;
            }
            $start = Carbon::create($y, 1, 1)->startOfDay();
            $end = Carbon::create($y, 12, 31)->endOfDay();

            return [$start, $end];
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $this->yearMonth)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
            $this->yearMonth = $start->format('Y-m');
        }

        $end = (clone $start)->endOfMonth();

        return [$start, $end];
    }

    protected function applyDetailKeyword(Builder $query): void
    {
        if (blank($this->search)) {
            return;
        }

        $normalized = preg_replace('/\s+/u', '', (string) $this->search) ?? '';
        if ($normalized === '') {
            return;
        }

        $query->where(function (Builder $q) use ($normalized): void {
            $q->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalized}%"])
                ->orWhereRaw("REPLACE(IFNULL(AccountManager,''), ' ', '') like ?", ["%{$normalized}%"])
                ->orWhereRaw("REPLACE(IFNULL(ConsultingType,''), ' ', '') like ?", ["%{$normalized}%"])
                ->orWhereRaw("REPLACE(IFNULL(Possibility,''), ' ', '') like ?", ["%{$normalized}%"])
                ->orWhereRaw("REPLACE(IFNULL(Description,''), ' ', '') like ?", ["%{$normalized}%"]);
        });
    }

    public function render()
    {
        [$start, $end] = $this->periodBounds();
        $startDate = $start->toDateString();
        $endDate = $end->toDateString();

        $periodLabel = $this->periodGranularity === 'year'
            ? $start->translatedFormat('Y년')
            : $start->translatedFormat('Y년 n월');

        $yearOptions = [];
        $maxY = (int) now()->year + 1;
        for ($y = $maxY; $y >= 2018; $y--) {
            $yearOptions[] = $y;
        }

        if ($this->dateBasis === 'created') {
            $query = CoNewTarget::query()->keyword($this->search);
            $query->whereNotNull('CreatedDate')
                ->whereBetween('CreatedDate', [$startDate, $endDate]);

            $totalCount = (clone $query)->count();

            $rows = $query
                ->orderByDesc('CreatedDate')
                ->orderByDesc('ID')
                ->paginate(15);

            return view('livewire.potential-institution-view', [
                'basisCreated' => true,
                'targets' => $rows,
                'meetings' => null,
                'totalCount' => $totalCount,
                'periodLabel' => $periodLabel,
                'yearOptions' => $yearOptions,
            ]);
        }

        $query = CoNewTargetDetail::query()
            ->whereNotNull('MeetingDate')
            ->whereBetween('MeetingDate', [$startDate, $endDate]);

        $this->applyDetailKeyword($query);

        $totalCount = (clone $query)->count();

        $rows = $query
            ->orderByDesc('MeetingDate')
            ->orderByDesc('ID')
            ->paginate(15);

        return view('livewire.potential-institution-view', [
            'basisCreated' => false,
            'targets' => null,
            'meetings' => $rows,
            'totalCount' => $totalCount,
            'periodLabel' => $periodLabel,
            'yearOptions' => $yearOptions,
        ]);
    }
}
