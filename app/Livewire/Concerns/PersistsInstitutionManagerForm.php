<?php

namespace App\Livewire\Concerns;

use App\Actions\UpdateInstitutionManagers;
use App\Models\AccountInformation;

trait PersistsInstitutionManagerForm
{
    public function persistInstitutionManagers(UpdateInstitutionManagers $updateInstitutionManagers): void
    {
        if (! $this->canEditInstitutionDetail()) {
            $this->addError('managerEdit', '담당자 정보를 수정할 권한이 없습니다.');

            return;
        }

        $existing = AccountInformation::query()
            ->where('SK_Code', $this->editSkCode)
            ->first();

        $co = $this->canEditInstitutionDetailCo()
            ? trim($this->editCo) ?: null
            : $existing?->CO;
        $tr = $this->canEditInstitutionDetailTr()
            ? trim($this->editTr) ?: null
            : $existing?->TR;
        $cs = $this->canEditInstitutionDetailCs()
            ? trim($this->editCs) ?: null
            : $existing?->CS;

        $this->validate([
            'editSkCode' => 'required',
            'editInstitutionName' => 'required|string|max:255',
            'editCo' => 'nullable|string|max:255',
            'editTr' => 'nullable|string|max:255',
            'editCs' => 'nullable|string|max:255',
        ], [
            'editSkCode.required' => '기관 코드가 필요합니다.',
            'editInstitutionName.required' => '기관명이 필요합니다.',
        ]);

        $updateInstitutionManagers->execute([
            'sk_code' => $this->editSkCode,
            'institution_name' => trim($this->editInstitutionName),
            'co' => $co,
            'tr' => $tr,
            'cs' => $cs,
        ]);

        $institutionId = (int) ($this->editingInstitutionId ?? 0);
        $skCode = $this->editSkCode;

        session()->flash('success', '담당자 정보가 저장되었습니다.');

        $this->dispatch(
            'institution-saved',
            mode: 'manager',
            institutionId: $institutionId,
            skCode: $skCode,
        );
    }
}
