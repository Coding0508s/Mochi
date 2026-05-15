<?php

namespace App\Livewire;

use App\Actions\DeletePotentialMeetingDetail;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\SkCodeRequest;
use App\Models\SupportRecord;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
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

    public bool $showMeetingDetailModal = false;

    public ?array $selectedMeeting = null;

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

    #[On('potential-meeting-saved')]
    public function refreshDetailAfterMeeting(int $targetId): void
    {
        if (! $this->showDetailModal) {
            return;
        }

        if ((int) ($this->selectedTarget['id'] ?? 0) !== $targetId) {
            return;
        }

        $target = CoNewTarget::query()->find($targetId);
        if ($target) {
            $this->loadDetailData($target);
        }
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
        $this->showMeetingDetailModal = false;
        $this->selectedMeeting = null;
    }

    public function openMeetingDetailModal(int $meetingId): void
    {
        $meeting = collect($this->detailMeetings)->firstWhere('id', $meetingId);

        if (! $meeting) {
            return;
        }

        $this->selectedMeeting = $meeting;
        $this->showMeetingDetailModal = true;
    }

    public function closeMeetingDetailModal(): void
    {
        $this->showMeetingDetailModal = false;
        $this->selectedMeeting = null;
    }

    public function deleteMeetingDetail(int $detailId): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $targetId = (int) ($this->selectedTarget['id'] ?? 0);
        if ($targetId <= 0) {
            return;
        }

        $target = CoNewTarget::query()->find($targetId);
        if (! $target) {
            return;
        }

        try {
            app(DeletePotentialMeetingDetail::class)($target, $detailId);
        } catch (AuthorizationException $e) {
            $this->addError('deleteMeeting', $e->getMessage());

            return;
        } catch (ModelNotFoundException $e) {
            report($e);
            $this->addError('deleteMeeting', '삭제할 미팅 이력을 찾을 수 없습니다.');

            return;
        }

        $this->closeMeetingDetailModal();
        $this->openTargetDetail($targetId);
        session()->flash('success', '미팅/컨설팅 이력을 삭제했습니다.');
    }

    /**
     * 미계약 잠재기관(CoNewTarget) 1건 삭제. 계약·정식 전환된 행은 삭제 불가. 관리자만.
     */
    public function deleteUncontractedTarget(int $id): void
    {
        Gate::authorize('deletePotentialInstitutions');

        $target = CoNewTarget::query()->findOrFail($id);

        if ($target->IsContract) {
            $this->addError('deleteTarget', '계약 처리된 잠재기관(정식 기관)은 삭제할 수 없습니다.');

            return;
        }

        $accountName = (string) ($target->AccountName ?? '');

        try {
            DB::transaction(function () use ($target, $accountName): void {
                if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
                    SupportRecord::query()
                        ->where('potential_target_id', (int) $target->ID)
                        ->delete();
                }

                $detailQuery = CoNewTargetDetail::query()->ofAccount($accountName);
                if (filled($target->AccountManager)) {
                    $detailQuery->where('AccountManager', $target->AccountManager);
                }
                $detailQuery->delete();

                if (Schema::hasTable('sk_code_requests')) {
                    SkCodeRequest::query()
                        ->where('co_new_target_id', (int) $target->ID)
                        ->delete();
                }

                $target->delete();
            });
        } catch (\Throwable $e) {
            report($e);
            $this->addError('deleteTarget', '삭제 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');

            return;
        }

        if ($this->showDetailModal && (int) ($this->selectedTarget['id'] ?? 0) === $id) {
            $this->closeDetailModal();
        }

        session()->flash('success', '잠재기관을 삭제했습니다.');
    }

    private function loadDetailData(CoNewTarget $target): void
    {
        $user = auth()->user();

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
            'can_manage' => $user !== null && $target->isManagedBy($user),
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
                    'account_name' => $detail->AccountName ?? '-',
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
