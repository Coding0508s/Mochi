<?php

namespace App\Livewire\Concerns;

use App\Actions\UpdatePotentialInstitution;
use App\Models\CoNewTarget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

trait ManagesPotentialInstitutionDetailEdit
{
    public bool $detailEditMode = false;

    public string $editAccountManager = '';

    public string $editType = '';

    public string $editGubun = '';

    public string $editAccountName = '';

    public string $editDirector = '';

    public string $editPhone = '';

    public string $editAddress = '';

    public string $editConnected = '';

    public string $editPossibility = '';

    public string $editLS = '';

    public string $editGSK = '';

    public string $editGSE = '';

    public function enterDetailEditMode(): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        if (($this->selectedTarget['is_contract'] ?? false) || ! ($this->selectedTarget['can_manage'] ?? false)) {
            return;
        }

        $this->hydrateDetailEditFormFromSelectedTarget();
        $this->detailEditMode = true;
        $this->resetValidation();
    }

    public function cancelDetailEdit(): void
    {
        $this->detailEditMode = false;
        $this->resetDetailEditForm();
        $this->resetValidation();
    }

    public function saveDetailEdit(): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $targetId = (int) ($this->selectedTarget['id'] ?? 0);
        if ($targetId <= 0) {
            return;
        }

        $validated = $this->validate($this->detailEditRules(), $this->detailEditMessages());

        $target = CoNewTarget::query()->findOrFail($targetId);

        try {
            $updated = app(UpdatePotentialInstitution::class)($target, [
                'account_manager' => $validated['editAccountManager'] ?: null,
                'type' => $validated['editType'],
                'gubun' => $validated['editGubun'],
                'account_name' => $validated['editAccountName'],
                'director' => $validated['editDirector'] ?: null,
                'phone' => $validated['editPhone'] ?: null,
                'address' => $validated['editAddress'] ?: null,
                'connected' => $validated['editConnected'] ?: null,
                'possibility' => $validated['editPossibility'] ?: null,
                'ls' => $this->toNonNegativeInt($validated['editLS'] ?? null),
                'gs_k' => $this->toNonNegativeInt($validated['editGSK'] ?? null),
                'gs_e' => $this->toNonNegativeInt($validated['editGSE'] ?? null),
            ]);
        } catch (AuthorizationException $e) {
            $this->addError('detailEdit', $e->getMessage());

            return;
        }

        $this->detailEditMode = false;
        $this->resetDetailEditForm();
        $this->reloadDetailModalAfterTargetUpdate($updated);
        session()->flash('success', '잠재기관 정보가 저장되었습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function detailEditRules(): array
    {
        return [
            'editAccountManager' => ['nullable', 'string', 'max:100'],
            'editGubun' => ['required', 'string', 'max:100'],
            'editType' => ['required', 'string', 'max:100'],
            'editAccountName' => ['required', 'string', 'max:150'],
            'editDirector' => ['nullable', 'string', 'max:100'],
            'editPhone' => ['nullable', 'string', 'max:50'],
            'editAddress' => ['nullable', 'string', 'max:255'],
            'editConnected' => ['nullable', 'string', 'max:100'],
            'editPossibility' => ['nullable', 'string', Rule::in(['', 'A', 'B', 'C', 'D'])],
            'editLS' => ['nullable', 'integer', 'min:0'],
            'editGSK' => ['nullable', 'integer', 'min:0'],
            'editGSE' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function detailEditMessages(): array
    {
        return [
            'editGubun.required' => '컨설팅타입을 입력해 주세요.',
            'editType.required' => '신규구분을 선택해 주세요.',
            'editAccountName.required' => '기관명을 입력해 주세요.',
            'editLS.integer' => 'LittleSEED는 숫자만 입력해 주세요.',
            'editGSK.integer' => 'GrapeSEED(유)는 숫자만 입력해 주세요.',
            'editGSE.integer' => 'GrapeSEED(초)는 숫자만 입력해 주세요.',
            '*.min' => '숫자는 0 이상이어야 합니다.',
        ];
    }

    protected function resetDetailEditState(): void
    {
        $this->detailEditMode = false;
        $this->resetDetailEditForm();
    }

    protected function resetDetailEditForm(): void
    {
        $this->editAccountManager = '';
        $this->editType = '';
        $this->editGubun = '';
        $this->editAccountName = '';
        $this->editDirector = '';
        $this->editPhone = '';
        $this->editAddress = '';
        $this->editConnected = '';
        $this->editPossibility = '';
        $this->editLS = '';
        $this->editGSK = '';
        $this->editGSE = '';
    }

    protected function hydrateDetailEditFormFromSelectedTarget(): void
    {
        if ($this->selectedTarget === null) {
            return;
        }

        $this->editAccountManager = $this->displayValueToEditString($this->selectedTarget['account_manager'] ?? '');
        $this->editType = $this->displayValueToEditString($this->selectedTarget['type'] ?? '');
        $this->editGubun = $this->displayValueToEditString($this->selectedTarget['gubun'] ?? '');
        $this->editAccountName = $this->displayValueToEditString($this->selectedTarget['account_name'] ?? '');
        $this->editDirector = $this->displayValueToEditString($this->selectedTarget['director'] ?? '');
        $this->editPhone = $this->displayValueToEditString($this->selectedTarget['phone'] ?? '');
        $this->editAddress = $this->displayValueToEditString($this->selectedTarget['address'] ?? '');
        $this->editConnected = $this->displayValueToEditString($this->selectedTarget['connected'] ?? '');
        $this->editPossibility = $this->displayValueToEditString($this->selectedTarget['possibility'] ?? '');
        $this->editLS = (string) ($this->selectedTarget['ls'] ?? 0);
        $this->editGSK = (string) ($this->selectedTarget['gs_k'] ?? 0);
        $this->editGSE = (string) ($this->selectedTarget['gs_e'] ?? 0);
    }

    protected function displayValueToEditString(mixed $value): string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '-' ? '' : $stringValue;
    }

    /**
     * 상세 모달 데이터를 갱신합니다. 각 Livewire 컴포넌트에서 구현합니다.
     */
    abstract protected function reloadDetailModalAfterTargetUpdate(CoNewTarget $target): void;

    /**
     * @param  mixed  $value
     */
    abstract protected function toNonNegativeInt($value): int;
}
