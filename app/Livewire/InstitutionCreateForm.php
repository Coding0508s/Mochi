<?php

namespace App\Livewire;

use App\Actions\CreateInstitution;
use App\Models\AccountInformation;
use App\Models\Institution;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class InstitutionCreateForm extends Component
{
    public string $newSkCode = '';

    public string $newInstitutionName = '';

    public string $newGubun = '';

    public string $newDirector = '';

    public string $newPhone = '';

    public string $newAccountTel = '';

    public string $newAddress = '';

    public string $newCustomerType = '';

    public string $newGsNo = '';

    public string $newCo = '';

    public string $newTr = '';

    public string $newCs = '';

    public string $newPossibility = '';

    public function save(CreateInstitution $createInstitution): mixed
    {
        if (! config('features.institution_create_enabled')) {
            return $this->redirect(route('institutions.index'));
        }

        $this->validate([
            'newSkCode' => 'required|string|max:255|unique:S_AccountName,SKcode',
            'newInstitutionName' => 'required|string|max:255',
            'newGubun' => 'nullable|string|max:255',
            'newDirector' => 'nullable|string|max:255',
            'newPhone' => 'nullable|string|max:255',
            'newAccountTel' => 'nullable|string|max:255',
            'newAddress' => 'nullable|string|max:255',
            'newCustomerType' => 'nullable|string|max:255',
            'newGsNo' => 'nullable|string|max:255',
            'newCo' => 'nullable|string|max:255',
            'newTr' => 'nullable|string|max:255',
            'newCs' => 'nullable|string|max:255',
            'newPossibility' => 'nullable|string|in:A,B,C,D',
        ], [
            'newSkCode.required' => 'SK코드를 입력해 주세요.',
            'newSkCode.unique' => '이미 사용 중인 SK코드입니다.',
            'newInstitutionName.required' => '기관명을 입력해 주세요.',
        ]);

        $createInstitution->execute([
            'sk_code' => $this->newSkCode,
            'institution_name' => $this->newInstitutionName,
            'gubun' => $this->newGubun,
            'director' => $this->newDirector,
            'phone' => $this->newPhone,
            'account_tel' => $this->newAccountTel,
            'address' => $this->newAddress,
            'customer_type' => $this->newCustomerType,
            'gs_no' => $this->newGsNo,
            'co' => $this->newCo,
            'tr' => $this->newTr,
            'cs' => $this->newCs,
            'possibility' => $this->newPossibility,
        ]);

        session()->flash('success', '신규 기관이 등록되었습니다.');

        return $this->redirect(route('institutions.index'));
    }

    public function render(): View
    {
        $gubunList = Institution::query()
            ->whereNotNull('Gubun')
            ->where('Gubun', '!=', '')
            ->distinct()
            ->pluck('Gubun');

        $coManagerOptions = AccountInformation::query()
            ->whereNotNull('CO')
            ->where('CO', '!=', '')
            ->distinct()
            ->orderBy('CO')
            ->pluck('CO');

        $trManagerOptions = AccountInformation::query()
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->distinct()
            ->orderBy('TR')
            ->pluck('TR');

        $csManagerOptions = AccountInformation::query()
            ->whereNotNull('CS')
            ->where('CS', '!=', '')
            ->distinct()
            ->orderBy('CS')
            ->pluck('CS');

        return view('livewire.institution-create-form', [
            'gubunList' => $gubunList,
            'coManagerOptions' => $coManagerOptions,
            'trManagerOptions' => $trManagerOptions,
            'csManagerOptions' => $csManagerOptions,
        ]);
    }
}
