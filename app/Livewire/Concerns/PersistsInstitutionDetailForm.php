<?php

namespace App\Livewire\Concerns;

use App\Actions\UpdateInstitutionDetail;
use App\Models\Institution;
use Illuminate\Validation\Rule;

trait PersistsInstitutionDetailForm
{
    /**
     * @return array{institution_id: int, sk_code: string}|null
     */
    public function persistInstitutionDetailFields(UpdateInstitutionDetail $updateInstitutionDetail): ?array
    {
        if (! $this->selectedInstitution) {
            return null;
        }

        if (! $this->canEditInstitutionDetail()) {
            $this->addError('detailEdit', '기관 상세 정보를 수정할 권한이 없습니다.');

            return null;
        }

        $originalSk = trim((string) ($this->selectedInstitution['skcode'] ?? ''));

        if ($originalSk === '') {
            $this->addError('detailEdit', '기관 코드가 없어 저장할 수 없습니다.');

            return null;
        }

        $institution = $this->resolveInstitutionMasterForDetailSave($originalSk);

        $this->applyInstitutionDetailEditFieldLocks();

        // SK 코드 중복 검사는 "SK를 실제로 변경할 때"만 적용한다.
        // 레거시 데이터에는 동일 SK 행이 2개 이상 존재하는 기관이 있어(예: 중복 마이그레이션),
        // SK를 그대로 두고 다른 필드만 수정하는데도 unique 규칙이 자기 자신 외의 중복 행과
        // 충돌해 "이미 사용 중인 SK 코드입니다."로 저장이 막히는 문제가 있었다.
        $skCodeRules = ['required', 'string', 'max:100'];
        if (trim($this->editDetailSkCode) !== $originalSk) {
            $uniqueRule = Rule::unique('S_AccountName', 'SKcode');
            if ($institution !== null) {
                $uniqueRule = $uniqueRule->ignore((int) $institution->ID, 'ID');
            }
            $skCodeRules[] = $uniqueRule;
        }

        $this->validate([
            'editDetailSkCode' => $skCodeRules,
            'editDetailInstitutionName' => ['required', 'string', 'max:255'],
            'editDetailEnglishName' => ['nullable', 'string', 'max:255'],
            'editDetailPortalName' => ['nullable', 'string', 'max:255'],
            'editDetailPortalCampusId' => ['nullable', 'string', 'max:100'],
            'editDetailAccountNo' => ['nullable', 'string', 'max:100'],
            'editDetailGubun' => ['nullable', 'string', 'max:100'],
            'editDetailDirector' => ['nullable', 'string', 'max:255'],
            'editDetailPhone' => ['nullable', 'string', 'max:100'],
            'editDetailAccountTel' => ['nullable', 'string', 'max:100'],
            'editDetailAddress' => ['nullable', 'string', 'max:500'],
            'editCustomerType' => ['nullable', 'string', 'max:255'],
            'editGsNo' => ['nullable', 'string', 'max:255'],
            'editDetailCo' => ['nullable', 'string', 'max:255'],
            'editDetailTr' => ['nullable', 'string', 'max:255'],
            'editDetailCs' => ['nullable', 'string', 'max:255'],
        ], [
            'editDetailSkCode.required' => 'SK 코드를 입력해 주세요.',
            'editDetailSkCode.unique' => '이미 사용 중인 SK 코드입니다.',
            'editDetailInstitutionName.required' => '기관명을 입력해 주세요.',
        ]);

        // info만 있고 마스터가 없는 레거시 기관: 검증 통과 후에야 마스터를 만든다.
        if ($institution === null) {
            $accountName = trim($this->editDetailInstitutionName);
            $institution = Institution::query()->firstOrCreate(
                ['SKcode' => $originalSk],
                ['AccountName' => $accountName !== '' ? $accountName : $originalSk],
            );
        }

        $institutionId = (int) $institution->ID;

        $updateInstitutionDetail->execute($institution, [
            'sk_code' => trim($this->editDetailSkCode),
            'institution_name' => trim($this->editDetailInstitutionName),
            'english_name' => trim($this->editDetailEnglishName) !== '' ? trim($this->editDetailEnglishName) : null,
            'portal_name' => trim($this->editDetailPortalName) !== '' ? trim($this->editDetailPortalName) : null,
            'portal_campus_id' => trim($this->editDetailPortalCampusId) !== '' ? trim($this->editDetailPortalCampusId) : null,
            'account_no' => trim($this->editDetailAccountNo) !== '' ? trim($this->editDetailAccountNo) : null,
            'gubun' => trim($this->editDetailGubun) !== '' ? trim($this->editDetailGubun) : null,
            'director' => trim($this->editDetailDirector) !== '' ? trim($this->editDetailDirector) : null,
            'phone' => trim($this->editDetailPhone) !== '' ? trim($this->editDetailPhone) : null,
            'account_tel' => trim($this->editDetailAccountTel) !== '' ? trim($this->editDetailAccountTel) : null,
            'address' => trim($this->editDetailAddress) !== '' ? trim($this->editDetailAddress) : null,
            'customer_type' => trim($this->editCustomerType) ?: null,
            'gs_no' => trim($this->editGsNo) !== '' ? trim($this->editGsNo) : null,
            'co' => trim($this->editDetailCo) ?: null,
            'tr' => trim($this->editDetailTr) ?: null,
            'cs' => trim($this->editDetailCs) ?: null,
        ]);

        $newSk = trim($this->editDetailSkCode);
        $this->isEditingDetail = false;
        $this->resetValidation();
        session()->flash('success', '기관 상세 정보가 저장되었습니다.');

        $this->dispatch('institution-form-detail-edit-state', isEditing: false);

        $catalogId = (int) ($this->selectedInstitution['id'] ?? 0);

        return [
            'institution_id' => $catalogId > 0 ? $catalogId : $institutionId,
            'sk_code' => $newSk,
        ];
    }

    protected function applyInstitutionDetailEditFieldLocks(): void
    {
        if (! $this->selectedInstitution) {
            return;
        }

        if (! $this->canEditInstitutionDetailSkCode()) {
            $this->editDetailSkCode = (string) ($this->selectedInstitution['skcode'] ?? '');
        }

        if ($this->canEditInstitutionDetailCore() || $this->canEditAssignedInstitutionDetailFields()) {
            return;
        }

        $this->editDetailInstitutionName = (string) ($this->selectedInstitution['name'] ?? '');
        $this->editDetailEnglishName = (string) ($this->selectedInstitution['english_name'] ?? '');
        $this->editDetailPortalName = (string) ($this->selectedInstitution['portal_name'] ?? '');
        $this->editDetailPortalCampusId = (string) ($this->selectedInstitution['portal_campus_id'] ?? '');
        $this->editDetailAccountNo = (string) ($this->selectedInstitution['account_no'] ?? '');
        $this->editDetailGubun = (string) ($this->selectedInstitution['gubun'] ?? '');
        $this->editDetailDirector = (string) ($this->selectedInstitution['director'] ?? '');
        $this->editDetailPhone = (string) ($this->selectedInstitution['phone'] ?? '');
        $this->editDetailAccountTel = (string) ($this->selectedInstitution['account_tel'] ?? '');
        $this->editDetailAddress = (string) ($this->selectedInstitution['address'] ?? '');
        $this->editCustomerType = (string) ($this->selectedInstitution['customer_type'] ?? '');
        $this->editGsNo = (string) ($this->selectedInstitution['gs_no'] ?? '');
        $this->editDetailCo = (string) ($this->selectedInstitution['co'] ?? '');
        $this->editDetailTr = (string) ($this->selectedInstitution['tr'] ?? '');
        $this->editDetailCs = (string) ($this->selectedInstitution['cs'] ?? '');
    }

    private function resolveInstitutionMasterForDetailSave(string $originalSk): ?Institution
    {
        $masterId = (int) ($this->selectedInstitution['master_id'] ?? 0);

        if ($masterId > 0) {
            $institution = Institution::query()->find($masterId);
            if ($institution !== null) {
                return $institution;
            }
        }

        return Institution::query()->where('SKcode', $originalSk)->first();
    }
}
