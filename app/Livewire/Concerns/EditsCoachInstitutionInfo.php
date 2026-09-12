<?php

namespace App\Livewire\Concerns;

use App\Actions\UpdateInstitutionDetail;
use App\Models\Employee;
use App\Models\Institution;
use App\Support\InstitutionAccountListQuery;
use App\Support\InstitutionResolver;
use App\Support\SkCodeNormalizer;
use App\Support\TeamMenuContext;
use Illuminate\Support\Facades\Schema;

trait EditsCoachInstitutionInfo
{
    protected const DEPT_CO = 'A02';

    protected const DEPT_TR = 'A05';

    protected const DEPT_CS = 'A03';

    public bool $institutionModalEditMode = false;

    /** @var array<string, mixed>|null */
    public ?array $selectedInstitution = null;

    public string $editDetailInstitutionName = '';

    public string $editDetailAddress = '';

    public string $editDetailCo = '';

    public string $editDetailTr = '';

    public string $editDetailCs = '';

    /** @var list<string> */
    public array $coManagerOptions = [];

    /** @var list<string> */
    public array $trManagerOptions = [];

    /** @var list<string> */
    public array $csManagerOptions = [];

    public function canEditOpenedInstitutionInfo(): bool
    {
        if (! $this->institutionInfo || ! empty($this->institutionInfo['is_terminated'])) {
            return false;
        }

        if ($this->isCrossTeamReadOnlyContext(TeamMenuContext::MENU_COACH)) {
            return false;
        }

        $user = auth()->user();
        if ($user?->hasFullAccess()) {
            return true;
        }

        $skCode = trim((string) ($this->institutionInfo['sk_code'] ?? ''));
        if ($skCode === '') {
            return false;
        }

        return app(InstitutionAccountListQuery::class)->currentUserCanManageInstitution($skCode);
    }

    public function canEditOpenedInstitutionManagers(): bool
    {
        if ($this->canEditOpenedInstitutionInfo()) {
            return true;
        }

        if (! $this->institutionInfo || ! empty($this->institutionInfo['is_terminated'])) {
            return false;
        }

        if ($this->isCrossTeamReadOnlyContext(TeamMenuContext::MENU_COACH)) {
            return false;
        }

        $user = auth()->user();

        return (bool) ($user?->canViewAllInstitutions() && $user->isCoachTeam());
    }

    public function startInstitutionInfoEdit(): void
    {
        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->canEditOpenedInstitutionManagers()) {
            return;
        }

        $this->resetErrorBag();
        $this->editDetailInstitutionName = (string) ($this->institutionInfo['name'] ?? '');
        $this->editDetailAddress = (string) ($this->institutionInfo['address'] ?? '');
        $this->editDetailCo = (string) ($this->institutionInfo['co'] ?? '');
        $this->editDetailTr = (string) ($this->institutionInfo['tr'] ?? '');
        $this->editDetailCs = (string) ($this->institutionInfo['cs'] ?? '');
        $this->coManagerOptions = $this->managerOptionsForDept(self::DEPT_CO);
        $this->trManagerOptions = $this->managerOptionsForDept(self::DEPT_TR);
        $this->csManagerOptions = $this->managerOptionsForDept(self::DEPT_CS);
        $this->institutionModalEditMode = true;
    }

    public function cancelInstitutionInfoEdit(): void
    {
        $this->institutionModalEditMode = false;
        $this->resetErrorBag();
    }

    public function saveInstitutionInfo(UpdateInstitutionDetail $updateInstitutionDetail): void
    {
        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->canEditOpenedInstitutionManagers() || ! $this->institutionInfo) {
            return;
        }

        $skCode = trim((string) ($this->institutionInfo['sk_code'] ?? ''));
        if ($skCode === '') {
            return;
        }

        $institution = InstitutionResolver::resolve(SkCodeNormalizer::candidates($skCode));
        if ($institution === null) {
            return;
        }

        $canEditDetail = $this->canEditOpenedInstitutionInfo();
        $canEditTr = $canEditDetail || $this->canEditOpenedInstitutionManagers();

        if ($canEditDetail) {
            $this->validate([
                'editDetailInstitutionName' => ['required', 'string', 'max:255'],
                'editDetailAddress' => ['nullable', 'string', 'max:500'],
                'editDetailCo' => ['nullable', 'string', 'max:255'],
                'editDetailTr' => ['nullable', 'string', 'max:255'],
                'editDetailCs' => ['nullable', 'string', 'max:255'],
            ], [
                'editDetailInstitutionName.required' => '기관명을 입력해 주세요.',
            ]);
        } else {
            $this->validate([
                'editDetailTr' => ['nullable', 'string', 'max:255'],
            ]);
        }

        $existing = $this->existingInstitutionDetailPayload($institution);
        $accountInfo = $institution->accountInfo;

        $payload = [
            ...$existing,
            'institution_name' => $canEditDetail
                ? trim($this->editDetailInstitutionName)
                : $existing['institution_name'],
            'address' => $canEditDetail
                ? (trim($this->editDetailAddress) !== '' ? trim($this->editDetailAddress) : null)
                : $existing['address'],
            'co' => $canEditDetail
                ? (trim($this->editDetailCo) !== '' ? trim($this->editDetailCo) : null)
                : ($accountInfo?->CO),
            'tr' => $canEditTr
                ? (trim($this->editDetailTr) !== '' ? trim($this->editDetailTr) : null)
                : ($accountInfo?->TR),
            'cs' => $canEditDetail
                ? (trim($this->editDetailCs) !== '' ? trim($this->editDetailCs) : null)
                : ($accountInfo?->CS),
        ];

        $updateInstitutionDetail->execute($institution, $payload);

        session()->flash('success', '기관 정보가 저장되었습니다.');
        $this->closeInstitutionModal();
    }

    /**
     * @return array{
     *     sk_code: string,
     *     institution_name: string,
     *     english_name: ?string,
     *     portal_name: ?string,
     *     portal_campus_id: ?string,
     *     account_no: ?string,
     *     gubun: ?string,
     *     director: ?string,
     *     phone: ?string,
     *     account_tel: ?string,
     *     address: ?string,
     *     customer_type: ?string,
     *     gs_no: ?string,
     *     co: ?string,
     *     tr: ?string,
     *     cs: ?string
     * }
     */
    private function existingInstitutionDetailPayload(Institution $institution): array
    {
        $accountInfo = $institution->accountInfo;
        $address = trim((string) ($institution->Address ?? ''));
        if ($address === '') {
            $address = trim((string) ($accountInfo?->Address ?? ''));
        }

        return [
            'sk_code' => (string) $institution->SKcode,
            'institution_name' => $institution->resolvedAccountName(),
            'english_name' => filled($institution->EnglishName ?? null) ? (string) $institution->EnglishName : null,
            'portal_name' => filled($institution->PortalAccountName ?? null) ? (string) $institution->PortalAccountName : null,
            'portal_campus_id' => filled($institution->PortalCampusID ?? null) ? (string) $institution->PortalCampusID : null,
            'account_no' => filled($institution->AccountNo ?? null) ? (string) $institution->AccountNo : null,
            'gubun' => filled($institution->Gubun ?? null) ? (string) $institution->Gubun : null,
            'director' => filled($institution->Director ?? null) ? (string) $institution->Director : null,
            'phone' => filled($institution->Phone ?? null) ? (string) $institution->Phone : null,
            'account_tel' => filled($institution->AccountTel ?? null) ? (string) $institution->AccountTel : null,
            'address' => $address !== '' ? $address : null,
            'customer_type' => filled($accountInfo?->Customer_Type) ? (string) $accountInfo->Customer_Type : null,
            'gs_no' => $institution->resolvedGsNumber() !== '' ? $institution->resolvedGsNumber() : null,
            'co' => filled($accountInfo?->CO) ? (string) $accountInfo->CO : null,
            'tr' => filled($accountInfo?->TR) ? (string) $accountInfo->TR : null,
            'cs' => filled($accountInfo?->CS) ? (string) $accountInfo->CS : null,
        ];
    }

    /**
     * @return list<string>
     */
    private function managerOptionsForDept(string $deptNo): array
    {
        if (! Schema::hasTable('employee')) {
            return [];
        }

        return Employee::query()
            ->where('WORKDEPT', $deptNo)
            ->where('STATUS', 1)
            ->get(['ENGLISHNAME', 'KOREANAME'])
            ->map(function (Employee $employee): string {
                $english = trim((string) ($employee->ENGLISHNAME ?? ''));
                if ($english !== '') {
                    return $english;
                }

                return trim((string) ($employee->KOREANAME ?? ''));
            })
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function resetInstitutionInfoEditState(): void
    {
        $this->institutionModalEditMode = false;
        $this->selectedInstitution = null;
        $this->editDetailInstitutionName = '';
        $this->editDetailAddress = '';
        $this->editDetailCo = '';
        $this->editDetailTr = '';
        $this->editDetailCs = '';
        $this->coManagerOptions = [];
        $this->trManagerOptions = [];
        $this->csManagerOptions = [];
        $this->resetErrorBag();
    }
}
