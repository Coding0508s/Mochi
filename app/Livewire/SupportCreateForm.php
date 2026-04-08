<?php

namespace App\Livewire;

use App\Models\Institution;
use App\Models\SupportRecord;
use Illuminate\Contracts\View\View;
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

    public string $formVisitPurpose = '';

    public string $formToAccount = '';

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

            return;
        }

        $inst = Institution::query()->where('SKcode', $value)->first();
        $this->formAccountName = (string) ($inst?->AccountName ?? '');
    }

    public function updatedFormInstitutionKeyword(string $value): void
    {
        $keyword = trim($value);

        if ($keyword === '') {
            $this->formSkCode = '';
            $this->formAccountName = '';

            return;
        }

        $inst = Institution::query()
            ->where('AccountName', $keyword)
            ->orWhere('SKcode', $keyword)
            ->first();

        if ($inst) {
            $this->formSkCode = (string) $inst->SKcode;
            $this->formAccountName = (string) $inst->AccountName;

            return;
        }

        $this->formSkCode = '';
        $this->formAccountName = '';
    }

    public function selectInstitution(string $skCode): void
    {
        $inst = Institution::query()->where('SKcode', $skCode)->first();

        if (! $inst) {
            return;
        }

        $this->formSkCode = (string) $inst->SKcode;
        $this->formAccountName = (string) $inst->AccountName;
        $this->formInstitutionKeyword = (string) $inst->AccountName;
    }

    public function save(): void
    {
        $this->validate();

        SupportRecord::query()->create([
            'Year' => (int) date('Y', strtotime($this->formSupportDate)),
            'SK_Code' => $this->formSkCode,
            'Account_Name' => $this->formAccountName,
            'TR_Name' => $this->formCoName,
            'Support_Date' => $this->formSupportDate,
            'Meet_Time' => $this->formSupportTime.':00',
            'Support_Type' => $this->formSupportType,
            'Target' => $this->formTarget,
            'Issue' => $this->formVisitPurpose,
            'TO_Account' => $this->formToAccount,
            'Status' => $this->formCompleted ? '완료' : '진행중',
            'CompletedDate' => $this->formCompleted ? now() : null,
            'CreatedDate' => now(),
        ]);

        session()->flash('success', '지원 보고서가 저장되었습니다.');
        $this->redirectRoute('supports.index', navigate: true);
    }

    public function render(): View
    {
        $institutionSuggestions = Institution::query()
            ->where(function ($query): void {
                $keyword = trim($this->formInstitutionKeyword);
                $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';

                if ($normalizedKeyword === '') {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                    ->orWhereRaw("REPLACE(SKcode, ' ', '') like ?", ["%{$normalizedKeyword}%"]);
            })
            ->orderBy('AccountName')
            ->limit(8)
            ->get(['SKcode', 'AccountName']);

        return view('livewire.support-create-form', [
            'institutionSuggestions' => $institutionSuggestions,
        ]);
    }
}
