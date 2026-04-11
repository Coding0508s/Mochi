<?php

namespace App\Livewire;

use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\Institution;
use App\Models\SupportRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SupportCreateForm extends Component
{
    public string $formSkCode = '';

    public string $formAccountName = '';

    public string $formInstitutionKeyword = '';

    public string $formCoName = '';

    public string $formSupportDate = '';

    public string $formSupportTime = '';

    public string $formSupportType = '전화';

    public string $formTarget = '';

    public string $formToAccount = '';

    /** 본사/타 부서 공유 (TO_Depart) */
    public string $formToDepart = '';

    public bool $formIsPotential = false;

    /** 잠재기관일 때만 사용하는 가능성 (A/B/C/D) */
    public string $formPossibility = '';

    public bool $formCompleted = false;

    protected array $rules = [
        'formSkCode' => 'required',
        'formSupportDate' => 'required|date',
        'formSupportTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
    ];

    protected array $messages = [
        'formSkCode.required' => '기관을 선택해 주세요.',
        'formSupportDate.required' => '지원 날짜를 입력해 주세요.',
        'formSupportDate.date' => '올바른 날짜 형식이 아닙니다.',
        'formSupportTime.required' => '지원 시간을 입력해 주세요.',
        'formSupportTime.regex' => '지원 시간은 HH:MM 형식으로 입력해 주세요.',
    ];

    public function mount(): void
    {
        $this->formCoName = (string) (auth()->user()?->name ?? 'Andrew Hur');
        $this->formSupportDate = now()->format('Y-m-d');
        $this->formSupportTime = now()->format('H:i');
    }

    public function updatedFormSkCode(string $value): void
    {
        if (blank($value)) {
            $this->formAccountName = '';
            $this->formIsPotential = false;
            $this->formPossibility = '';

            return;
        }

        $potential = $this->findPotentialBySkCode($value);
        $inst = Institution::query()->where('SKcode', $value)->first();
        $this->formAccountName = (string) ($inst?->AccountName ?? $potential?->AccountName ?? '');
        $this->formIsPotential = $potential !== null;
        $this->formPossibility = $potential ? (string) ($potential->Possibility ?? '') : '';
        if (filled($value)) {
            $this->applyDefaultCommunicationTemplatesIfEmpty();
        }
    }

    public function updatedFormInstitutionKeyword(string $value): void
    {
        $keyword = trim($value);

        if ($keyword === '') {
            $this->formSkCode = '';
            $this->formAccountName = '';
            $this->formIsPotential = false;
            $this->formPossibility = '';

            return;
        }

        $potential = $this->findPotentialByKeyword($keyword);
        if ($potential) {
            $this->formSkCode = (string) $potential->AccountCode;
            $this->formAccountName = (string) $potential->AccountName;
            $this->formIsPotential = true;
            $this->formPossibility = (string) ($potential->Possibility ?? '');
            $this->applyDefaultCommunicationTemplatesIfEmpty();

            return;
        }

        $inst = Institution::query()
            ->where('AccountName', $keyword)
            ->orWhere('SKcode', $keyword)
            ->first();

        if ($inst) {
            $this->formSkCode = (string) $inst->SKcode;
            $this->formAccountName = (string) $inst->AccountName;
            $this->formIsPotential = false;
            $this->formPossibility = '';
            $this->applyDefaultCommunicationTemplatesIfEmpty();

            return;
        }

        $this->formSkCode = '';
        $this->formAccountName = '';
        $this->formIsPotential = false;
        $this->formPossibility = '';
    }

    public function selectInstitution(string $skCode, bool $isPotential = false): void
    {
        $inst = Institution::query()->where('SKcode', $skCode)->first();

        if (! $inst && ! $isPotential) {
            return;
        }

        $potential = $this->findPotentialBySkCode($skCode);

        $this->formSkCode = $inst
            ? (string) $inst->SKcode
            : (string) ($potential?->AccountCode ?? $skCode);
        $this->formAccountName = $inst
            ? (string) $inst->AccountName
            : (string) ($potential?->AccountName ?? '');
        $this->formInstitutionKeyword = $this->formAccountName;
        $this->formIsPotential = $isPotential || $potential !== null;
        $this->formPossibility = $this->formIsPotential ? (string) ($potential?->Possibility ?? '') : '';
        $this->applyDefaultCommunicationTemplatesIfEmpty();
    }

    /**
     * 기관 선택 직후, 소통 필드가 비어 있을 때만 config 템플릿을 넣습니다.
     */
    private function applyDefaultCommunicationTemplatesIfEmpty(): void
    {
        if (blank($this->formSkCode)) {
            return;
        }

        if ($this->formToAccount === '') {
            $this->formToAccount = (string) config('support_report_defaults.to_account_template', '');
        }

        if ($this->formToDepart === '') {
            $this->formToDepart = (string) config('support_report_defaults.to_depart_template', '');
        }
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function (): void {
            $supportRecord = SupportRecord::query()->create([
                'Year' => (int) date('Y', strtotime($this->formSupportDate)),
                'SK_Code' => $this->formSkCode,
                'Account_Name' => $this->formAccountName,
                'TR_Name' => $this->formCoName,
                'Support_Date' => $this->formSupportDate,
                'Meet_Time' => $this->formSupportTime.':00',
                'Support_Type' => $this->formSupportType,
                'Target' => $this->formTarget,
                'Issue' => null,
                'TO_Account' => $this->formToAccount,
                'TO_Depart' => $this->formToDepart,
                'Status' => $this->formCompleted ? '완료' : '진행중',
                'CompletedDate' => $this->formCompleted ? now() : null,
                'CreatedDate' => now(),
            ]);

            $this->mirrorSupportToPotentialDetail($supportRecord);
        });

        session()->flash('success', '지원 보고서가 저장되었습니다.');
        $this->redirectRoute('supports.index', navigate: true);
    }

    private function mirrorSupportToPotentialDetail(SupportRecord $supportRecord): void
    {
        $skCode = trim((string) $supportRecord->SK_Code);
        if ($skCode === '') {
            return;
        }

        $target = CoNewTarget::query()
            ->where('AccountCode', $skCode)
            ->where('IsContract', false)
            ->orderByDesc('ID')
            ->first();

        if (! $target) {
            return;
        }

        // 폼에서 입력한 가능성 값이 있으면 CoNewTarget에도 반영
        $possibility = filled($this->formPossibility) ? $this->formPossibility : ($target->Possibility ?: null);
        if (filled($this->formPossibility) && $this->formPossibility !== $target->Possibility) {
            $target->Possibility = $this->formPossibility;
            $target->save();
        }

        $descriptionBlocks = array_filter([
            filled($this->formToAccount) ? '[기관 소통내용]'.PHP_EOL.$this->formToAccount : null,
            filled($this->formToDepart) ? '[본사/타 부서 공유]'.PHP_EOL.$this->formToDepart : null,
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => (int) date('Y', strtotime($this->formSupportDate)),
            'AccountName' => (string) ($target->AccountName ?? $this->formAccountName),
            'AccountManager' => filled($target->AccountManager) ? $target->AccountManager : ($this->formCoName ?: null),
            'MeetingDate' => $this->formSupportDate,
            'MeetingTime' => $this->formSupportTime,
            'MeetingTime_End' => null,
            'Description' => implode(PHP_EOL.PHP_EOL, $descriptionBlocks),
            'ConsultingType' => $this->formSupportType,
            'Possibility' => $possibility,
        ]);
    }

    public function render(): View
    {
        $keyword = trim($this->formInstitutionKeyword);
        $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';

        // Eloquent\Collection::merge()는 항목을 모델로 간주해 getKey()를 호출한다.
        // 배열로 합치려면 일반 Support\Collection으로 바꾼 뒤 merge 한다.
        $institutionSuggestions = collect(
            Institution::query()
                ->where(function ($query) use ($normalizedKeyword): void {
                    if ($normalizedKeyword === '') {
                        $query->whereRaw('1 = 0');

                        return;
                    }

                    $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                        ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                })
                ->orderBy('AccountName')
                ->limit(8)
                ->get(['SKcode', 'AccountName'])
        )->map(fn (Institution $inst): array => [
            'SKcode' => (string) $inst->SKcode,
            'AccountName' => (string) $inst->AccountName,
            'is_potential' => false,
        ]);

        $potentialSuggestions = $this->potentialSuggestions($normalizedKeyword);

        $mergedSuggestions = $institutionSuggestions
            ->merge($potentialSuggestions)
            ->groupBy('SKcode')
            ->map(function (Collection $group): array {
                $potentialItem = $group->firstWhere('is_potential', true);
                $item = $potentialItem ?? $group->first();

                return [
                    'SKcode' => (string) ($item['SKcode'] ?? ''),
                    'AccountName' => (string) ($item['AccountName'] ?? ''),
                    'is_potential' => (bool) ($item['is_potential'] ?? false),
                ];
            })
            ->sortBy('AccountName', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(8)
            ->values()
            ->map(fn (array $item): object => (object) $item);

        return view('livewire.support-create-form', [
            'institutionSuggestions' => $mergedSuggestions,
        ]);
    }

    private function findPotentialBySkCode(string $skCode): ?CoNewTarget
    {
        $trimmedSk = trim($skCode);
        if ($trimmedSk === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where('AccountCode', $trimmedSk)
            ->orderByDesc('ID')
            ->first();
    }

    private function findPotentialByKeyword(string $keyword): ?CoNewTarget
    {
        $trimmedKeyword = trim($keyword);
        if ($trimmedKeyword === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where(function ($query) use ($trimmedKeyword): void {
                $query->where('AccountName', $trimmedKeyword)
                    ->orWhere('AccountCode', $trimmedKeyword);
            })
            ->orderByDesc('ID')
            ->first();
    }

    private function potentialSuggestions(string $normalizedKeyword): Collection
    {
        if ($normalizedKeyword === '') {
            return collect();
        }

        return collect(
            CoNewTarget::query()
                ->where('IsContract', false)
                ->whereNotNull('AccountCode')
                ->where('AccountCode', '!=', '')
                ->where(function ($query) use ($normalizedKeyword): void {
                    $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                        ->orWhereRaw("REPLACE(AccountCode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                })
                ->orderBy('AccountName')
                ->limit(8)
                ->get(['AccountCode', 'AccountName'])
        )->map(fn (CoNewTarget $target): array => [
            'SKcode' => (string) $target->AccountCode,
            'AccountName' => (string) $target->AccountName,
            'is_potential' => true,
        ]);
    }
}
