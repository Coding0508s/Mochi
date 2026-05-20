<?php

namespace App\Livewire\Concerns;

use App\Actions\DeleteSupportRecord;
use App\Actions\UpdateSupportRecord;
use App\Models\SupportRecord;
use App\Support\ManagerNameNormalizer;
use Illuminate\Auth\Access\AuthorizationException;

trait ManagesInstitutionSupportDetailEdit
{
    public bool $supportDetailEditMode = false;

    public string $editSupportDate = '';

    public string $editSupportTime = '';

    public string $editSupportType = '';

    public string $editTarget = '';

    public string $editIssue = '';

    public string $editToAccount = '';

    public string $editToDepart = '';

    public string $editOthers = '';

    public bool $editCompleted = false;

    public function enterSupportDetailEditMode(): void
    {
        if ($this->selectedSupportRecord === null || ! $this->canEditSupportDetail()) {
            return;
        }

        $this->hydrateSupportDetailEditFormFromSelectedRecord();
        $this->supportDetailEditMode = true;
        $this->resetValidation();
    }

    public function cancelSupportDetailEdit(): void
    {
        $this->supportDetailEditMode = false;
        $this->resetSupportDetailEditForm();
        $this->resetValidation();
    }

    public function saveSupportDetailEdit(): void
    {
        if ($this->selectedSupportRecord === null || $this->selectedInstitution === null) {
            return;
        }

        $supportId = (int) ($this->selectedSupportRecord['id'] ?? 0);
        $skCode = trim((string) ($this->selectedInstitution['skcode'] ?? ''));

        if ($supportId <= 0 || $skCode === '') {
            return;
        }

        $this->editSupportTime = $this->normalizeSupportTimeForInput($this->editSupportTime);

        $validated = $this->validate($this->supportDetailEditRules(), $this->supportDetailEditMessages());

        $record = SupportRecord::query()
            ->whereKey($supportId)
            ->where('SK_Code', $skCode)
            ->first();

        if ($record === null) {
            $this->addError('supportDetailEdit', '수정할 지원 내역을 찾을 수 없습니다.');

            return;
        }

        try {
            $updated = app(UpdateSupportRecord::class)($record, $skCode, [
                'support_date' => $validated['editSupportDate'],
                'support_time' => $validated['editSupportTime'],
                'support_type' => $validated['editSupportType'],
                'target' => $validated['editTarget'] ?: null,
                'issue' => $validated['editIssue'] ?: null,
                'to_account' => $validated['editToAccount'] ?: null,
                'to_depart' => $validated['editToDepart'] ?: null,
                'others' => $validated['editOthers'] ?: null,
                'completed' => (bool) $validated['editCompleted'],
            ]);
        } catch (AuthorizationException $e) {
            $this->addError('supportDetailEdit', $e->getMessage());

            return;
        }

        $this->supportDetailEditMode = false;
        $this->resetSupportDetailEditForm();
        $this->reloadSupportDetailAfterUpdate($updated);
        session()->flash('success', '지원 내역을 수정했습니다.');
    }

    public function deleteSupportDetail(): void
    {
        if ($this->selectedSupportRecord === null || $this->selectedInstitution === null) {
            return;
        }

        if ($this->isSelectedInstitutionTerminated()) {
            return;
        }

        $supportId = (int) ($this->selectedSupportRecord['id'] ?? 0);
        $skCode = trim((string) ($this->selectedInstitution['skcode'] ?? ''));

        if ($supportId <= 0 || $skCode === '') {
            return;
        }

        $record = SupportRecord::query()
            ->whereKey($supportId)
            ->where('SK_Code', $skCode)
            ->first();

        if ($record === null) {
            return;
        }

        try {
            app(DeleteSupportRecord::class)($record, $skCode);
        } catch (AuthorizationException $e) {
            $this->addError('supportDetailEdit', $e->getMessage());

            return;
        }

        $institutionId = (int) ($this->selectedInstitution['id'] ?? 0);
        $this->closeSupportDetailModal();
        $this->resetSupportDetailEditState();

        if ($institutionId > 0) {
            $this->openDetailModal($institutionId);
        }

        session()->flash('success', '지원 내역을 삭제했습니다.');
    }

    protected function canEditSupportDetail(): bool
    {
        if ($this->isSelectedInstitutionTerminated()) {
            return false;
        }

        if ($this->selectedSupportRecord === null) {
            return false;
        }

        return (bool) ($this->selectedSupportRecord['can_edit'] ?? false);
    }

    protected function isSelectedInstitutionTerminated(): bool
    {
        $customerType = (string) ($this->selectedInstitution['customer_type'] ?? '');

        return str_contains($customerType, '해지');
    }

    protected function resolveSupportRecordCanEdit(SupportRecord $record): bool
    {
        if ($this->isSelectedInstitutionTerminated()) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $authorKey = ManagerNameNormalizer::normalize((string) ($record->TR_Name ?? ''));
        $userKey = ManagerNameNormalizer::normalize($user->nameForCoReports());

        return $authorKey !== '' && $userKey !== '' && $authorKey === $userKey;
    }

    /**
     * @return array<string, mixed>
     */
    protected function supportDetailEditRules(): array
    {
        return [
            'editSupportDate' => ['required', 'date'],
            'editSupportTime' => ['required', 'date_format:H:i'],
            'editSupportType' => ['required', 'string', 'max:100'],
            'editTarget' => ['nullable', 'string', 'max:255'],
            'editIssue' => ['nullable', 'string', 'max:5000'],
            'editToAccount' => ['nullable', 'string', 'max:5000'],
            'editToDepart' => ['nullable', 'string', 'max:5000'],
            'editOthers' => ['nullable', 'string', 'max:5000'],
            'editCompleted' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function supportDetailEditMessages(): array
    {
        return [
            'editSupportDate.required' => '지원일을 입력해 주세요.',
            'editSupportDate.date' => '지원일 형식이 올바르지 않습니다.',
            'editSupportTime.required' => '지원 시간을 입력해 주세요.',
            'editSupportTime.date_format' => '지원 시간 형식이 올바르지 않습니다.',
            'editSupportType.required' => '지원방법을 입력해 주세요.',
        ];
    }

    protected function resetSupportDetailEditState(): void
    {
        $this->supportDetailEditMode = false;
        $this->resetSupportDetailEditForm();
    }

    protected function resetSupportDetailEditForm(): void
    {
        $this->editSupportDate = '';
        $this->editSupportTime = '';
        $this->editSupportType = '';
        $this->editTarget = '';
        $this->editIssue = '';
        $this->editToAccount = '';
        $this->editToDepart = '';
        $this->editOthers = '';
        $this->editCompleted = false;
    }

    protected function hydrateSupportDetailEditFormFromSelectedRecord(): void
    {
        if ($this->selectedSupportRecord === null) {
            return;
        }

        $this->editSupportDate = $this->displaySupportValueToEditString($this->selectedSupportRecord['support_date'] ?? '');
        $this->editSupportTime = $this->normalizeSupportTimeForInput(
            $this->displaySupportValueToEditString($this->selectedSupportRecord['support_time'] ?? ''),
        );
        $this->editSupportType = $this->displaySupportValueToEditString($this->selectedSupportRecord['support_type'] ?? '');
        $this->editTarget = $this->displaySupportValueToEditString($this->selectedSupportRecord['target'] ?? '');
        $this->editIssue = $this->displaySupportValueToEditString($this->selectedSupportRecord['issue'] ?? '');
        $this->editToAccount = $this->displaySupportValueToEditString($this->selectedSupportRecord['to_account'] ?? '');
        $this->editToDepart = $this->displaySupportValueToEditString($this->selectedSupportRecord['to_depart'] ?? '');
        $this->editOthers = $this->displaySupportValueToEditString($this->selectedSupportRecord['others'] ?? '');
        $this->editCompleted = ($this->selectedSupportRecord['status'] ?? '') === '완료';
    }

    protected function displaySupportValueToEditString(mixed $value): string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '-' ? '' : $stringValue;
    }

    protected function normalizeSupportTimeForInput(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '' || $stringValue === '-') {
            return '09:00';
        }

        if (preg_match('/([01]\d|2[0-3]):([0-5]\d)/', $stringValue, $matches)) {
            return $matches[0];
        }

        return '09:00';
    }

    abstract protected function reloadSupportDetailAfterUpdate(SupportRecord $record): void;
}
