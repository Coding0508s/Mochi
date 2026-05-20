<?php

namespace App\Livewire;

use App\Models\Institution;
use App\Models\Teacher;
use App\Support\SkCodeNormalizer;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class ContactList extends Component
{
    use WithPagination;

    // ─── 검색 관련 상태 ────────────────────────────────────────────
    public string $searchType = 'name';
    // 라디오 버튼으로 선택한 검색 기준
    // 'name' | 'email' | 'school' | 'phone'

    public string $search = '';

    // 검색창에 입력한 텍스트
    public string $employmentFilter = 'all';
    // 재직 상태 필터: all | active | inactive

    // ─── 생성/수정 모달 상태 ────────────────────────────────────────
    public bool $showModal = false;

    public ?int $editingId = null; // null: 신규, 숫자: 수정

    // ─── 삭제 확인 모달 상태 ────────────────────────────────────────
    public bool $showDeleteModal = false;

    public ?int $deleteTargetId = null;

    public string $deleteTargetName = '';

    // ─── 상세 보기 모달 상태 ────────────────────────────────────────
    public bool $showDetailModal = false;

    public ?array $selectedContact = null;

    // 모달 내 입력 필드들
    public string $newName = '';

    public string $newPhone = '';

    public string $newEmail = '';

    public string $originalEmail = '';

    public string $newPosition = '';  // 직급

    public string $newEmploymentStatus = 'active'; // 계정상태: active|inactive

    public string $newClassParticipation = 'in';   // 수업참여: in|out

    public string $newSkCode = '';  // 선택한 기관의 SKcode

    public string $newSchoolName = '';  // 선택한 기관명

    /** 기관 검색(모달): 기관명 또는 SK 코드 입력 */
    public string $newInstitutionKeyword = '';

    public string $newDescription = '';  // 비고

    /** GrapeSEED / LittleSEED Essentials 일자 (Y-m-d, 비우면 NULL) */
    public string $newGrapeSeedEssentials = '';

    public string $newLittleSeedEssentials = '';

    protected array $messages = [
        'newName.required' => '이름을 입력해 주세요.',
        'newEmail.required' => '이메일을 입력해 주세요.',
        'newEmail.email' => '올바른 이메일 형식이 아닙니다.',
        'newEmail.unique' => '이미 등록된 이메일입니다.',
        'newSkCode.required' => '기관을 검색하여 선택해 주세요.',
    ];

    // ─── 검색어가 바뀌면 1페이지로 초기화 ────────────────────────
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSearchType(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function updatingEmploymentFilter(): void
    {
        $this->resetPage();
    }

    // ─── 모달 열기 / 닫기 ─────────────────────────────────────────
    public function openCreateModal(): void
    {
        $this->resetModal();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $teacher = Teacher::findOrFail($id);

        $this->editingId = $teacher->ID;
        $this->newName = (string) ($teacher->Name ?? '');
        $this->newPhone = (string) ($teacher->Phone ?? '');
        $this->newEmail = (string) ($teacher->Email ?? '');
        $this->originalEmail = (string) ($teacher->Email ?? '');
        $this->newPosition = (string) ($teacher->Position ?? '');
        $this->newEmploymentStatus = $this->normalizeStatusForForm($teacher->Status);
        $this->newClassParticipation = (bool) ($teacher->ClassInOut ?? false) ? 'in' : 'out';
        $this->newSkCode = (string) ($teacher->SK_Code ?? '');
        $this->newSchoolName = (string) ($teacher->School_Name ?? '');
        $this->newDescription = (string) ($teacher->Description ?? '');
        $this->newGrapeSeedEssentials = $teacher->GrapeSEEDEssentials?->format('Y-m-d') ?? '';
        $this->newLittleSeedEssentials = $teacher->LittleSEEDEssentials?->format('Y-m-d') ?? '';
        $this->showModal = true;
    }

    // 기존 호출 호환용
    public function openModal(): void
    {
        $this->openCreateModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetModal();
    }

    public function openDetailModal(int $id): void
    {
        $teacher = Teacher::query()
            ->with('institution.accountInfo')
            ->findOrFail($id);

        $institution = $teacher->institution;

        // 일부 데이터는 SK_Code 앞에 '*'가 붙어 관계 매칭이 실패할 수 있어 보정 조회를 수행합니다.
        if (! $institution) {
            $normalizedSkCode = SkCodeNormalizer::normalize($teacher->SK_Code);
            if ($normalizedSkCode) {
                $institution = Institution::query()
                    ->with('accountInfo')
                    ->where('SKcode', $normalizedSkCode)
                    ->first();
            }
        }

        $this->selectedContact = [
            'id' => $teacher->ID,
            'name' => $teacher->Name,
            'email' => $teacher->Email,
            'phone' => $teacher->Phone,
            'position' => $teacher->Position,
            'status' => (bool) ($teacher->ClassInOut ?? false) ? '재직' : '퇴직',
            'status_text' => $teacher->Status,
            'description' => $teacher->Description,
            'sk_code' => $teacher->SK_Code,
            'school_name' => $teacher->School_Name,
            'co_name' => $teacher->CO_Name,
            'co' => $teacher->CO ?: $institution?->accountInfo?->CO,
            'cs' => $teacher->CS ?: $institution?->accountInfo?->CS,
            'tr' => $institution?->accountInfo?->TR,
            'institution_address' => $institution?->Address,
            'grape_seed_essentials' => $this->formatDate($teacher->GrapeSEEDEssentials),
            'little_seed_essentials' => $this->formatDate($teacher->LittleSEEDEssentials),
            'created_date' => $this->formatDate($teacher->Created_Date),
        ];

        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->selectedContact = null;
    }

    /** 상세(읽기) 모달에서 수정 폼으로 전환 */
    public function openEditFromDetail(): void
    {
        if ($this->selectedContact === null || ! isset($this->selectedContact['id'])) {
            return;
        }

        $id = (int) $this->selectedContact['id'];
        $this->closeDetailModal();
        $this->openEditModal($id);
    }

    private function resetModal(): void
    {
        $this->newName = '';
        $this->newPhone = '';
        $this->newEmail = '';
        $this->originalEmail = '';
        $this->newPosition = '';
        $this->newEmploymentStatus = 'active';
        $this->newClassParticipation = 'in';
        $this->newSkCode = '';
        $this->newSchoolName = '';
        $this->newInstitutionKeyword = '';
        $this->newDescription = '';
        $this->newGrapeSeedEssentials = '';
        $this->newLittleSeedEssentials = '';
        $this->editingId = null;
        $this->resetValidation();
    }

    public function updatedNewInstitutionKeyword(string $value): void
    {
        if (filled($this->newSkCode)) {
            return;
        }

        $keyword = trim($value);
        if ($keyword === '') {
            $this->newSchoolName = '';

            return;
        }

        $inst = Institution::query()
            ->whereNotNull('SKcode')
            ->where(function ($q) use ($keyword): void {
                $q->where('AccountName', $keyword)
                    ->orWhere('SKcode', $keyword);
            })
            ->first();

        if ($inst) {
            $this->newSkCode = (string) $inst->SKcode;
            $this->newSchoolName = (string) ($inst->AccountName ?? '');
            $this->newInstitutionKeyword = '';
            $this->resetErrorBag('newSkCode');
        }
    }

    public function selectTeacherInstitution(string $skCode): void
    {
        $trimmed = trim($skCode);
        if ($trimmed === '') {
            return;
        }

        $inst = Institution::query()->where('SKcode', $trimmed)->first();
        if (! $inst) {
            return;
        }

        $this->newSkCode = (string) $inst->SKcode;
        $this->newSchoolName = (string) ($inst->AccountName ?? '');
        $this->newInstitutionKeyword = '';
        $this->resetErrorBag('newSkCode');
    }

    public function clearTeacherInstitutionSelection(): void
    {
        $this->newSkCode = '';
        $this->newSchoolName = '';
        $this->newInstitutionKeyword = '';
    }

    // ─── 신규 교사 저장 ───────────────────────────────────────────
    public function save(): void
    {
        // 사용자가 앞/뒤 공백을 넣어도 동일 이메일로 처리되도록 정리합니다.
        $this->newEmail = trim($this->newEmail);

        $normalizedNewEmail = mb_strtolower($this->newEmail);
        $normalizedOriginalEmail = mb_strtolower(trim($this->originalEmail));

        // 기본 검증
        $emailRules = ['required', 'email', 'max:190'];

        // 수정 시 "이메일이 실제로 바뀐 경우"에만 유니크 검사를 적용합니다.
        // (기존 데이터에 중복 이메일이 있어도, 이메일 유지 수정은 가능해야 합니다.)
        $isEmailChanged = ! $this->editingId || ($normalizedNewEmail !== $normalizedOriginalEmail);
        if ($isEmailChanged) {
            $emailUniqueRule = Rule::unique('Teachers', 'Email');
            if ($this->editingId) {
                $emailUniqueRule->ignore($this->editingId, 'ID');
            }
            $emailRules[] = $emailUniqueRule;
        }

        $this->validate([
            'newName' => 'required|string|max:190',
            'newEmail' => $emailRules,
            'newPhone' => 'nullable|string|max:190',
            'newSkCode' => 'required',
            'newEmploymentStatus' => 'required|in:active,inactive',
            'newClassParticipation' => 'required|in:in,out',
            'newGrapeSeedEssentials' => ['nullable', 'date'],
            'newLittleSeedEssentials' => ['nullable', 'date'],
        ], $this->messages);

        $isActive = $this->newEmploymentStatus === 'active';
        $isClassIn = $this->newClassParticipation === 'in';

        $grapeDate = trim($this->newGrapeSeedEssentials);
        $littleDate = trim($this->newLittleSeedEssentials);

        $data = [
            'Name' => $this->newName,
            'Phone' => $this->newPhone,
            'Email' => $this->newEmail,
            'Position' => $this->newPosition,
            'SK_Code' => $this->newSkCode,
            'School_Name' => $this->newSchoolName,
            'Description' => $this->newDescription,
            'Status' => $isActive ? '활성화' : '비활성화',
            'ClassInOut' => $isClassIn,
            'GrapeSEEDEssentials' => $grapeDate === '' ? null : $grapeDate,
            'LittleSEEDEssentials' => $littleDate === '' ? null : $littleDate,
        ];

        if ($this->editingId) {
            Teacher::where('ID', $this->editingId)->update($data);
            session()->flash('success', '연락처 정보가 수정되었습니다.');
        } else {
            $data['Created_Date'] = now();
            Teacher::create($data);
            session()->flash('success', '새 연락처가 등록되었습니다.');
        }

        $this->closeModal();
    }

    public function retire(): void
    {
        if (! $this->editingId) {
            return;
        }

        $this->newEmploymentStatus = 'inactive';
        $this->newClassParticipation = 'out';
        $this->save();
    }

    public function confirmDelete(int $id): void
    {
        $teacher = Teacher::findOrFail($id);
        $this->deleteTargetId = $teacher->ID;
        $this->deleteTargetName = (string) ($teacher->Name ?? '연락처');
        $this->showDeleteModal = true;
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deleteTargetId = null;
        $this->deleteTargetName = '';
    }

    public function delete(): void
    {
        if (! $this->deleteTargetId) {
            return;
        }

        Teacher::where('ID', $this->deleteTargetId)->delete();
        $this->closeDeleteModal();
        session()->flash('success', '연락처가 삭제되었습니다.');
        $this->resetPage();
    }

    // ─── 화면 렌더링 ──────────────────────────────────────────────
    public function render()
    {
        $teachers = Teacher::query()
            ->searchBy($this->searchType, $this->search)
            ->when($this->employmentFilter === 'active', function ($query) {
                $query->where('ClassInOut', true);
            })
            ->when($this->employmentFilter === 'inactive', function ($query) {
                $query->where('ClassInOut', false);
            })
            ->with('institution.accountInfo')
            ->orderBy('ID', 'desc')
            ->paginate(20);

        $teacherRows = $teachers->getCollection();
        $normalizedSkCodes = $teacherRows
            ->filter(fn (Teacher $teacher) => ! $teacher->institution)
            ->map(fn (Teacher $teacher) => SkCodeNormalizer::normalize($teacher->SK_Code))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedSkCodes->isNotEmpty()) {
            $fallbackInstitutions = Institution::query()
                ->with('accountInfo')
                ->whereIn('SKcode', $normalizedSkCodes)
                ->get()
                ->keyBy('SKcode');

            $teacherRows->each(function (Teacher $teacher) use ($fallbackInstitutions): void {
                if ($teacher->institution) {
                    return;
                }

                $normalizedSkCode = SkCodeNormalizer::normalize($teacher->SK_Code);
                if (! $normalizedSkCode) {
                    return;
                }

                $fallbackInstitution = $fallbackInstitutions->get($normalizedSkCode);
                if ($fallbackInstitution) {
                    $teacher->setRelation('institution', $fallbackInstitution);
                }
            });
        }

        $totalCount = Teacher::query()->count();
        $activeCount = Teacher::query()->where('ClassInOut', true)->count();
        $inactiveCount = max(0, $totalCount - $activeCount);

        $teacherInstitutionSuggestions = collect();
        if ($this->showModal && blank($this->newSkCode)) {
            $normalizedKeyword = preg_replace('/\s+/u', '', trim($this->newInstitutionKeyword)) ?? '';
            if ($normalizedKeyword !== '') {
                $teacherInstitutionSuggestions = Institution::query()
                    ->whereNotNull('SKcode')
                    ->where(function ($query) use ($normalizedKeyword): void {
                        $query->whereRaw("REPLACE(IFNULL(AccountName,''), ' ', '') like ?", ["%{$normalizedKeyword}%"])
                            ->orWhereRaw("REPLACE(IFNULL(SKcode,''), ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                    })
                    ->orderBy('AccountName')
                    ->limit(15)
                    ->get(['SKcode', 'AccountName']);
            }
        }

        return view('livewire.contact-list', [
            'teachers' => $teachers,
            'teacherInstitutionSuggestions' => $teacherInstitutionSuggestions,
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
        ]);
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        return $value->format('Y-m-d');
    }

    private function normalizeStatusForForm(?string $status): string
    {
        $normalized = trim((string) $status);

        return in_array($normalized, ['inactive', '비활성', '비활성화', '퇴직'], true)
            ? 'inactive'
            : 'active';
    }
}
