<?php

namespace App\Livewire\Concerns;

use App\Enums\TeacherEmploymentType;
use App\Models\Teacher;
use App\Support\KoreanMobilePhoneFormatter;
use App\Support\TeamMenuContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

trait CreatesCoachInstitutionTeacher
{
    public bool $showInstitutionTeacherCreateModal = false;

    public string $institutionTeacherCreateNotice = '';

    public array $newTeacherForm = [];

    public function canCreateTeacherForOpenedInstitution(): bool
    {
        if (! $this->institutionInfo || ! empty($this->institutionInfo['is_terminated'])) {
            return false;
        }

        if ($this->isCrossTeamReadOnlyContext(TeamMenuContext::MENU_COACH)) {
            return false;
        }

        $skCode = trim((string) ($this->institutionInfo['sk_code'] ?? ''));
        if ($skCode === '') {
            return false;
        }

        return Gate::allows('createContactRecord', $skCode);
    }

    public function startInstitutionTeacherCreate(): void
    {
        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->canCreateTeacherForOpenedInstitution()) {
            return;
        }

        $this->institutionModalEditMode = false;
        $this->institutionTeacherCreateNotice = '';
        $this->resetErrorBag();
        $this->newTeacherForm = $this->emptyInstitutionTeacherForm();
        $this->showInstitutionTeacherCreateModal = true;
    }

    public function updatedNewTeacherForm(mixed $value, ?string $key = null): void
    {
        if ($key !== 'phone') {
            return;
        }

        $this->newTeacherForm['phone'] = KoreanMobilePhoneFormatter::format((string) $value);
    }

    public function cancelInstitutionTeacherCreate(): void
    {
        $this->showInstitutionTeacherCreateModal = false;
        $this->newTeacherForm = $this->emptyInstitutionTeacherForm();
        $this->resetErrorBag();
    }

    public function saveInstitutionTeacher(): void
    {
        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->canCreateTeacherForOpenedInstitution() || ! $this->institutionInfo) {
            return;
        }

        $skCode = trim((string) ($this->institutionInfo['sk_code'] ?? ''));
        $schoolName = (string) ($this->institutionInfo['name'] ?? '');
        $email = trim((string) ($this->newTeacherForm['email'] ?? ''));
        $this->newTeacherForm['email'] = $email;
        $this->newTeacherForm['phone'] = KoreanMobilePhoneFormatter::format(
            (string) ($this->newTeacherForm['phone'] ?? '')
        );

        $emailRules = ['nullable', 'email', 'max:190'];
        if ($email !== '') {
            $emailRules[] = Rule::unique('Teachers', 'Email');
        }

        $this->validate([
            'newTeacherForm.name' => ['required', 'string', 'max:190'],
            'newTeacherForm.email' => $emailRules,
            'newTeacherForm.phone' => ['nullable', 'string', 'max:13'],
            'newTeacherForm.position' => ['nullable', 'string', 'max:190'],
            'newTeacherForm.employment_type' => ['required', Rule::enum(TeacherEmploymentType::class)],
            'newTeacherForm.class_participation' => ['required', Rule::in(['in', 'out'])],
            'newTeacherForm.gs_essentials' => ['nullable', 'date'],
            'newTeacherForm.ls_essentials' => ['nullable', 'date'],
            'newTeacherForm.description' => ['nullable', 'string', 'max:5000'],
        ], [
            'newTeacherForm.name.required' => '교사 이름을 입력해 주세요.',
            'newTeacherForm.email.email' => '올바른 이메일 형식이 아닙니다.',
            'newTeacherForm.email.unique' => '이미 등록된 이메일입니다.',
            'newTeacherForm.description.max' => '비고는 5,000자 이내로 입력해 주세요.',
        ]);

        $gsEssentials = trim((string) ($this->newTeacherForm['gs_essentials'] ?? ''));
        $lsEssentials = trim((string) ($this->newTeacherForm['ls_essentials'] ?? ''));
        $classParticipation = (string) ($this->newTeacherForm['class_participation'] ?? 'out');

        Teacher::create([
            'Name' => trim((string) $this->newTeacherForm['name']),
            'Phone' => trim((string) ($this->newTeacherForm['phone'] ?? '')),
            'Email' => $email !== '' ? $email : null,
            'Position' => trim((string) ($this->newTeacherForm['position'] ?? '')),
            'SK_Code' => $skCode,
            'School_Name' => $schoolName,
            'Description' => trim((string) ($this->newTeacherForm['description'] ?? '')),
            'Status' => '활성화',
            'EmploymentType' => TeacherEmploymentType::fromMixed(
                $this->newTeacherForm['employment_type'] ?? null
            )->value,
            'ClassInOut' => $classParticipation === 'in',
            'GrapeSEEDEssentials' => $gsEssentials === '' ? null : $gsEssentials,
            'LittleSEEDEssentials' => $lsEssentials === '' ? null : $lsEssentials,
            'Created_Date' => now(),
        ]);

        $this->showInstitutionTeacherCreateModal = false;
        $this->newTeacherForm = $this->emptyInstitutionTeacherForm();
        $this->resetErrorBag();
        $this->openInstitutionModal($skCode);
        $this->institutionTeacherCreateNotice = '교사가 추가되었습니다.';
    }

    /**
     * @return array<string, string>
     */
    private function emptyInstitutionTeacherForm(): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'position' => '교사',
            'description' => '',
            'class_participation' => 'out',
            'employment_type' => TeacherEmploymentType::Unspecified->value,
            'gs_essentials' => '',
            'ls_essentials' => '',
        ];
    }

    private function resetInstitutionTeacherCreateState(): void
    {
        $this->showInstitutionTeacherCreateModal = false;
        $this->institutionTeacherCreateNotice = '';
        $this->newTeacherForm = $this->emptyInstitutionTeacherForm();
    }
}
