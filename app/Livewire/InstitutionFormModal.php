<?php

namespace App\Livewire;

use App\Actions\UpdateInstitutionDetail;
use App\Actions\UpdateInstitutionManagers;
use App\Livewire\Concerns\PersistsInstitutionDetailForm;
use App\Livewire\Concerns\PersistsInstitutionManagerForm;
use App\Livewire\Concerns\ResolvesInstitutionFormPermissions;
use Livewire\Attributes\On;
use Livewire\Component;

class InstitutionFormModal extends Component
{
    use PersistsInstitutionDetailForm;
    use PersistsInstitutionManagerForm;
    use ResolvesInstitutionFormPermissions;

    /** `manager`: 담당자 변경 모달, `detail`: 상세 모달 내 편집 폼 */
    public string $embedMode = 'manager';

    public bool $showManagerModal = false;

    public bool $isEditingDetail = false;

    public ?int $editingInstitutionId = null;

    public string $editSkCode = '';

    public string $editInstitutionName = '';

    public string $editCo = '';

    public string $editTr = '';

    public string $editCs = '';

    public string $editCustomerType = '';

    public string $editGsNo = '';

    public string $editDetailCo = '';

    public string $editDetailTr = '';

    public string $editDetailCs = '';

    public string $editDetailSkCode = '';

    public string $editDetailInstitutionName = '';

    public string $editDetailEnglishName = '';

    public string $editDetailPortalName = '';

    public string $editDetailPortalCampusId = '';

    public string $editDetailAccountNo = '';

    public string $editDetailGubun = '';

    public string $editDetailDirector = '';

    public string $editDetailPhone = '';

    public string $editDetailAccountTel = '';

    public string $editDetailAddress = '';

    /** @var array<string, mixed>|null */
    public ?array $selectedInstitution = null;

    /** @var list<string> */
    public array $coManagerOptions = [];

    /** @var list<string> */
    public array $trManagerOptions = [];

    /** @var list<string> */
    public array $csManagerOptions = [];

    /** @var list<string> */
    public array $gubunList = [];

    /** @var list<string> */
    public array $customerTypeOptions = [];

    public function mount(
        string $embedMode = 'manager',
        array $coManagerOptions = [],
        array $trManagerOptions = [],
        array $csManagerOptions = [],
        array $gubunList = [],
        array $customerTypeOptions = [],
    ): void {
        $this->embedMode = $embedMode;
        $this->coManagerOptions = $coManagerOptions;
        $this->trManagerOptions = $trManagerOptions;
        $this->csManagerOptions = $csManagerOptions;
        $this->gubunList = $gubunList;
        $this->customerTypeOptions = $customerTypeOptions;
    }

    #[On('institution-form-open-manager')]
    public function openManagerModal(
        int $institutionId,
        string $skCode,
        string $institutionName,
        string $co = '',
        string $tr = '',
        string $cs = '',
    ): void {
        if ($this->embedMode !== 'manager') {
            return;
        }

        $this->editingInstitutionId = $institutionId;
        $this->editSkCode = $skCode;
        $this->editInstitutionName = $institutionName;
        $this->editCo = $co;
        $this->editTr = $tr;
        $this->editCs = $cs;

        if (! $this->canEditInstitutionManagers()) {
            $this->showManagerModal = false;

            return;
        }

        $this->showManagerModal = true;
        $this->resetValidation();
    }

    #[On('institution-form-close-manager')]
    public function closeManagerModal(): void
    {
        $this->showManagerModal = false;
        $this->editingInstitutionId = null;
        $this->editSkCode = '';
        $this->editInstitutionName = '';
        $this->editCo = '';
        $this->editTr = '';
        $this->editCs = '';
        $this->resetValidation();
    }

    /**
     * @param  array<string, mixed>  $institution
     */
    #[On('institution-form-start-detail-edit')]
    public function startDetailEdit(array $institution): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }
        $this->selectedInstitution = $institution;
        $this->isEditingDetail = true;
        $this->editCustomerType = (string) ($institution['customer_type'] ?? '');
        $this->editGsNo = (string) ($institution['gs_no'] ?? '');
        $this->editDetailCo = (string) ($institution['co'] ?? '');
        $this->editDetailTr = (string) ($institution['tr'] ?? '');
        $this->editDetailCs = (string) ($institution['cs'] ?? '');
        $this->editDetailSkCode = (string) ($institution['skcode'] ?? '');
        $this->editDetailInstitutionName = (string) ($institution['name'] ?? '');
        $this->editDetailEnglishName = (string) ($institution['english_name'] ?? '');
        $this->editDetailPortalName = (string) ($institution['portal_name'] ?? '');
        $this->editDetailPortalCampusId = (string) ($institution['portal_campus_id'] ?? '');
        $this->editDetailAccountNo = (string) ($institution['account_no'] ?? '');
        $this->editDetailGubun = (string) ($institution['gubun'] ?? '');
        $this->editDetailDirector = (string) ($institution['director'] ?? '');
        $this->editDetailPhone = (string) ($institution['phone'] ?? '');
        $this->editDetailAccountTel = (string) ($institution['account_tel'] ?? '');
        $this->editDetailAddress = (string) ($institution['address'] ?? '');
        $this->resetValidation();
        $this->dispatch('institution-form-detail-edit-state', isEditing: true);
    }

    #[On('institution-form-cancel-detail-edit')]
    public function cancelDetailEdit(): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }
        if (! $this->selectedInstitution) {
            return;
        }

        $institution = $this->selectedInstitution;
        $this->isEditingDetail = false;
        $this->editCustomerType = (string) ($institution['customer_type'] ?? '');
        $this->editGsNo = (string) ($institution['gs_no'] ?? '');
        $this->editDetailCo = (string) ($institution['co'] ?? '');
        $this->editDetailTr = (string) ($institution['tr'] ?? '');
        $this->editDetailCs = (string) ($institution['cs'] ?? '');
        $this->editDetailSkCode = (string) ($institution['skcode'] ?? '');
        $this->editDetailInstitutionName = (string) ($institution['name'] ?? '');
        $this->editDetailEnglishName = (string) ($institution['english_name'] ?? '');
        $this->editDetailPortalName = (string) ($institution['portal_name'] ?? '');
        $this->editDetailPortalCampusId = (string) ($institution['portal_campus_id'] ?? '');
        $this->editDetailAccountNo = (string) ($institution['account_no'] ?? '');
        $this->editDetailGubun = (string) ($institution['gubun'] ?? '');
        $this->editDetailDirector = (string) ($institution['director'] ?? '');
        $this->editDetailPhone = (string) ($institution['phone'] ?? '');
        $this->editDetailAccountTel = (string) ($institution['account_tel'] ?? '');
        $this->editDetailAddress = (string) ($institution['address'] ?? '');
        $this->resetValidation();
        $this->dispatch('institution-form-detail-edit-state', isEditing: false);
    }

    #[On('institution-form-save-detail')]
    public function handleSaveDetailRequest(UpdateInstitutionDetail $updateInstitutionDetail): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }

        $this->saveDetailFields($updateInstitutionDetail);
    }

    #[On('institution-form-reset-detail')]
    public function resetDetailForm(): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }
        $this->isEditingDetail = false;
        $this->selectedInstitution = null;
        $this->resetDetailFieldState();
        $this->resetValidation();
        $this->dispatch('institution-form-detail-edit-state', isEditing: false);
    }

    /**
     * @param  array<string, mixed>  $institution
     */
    #[On('institution-form-set-detail-context')]
    public function setDetailContext(array $institution): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }
        $this->selectedInstitution = $institution;
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    #[On('institution-form-sync-detail')]
    public function syncDetailFromParent(array $fields): void
    {
        if ($this->embedMode !== 'detail') {
            return;
        }

        foreach ($fields as $property => $value) {
            if (property_exists($this, $property)) {
                $this->{$property} = (string) ($value ?? '');
            }
        }
    }

    #[On('institution-form-sync-manager')]
    public function syncManagerFromParent(
        string $co = '',
        string $tr = '',
        string $cs = '',
        string $institutionName = '',
    ): void {
        if ($this->embedMode !== 'manager') {
            return;
        }

        if ($institutionName !== '') {
            $this->editInstitutionName = $institutionName;
        }
        $this->editCo = $co;
        $this->editTr = $tr;
        $this->editCs = $cs;
    }

    #[On('institution-form-save-manager-request')]
    public function handleSaveManagerRequest(UpdateInstitutionManagers $updateInstitutionManagers): void
    {
        if ($this->embedMode !== 'manager') {
            return;
        }

        $this->saveManagers($updateInstitutionManagers);
    }

    public function saveManagers(UpdateInstitutionManagers $updateInstitutionManagers): void
    {
        $this->persistInstitutionManagers($updateInstitutionManagers);

        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->closeManagerModal();
    }

    public function saveDetailFields(UpdateInstitutionDetail $updateInstitutionDetail): void
    {
        $result = $this->persistInstitutionDetailFields($updateInstitutionDetail);

        if ($result === null) {
            return;
        }

        $this->dispatch(
            'institution-saved',
            mode: 'detail',
            institutionId: $result['institution_id'],
            skCode: $result['sk_code'],
        );
    }

    public function render()
    {
        return view('livewire.institution-form-modal');
    }

    private function resetDetailFieldState(): void
    {
        $this->editCustomerType = '';
        $this->editGsNo = '';
        $this->editDetailCo = '';
        $this->editDetailTr = '';
        $this->editDetailCs = '';
        $this->editDetailSkCode = '';
        $this->editDetailInstitutionName = '';
        $this->editDetailEnglishName = '';
        $this->editDetailPortalName = '';
        $this->editDetailPortalCampusId = '';
        $this->editDetailAccountNo = '';
        $this->editDetailGubun = '';
        $this->editDetailDirector = '';
        $this->editDetailPhone = '';
        $this->editDetailAccountTel = '';
        $this->editDetailAddress = '';
    }
}
