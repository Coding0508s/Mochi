<?php

namespace App\Livewire;

use App\Actions\ReinstateTeacher;
use App\Livewire\Concerns\ManagesReinstateInstitutionSelection;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\InstitutionAccountListQuery;
use App\Support\RetirementListWriter;
use App\Support\SkCodeNormalizer;
use App\Support\TeacherMasterWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactList extends Component
{
    use ManagesReinstateInstitutionSelection;
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

    // ─── 복직 확인 모달 상태 ────────────────────────────────────────
    public bool $showReinstateModal = false;

    public string $reinstateTargetName = '';

    public string $reinstateClassParticipation = 'in';

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

    public string $newClassParticipation = '';   // 수업참여: ''(미지정)|in|out

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

    public function mount(): void
    {
        $sidebarContext = trim((string) request()->query('sidebar_context', ''));
        if ($sidebarContext !== '') {
            session(['sidebar_context' => $sidebarContext]);
        }
    }

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
        Gate::authorize('createContactRecord');

        $this->resetModal();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $teacher = Teacher::query()
            ->with('institution.accountInfo')
            ->findOrFail($id);
        Gate::authorize('updateContactRecord', $teacher);

        $this->editingId = $teacher->ID;
        $this->newName = (string) ($teacher->Name ?? '');
        $this->newPhone = (string) ($teacher->Phone ?? '');
        $this->newEmail = (string) ($teacher->Email ?? '');
        $this->originalEmail = (string) ($teacher->Email ?? '');
        $this->newPosition = (string) ($teacher->Position ?? '');
        $this->newEmploymentStatus = $this->normalizeStatusForForm($teacher->Status);
        $this->newClassParticipation = $this->classParticipationFromTeacher($teacher);
        $this->newSkCode = (string) ($teacher->SK_Code ?? '');
        $this->newSchoolName = $teacher->institution?->resolvedAccountName()
            ?: (string) ($teacher->School_Name ?? '');
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
        $this->newClassParticipation = '';
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
            ->with('accountInfo')
            ->whereNotNull('SKcode')
            ->where(function ($q) use ($keyword): void {
                $q->where('AccountName', $keyword)
                    ->orWhere('SKcode', $keyword)
                    ->orWhereHas('accountInfo', function ($info) use ($keyword): void {
                        $info->where('Account_Name', $keyword);
                    });
            })
            ->first();

        if ($inst) {
            $this->newSkCode = (string) $inst->SKcode;
            $this->newSchoolName = $inst->resolvedAccountName();
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

        $inst = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $trimmed)
            ->first();
        if (! $inst) {
            return;
        }

        $this->newSkCode = (string) $inst->SKcode;
        $this->newSchoolName = $inst->resolvedAccountName();
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
        $existingTeacher = $this->editingId
            ? Teacher::query()->findOrFail($this->editingId)
            : null;

        if ($existingTeacher) {
            Gate::authorize('updateContactRecord', $existingTeacher);
        }

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
            'newClassParticipation' => ['nullable', Rule::in(['', 'in', 'out'])],
            'newGrapeSeedEssentials' => ['nullable', 'date'],
            'newLittleSeedEssentials' => ['nullable', 'date'],
        ], $this->messages);

        Gate::authorize('createContactRecord', $this->newSkCode);

        $isActive = $this->newEmploymentStatus === 'active';
        $grapeDate = trim($this->newGrapeSeedEssentials);
        $littleDate = trim($this->newLittleSeedEssentials);

        if ($existingTeacher?->isRetired() && $isActive) {
            session()->flash('warning', '퇴직 교사는 「복직 처리」를 사용해 주세요. 연락처 수정만으로 재직 상태가 바뀌지 않습니다.');

            return;
        }

        $status = $isActive ? '활성화' : '비활성화';
        if ($existingTeacher?->isRetired()) {
            $status = '퇴직';
        }

        $data = [
            'Name' => $this->newName,
            'Phone' => $this->newPhone,
            'Email' => $this->newEmail,
            'Position' => $this->newPosition,
            'SK_Code' => $this->newSkCode,
            'School_Name' => $this->newSchoolName,
            'Description' => $this->newDescription,
            'Status' => $status,
            'ClassInOut' => $this->classInOutFromParticipation($this->newClassParticipation),
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

    public function openReinstateModal(): void
    {
        if (! $this->editingId) {
            return;
        }

        $teacher = Teacher::query()->find($this->editingId);
        if (! $teacher || ! $teacher->isRetired()) {
            session()->flash('warning', '퇴직 상태인 교사만 복직 처리할 수 있습니다.');

            return;
        }

        $this->reinstateTargetName = (string) ($teacher->Name ?? '연락처');
        $this->reinstateClassParticipation = $teacher->ClassInOut ? 'in' : 'out';
        $this->prepareReinstateInstitutionState($teacher);
        $this->resetReinstateForm();
        $this->showReinstateModal = true;
    }

    public function closeReinstateModal(): void
    {
        $this->showReinstateModal = false;
        $this->reinstateTargetName = '';
        $this->reinstateClassParticipation = 'in';
        $this->resetReinstateInstitutionState();
        $this->resetReinstateForm();
    }

    public function reinstate(): void
    {
        if (! $this->editingId) {
            return;
        }

        $teacher = Teacher::query()->find($this->editingId);
        if (! $teacher || ! $teacher->isRetired()) {
            session()->flash('warning', '퇴직 상태인 교사만 복직 처리할 수 있습니다.');
            $this->closeReinstateModal();

            return;
        }

        $this->validate([
            'reinstateClassParticipation' => ['required', Rule::in(['in', 'out'])],
            'reinstateSkCode' => ['nullable', 'string', Rule::exists('S_AccountName', 'SKcode')],
        ], [
            'reinstateClassParticipation.required' => '수업 참여 여부를 선택해 주세요.',
            'reinstateClassParticipation.in' => '수업 참여 여부를 선택해 주세요.',
            'reinstateSkCode.exists' => '선택한 복직 기관을 찾을 수 없습니다. 다시 선택해 주세요.',
        ]);

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $classInOut = $this->reinstateClassParticipation === 'in';

        try {
            app(ReinstateTeacher::class)->execute($this->editingId, $user, $classInOut, $this->resolvedReinstateSkCode());
            session()->flash('success', '교사가 복직 처리되었습니다. 퇴직 이력은 퇴직교사 리스트에 "복직" 상태로 유지됩니다.');
            $this->closeReinstateModal();
            $this->closeModal();
            $this->resetPage();
        } catch (AuthorizationException) {
            session()->flash(
                'warning',
                '이 교사의 복직 처리 권한이 없습니다. Coach 담당 TR 기관 교사는 권한이 있는 계정으로 처리해 주세요.',
            );
        } catch (\InvalidArgumentException $exception) {
            session()->flash('warning', $exception->getMessage());
        }
    }

    public function canReinstateTeacher(?int $teacherId): bool
    {
        if ($teacherId === null || $teacherId <= 0) {
            return false;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return false;
        }

        $teacher = Teacher::query()->find($teacherId);
        if (! $teacher || ! $teacher->isRetired()) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacherId);
        CoachTeacherScope::apply($scopedQuery, $user);

        return $scopedQuery->exists();
    }

    private function resetReinstateForm(): void
    {
        $this->resetValidation(['reinstateClassParticipation', 'reinstateSkCode']);
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
        Gate::authorize('deleteContactRecords');

        if (! $this->deleteTargetId) {
            return;
        }

        $teacherId = $this->deleteTargetId;

        DB::transaction(function () use ($teacherId): void {
            app(RetirementListWriter::class)->deleteForTeacher($teacherId);
            app(TeacherMasterWriter::class)->deleteForTeacher($teacherId);
            Teacher::where('ID', $teacherId)->delete();
        });

        $this->closeDeleteModal();
        session()->flash('success', '연락처와 퇴직교사 관련 기록이 삭제되었습니다.');
        $this->resetPage();
    }

    public function exportContactsExcel(): ?StreamedResponse
    {
        try {
            $teachers = $this->filteredTeachersQuery()
                ->with('institution.accountInfo')
                ->orderBy('ID', 'desc')
                ->get();

            $this->hydrateTeacherInstitutions($teachers);

            if ($teachers->isEmpty()) {
                session()->flash('error', '다운로드할 데이터가 없습니다.');

                return null;
            }

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('교직원 연락처');

            $headers = [
                '기관코드',
                '기관명',
                '이름',
                '직급',
                '이메일',
                '전화번호',
                '상태',
                'GrapeSEED Essentials',
                'LittleSEED Essentials',
                '담당 Coach',
                'CS',
                'CO',
                '주소',
                '비고',
            ];

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $row = 2;
            foreach ($teachers as $teacher) {
                $sheet->fromArray([
                    (string) ($teacher->SK_Code ?? ''),
                    (string) ($teacher->School_Name ?? ''),
                    (string) ($teacher->Name ?? ''),
                    (string) ($teacher->Position ?? ''),
                    (string) ($teacher->Email ?? ''),
                    (string) ($teacher->Phone ?? ''),
                    $this->contactStatusLabel($teacher),
                    optional($teacher->GrapeSEEDEssentials)->format('Y-m-d') ?? '',
                    optional($teacher->LittleSEEDEssentials)->format('Y-m-d') ?? '',
                    (string) ($teacher->institution?->accountInfo?->TR ?? ''),
                    (string) ($teacher->CS ?: $teacher->institution?->accountInfo?->CS ?: ''),
                    (string) ($teacher->CO ?: $teacher->institution?->accountInfo?->CO ?: ''),
                    (string) ($teacher->institution?->Address ?? ''),
                    (string) ($teacher->Description ?? ''),
                ], null, 'A'.$row);
                $row++;
            }

            foreach (range('A', 'N') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $fileName = '교직원_연락처_'.now()->format('Ymd_His').'.xlsx';

            return response()->streamDownload(function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception) {
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다.');

            return null;
        }
    }

    // ─── 화면 렌더링 ──────────────────────────────────────────────
    public function render()
    {
        $teachersQuery = $this->filteredTeachersQuery();

        $teachers = (clone $teachersQuery)
            ->with('institution.accountInfo')
            ->orderBy('ID', 'desc')
            ->paginate(20);

        $this->hydrateTeacherInstitutions($teachers->getCollection());

        $statsQuery = Teacher::query();
        $this->applyContactVisibilityScope($statsQuery);
        $totalCount = (clone $statsQuery)->count();
        $activeCount = (clone $statsQuery)->where('ClassInOut', true)->count();
        $inactiveCount = (clone $statsQuery)->where('ClassInOut', false)->count();

        $teacherInstitutionSuggestions = collect();
        if ($this->showModal && blank($this->newSkCode)) {
            $keyword = trim($this->newInstitutionKeyword);
            if ($keyword !== '') {
                $teacherInstitutionQuery = Institution::query()
                    ->with('accountInfo')
                    ->whereNotNull('SKcode');

                $user = auth()->user();
                if (! $user?->hasFullAccess()) {
                    app(InstitutionAccountListQuery::class)
                        ->applyCurrentUserManagerScope($teacherInstitutionQuery);
                }

                $teacherInstitutionSuggestions = $teacherInstitutionQuery
                    ->search($keyword)
                    ->orderBy('AccountName')
                    ->limit(15)
                    ->get(['SKcode', 'AccountName']);
            }
        }

        return view('livewire.contact-list', [
            'teachers' => $teachers,
            'teacherInstitutionSuggestions' => $teacherInstitutionSuggestions,
            'reinstateInstitutionSuggestions' => $this->reinstateInstitutionSuggestions(),
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'inactiveCount' => $inactiveCount,
            'canCreateContactRecords' => Gate::allows('createContactRecord'),
            'canEditSelectedContact' => $this->canEditSelectedContact(),
            'canReinstateCurrentTeacher' => $this->canReinstateTeacher($this->editingId),
        ]);
    }

    private function canEditSelectedContact(): bool
    {
        if ($this->selectedContact === null || ! isset($this->selectedContact['id'])) {
            return false;
        }

        $teacher = Teacher::query()->find((int) $this->selectedContact['id']);
        if (! $teacher) {
            return false;
        }

        return Gate::allows('updateContactRecord', $teacher);
    }

    private function formatDate(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        return $value->format('Y-m-d');
    }

    private function filteredTeachersQuery(): Builder
    {
        $teachersQuery = Teacher::query()
            ->searchBy($this->searchType, $this->search)
            ->when($this->employmentFilter === 'active', function ($query) {
                $query->where('ClassInOut', true);
            })
            ->when($this->employmentFilter === 'inactive', function ($query) {
                $query->where('ClassInOut', false);
            });
        $this->applyContactVisibilityScope($teachersQuery);

        return $teachersQuery;
    }

    private function applyContactVisibilityScope(Builder $query): void
    {
        CoachTeacherScope::excludeHiddenInstitutions($query);

        $user = auth()->user();
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * @param  Collection<int, Teacher>  $teacherRows
     */
    private function hydrateTeacherInstitutions(Collection $teacherRows): void
    {
        $normalizedSkCodes = $teacherRows
            ->filter(fn (Teacher $teacher) => ! $teacher->institution)
            ->map(fn (Teacher $teacher) => SkCodeNormalizer::normalize($teacher->SK_Code))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedSkCodes->isEmpty()) {
            return;
        }

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

    private function contactStatusLabel(Teacher $teacher): string
    {
        if (trim((string) $teacher->Status) === '퇴직') {
            return '퇴직';
        }

        if (in_array(trim((string) $teacher->Status), ['inactive', '비활성', '비활성화'], true)) {
            return '비활성화';
        }

        return '활성화';
    }

    private function normalizeStatusForForm(?string $status): string
    {
        $normalized = trim((string) $status);

        return in_array($normalized, ['inactive', '비활성', '비활성화', '퇴직'], true)
            ? 'inactive'
            : 'active';
    }

    private function classParticipationFromTeacher(Teacher $teacher): string
    {
        if (! array_key_exists('ClassInOut', $teacher->getAttributes())) {
            return '';
        }

        $raw = $teacher->getAttributes()['ClassInOut'];

        if ($raw === null) {
            return '';
        }

        return filter_var($raw, FILTER_VALIDATE_BOOLEAN) ? 'in' : 'out';
    }

    private function classInOutFromParticipation(string $participation): ?bool
    {
        return match ($participation) {
            'in' => true,
            'out' => false,
            default => null,
        };
    }
}
