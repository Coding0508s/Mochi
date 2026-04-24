<?php

namespace App\Livewire;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\SupportRecord;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
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

    public bool $showDetailModal = false;

    public ?array $selectedTarget = null;

    public array $detailMeetings = [];

    public array $detailSupportRecords = [];

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

    public function openTargetDetail(int $targetId): void
    {
        $target = CoNewTarget::query()->findOrFail($targetId);
        $this->loadDetailData($target);
    }

    public function openTargetDetailFromMeeting(int $meetingId): void
    {
        $meeting = CoNewTargetDetail::query()->findOrFail($meetingId);

        $target = CoNewTarget::query()
            ->where('AccountName', (string) $meeting->AccountName)
            ->when(filled($meeting->AccountManager), function (Builder $query) use ($meeting): void {
                $query->where('AccountManager', $meeting->AccountManager);
            })
            ->orderByDesc('ID')
            ->first();

        if (! $target) {
            return;
        }

        $this->loadDetailData($target);
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedTarget = null;
        $this->detailMeetings = [];
        $this->detailSupportRecords = [];
    }

    private function loadDetailData(CoNewTarget $target): void
    {
        $this->selectedTarget = [
            'id' => $target->ID,
            'account_name' => $target->AccountName ?? '-',
            'account_code' => $target->AccountCode ?? '-',
            'account_manager' => $target->AccountManager ?? '-',
            'created_date' => $target->CreatedDate?->format('Y-m-d') ?? '-',
            'type' => $target->Type ?? '-',
            'gubun' => $target->Gubun ?? '-',
            'possibility' => $target->Possibility ?? '-',
            'director' => $target->Director ?? '-',
            'phone' => $target->Phone ?? '-',
            'address' => $target->Address ?? '-',
            'ls' => $target->LS ?? 0,
            'gs_k' => $target->GS_K ?? 0,
            'gs_e' => $target->GS_E ?? 0,
            'total' => $target->Total ?? 0,
            'is_contract' => (bool) ($target->IsContract ?? false),
        ];

        $this->detailMeetings = CoNewTargetDetail::query()
            ->ofAccount((string) ($target->AccountName ?? ''))
            ->when(filled($target->AccountManager), function (Builder $query) use ($target): void {
                $query->where('AccountManager', $target->AccountManager);
            })
            ->orderByDesc('MeetingDate')
            ->orderByDesc('ID')
            ->limit(100)
            ->get()
            ->map(function (CoNewTargetDetail $detail): array {
                return [
                    'id' => $detail->ID,
                    'meeting_date' => $detail->MeetingDate?->format('Y-m-d') ?? '-',
                    'meeting_time' => $detail->MeetingTime ?: '-',
                    'meeting_time_end' => $detail->MeetingTime_End ?: '-',
                    'account_manager' => $detail->AccountManager ?? '-',
                    'consulting_type' => $detail->ConsultingType ?? '-',
                    'possibility' => $detail->Possibility ?? '-',
                    'description' => $detail->Description ?? '-',
                ];
            })
            ->toArray();

        $skForSupport = trim((string) ($target->AccountCode ?? ''));

        $supportBase = SupportRecord::query();
        if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
            $supportBase->where(function ($query) use ($target, $skForSupport): void {
                $query->where('potential_target_id', (int) $target->ID);
                if ($skForSupport !== '') {
                    $query->orWhere('SK_Code', $skForSupport);
                }
            });
        } elseif ($skForSupport !== '') {
            $supportBase->where('SK_Code', $skForSupport);
        } else {
            $supportBase->whereRaw('1 = 0');
        }

        $this->detailSupportRecords = $supportBase
            ->orderByDesc('Support_Date')
            ->orderByDesc('ID')
            ->limit(50)
            ->get()
            ->map(function (SupportRecord $record): array {
                return [
                    'id' => $record->ID,
                    'support_date' => $record->Support_Date?->format('Y-m-d') ?? '-',
                    'meet_time' => $this->normalizeTime($record->Meet_Time),
                    'tr_name' => $record->TR_Name ?? '-',
                    'support_type' => $record->Support_Type ?? '-',
                    'target' => $record->Target ?? '-',
                    'to_account' => $record->TO_Account ?? '-',
                    'status' => $record->Status ?? '-',
                ];
            })
            ->toArray();

        $this->showDetailModal = true;
    }

    private function normalizeTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '-';
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
