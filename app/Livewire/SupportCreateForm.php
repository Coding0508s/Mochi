<?php

namespace App\Livewire;

use App\Actions\ResolveInstitutionRecipients;
use App\Livewire\Concerns\ManagesCoachTeacherSupportCreateModals;
use App\Livewire\Concerns\OpensTeacherSupportHistoryDetail;
use App\Mail\UrgentSupportNotificationMail;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use App\Models\ContractDocument;
use App\Models\Institution;
use App\Models\SalesforceAccount;
use App\Models\SalesforceFile;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\UrgentSupportNotification;
use App\Models\User;
use App\Support\SkCodeNormalizer;
use App\Support\SupportReportStoredMailNotifier;
use App\Support\TeamMenuContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class SupportCreateForm extends Component
{
    use ManagesCoachTeacherSupportCreateModals;
    use OpensTeacherSupportHistoryDetail;
    use WithFileUploads;

    public string $formSkCode = '';

    public ?int $formPotentialTargetId = null;

    public string $formAccountName = '';

    public string $formInstitutionKeyword = '';

    public string $formCoName = '';

    public string $formSupportDate = '';

    public string $formSupportTime = '';

    public string $formSupportType = '전화';

    public string $formTarget = '';

    public string $formToAccount = '';

    /** 본사/타 부서 공유 (TO_Depart) */
    public string $formToDepart = '';

    /** CS 기관 이슈 모드의 이슈 내용 (Issue 컬럼) */
    public string $formIssue = '';

    public bool $formIsPotential = false;

    /** 잠재기관일 때만 사용하는 가능성 (A/B/C/D) */
    public string $formPossibility = '';

    public bool $formCompleted = false;

    public bool $isUrgent = false;

    /** @var list<int> */
    public array $urgentRecipientIds = [];

    /**
     * @var list<array{id:int,name:string,email:?string,roles:list<string>,is_auto:bool}>
     */
    public array $availableRecipients = [];

    public string $selectedUrgentRecipientId = '';

    /** @var TemporaryUploadedFile|null */
    public $sfUpload = null;

    /** 저장 후 이동할 라우트 이름 (supports.index | institutions.index) */
    public string $afterSaveRouteName = 'supports.index';

    /** 작성 화면 진입 시 팀 메뉴(cs|coach|co) — 저장·메일 라벨에 동일 적용 */
    public ?string $formTeamMenu = null;

    public string $reportMode = 'institution';

    public int|string|null $formTeacherId = null;

    public ?string $formCoachTeacherCreateAction = null;

    protected array $rules = [
        'formSkCode' => ['nullable', 'required_without:formPotentialTargetId'],
        'formPotentialTargetId' => ['nullable', 'integer', 'required_without:formSkCode'],
        'formSupportDate' => 'required|date',
        'formSupportTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        'sfUpload' => [
            'nullable',
            'file',
            'max:20480',
            'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx',
        ],
    ];

    protected array $messages = [
        'formSkCode.required_without' => '기관을 선택해 주세요.',
        'formPotentialTargetId.required_without' => '기관을 선택해 주세요.',
        'formSupportDate.required' => '지원 날짜를 입력해 주세요.',
        'formSupportDate.date' => '올바른 날짜 형식이 아닙니다.',
        'formSupportTime.required' => '지원 시간을 입력해 주세요.',
        'formSupportTime.regex' => '지원 시간은 HH:MM 형식으로 입력해 주세요.',
        'formTarget.required' => '교사명을 입력해 주세요.',
        'sfUpload.max' => '파일 크기는 20MB 이하여야 합니다.',
        'sfUpload.mimes' => '허용 형식: PDF, 이미지, Word, Excel',
    ];

    public function mount(?int $potentialTargetId = null): void
    {
        $user = auth()->user();
        $this->formCoName = $user !== null ? $user->nameForCoReports() : '';
        $this->formSupportDate = now()->format('Y-m-d');
        $this->formSupportTime = now()->format('H:i');
        $requestedTeamMenu = request()->query('team_menu');
        $this->formTeamMenu = in_array($requestedTeamMenu, ['co', 'coach', 'cs'], true)
            ? (string) $requestedTeamMenu
            : (TeamMenuContext::activeMenu($user) ?? 'co');
        $this->applyInitialReportMode();
        $this->applyDefaultCompletionForReportMode();

        $prefillId = $potentialTargetId ?? request()->integer('potential_target_id');
        if ($prefillId > 0) {
            if (! config('potential_institutions.show_support_report_ui')) {
                session()->flash('warning', '잠재기관에서는 기관 지원 보고서 작성 기능을 사용하지 않습니다.');

                $this->redirect(route('potential-institutions.index'), navigate: true);

                return;
            }

            $this->applyPotentialTargetPrefill($prefillId);
            $this->syncInlineVisitFormState();

            return;
        }

        $prefillSkCode = trim((string) request()->query('sk_code', ''));
        if ($prefillSkCode !== '') {
            $this->afterSaveRouteName = $this->resolveAfterSaveRouteName(
                (string) request()->query('return', '')
            );
            $this->applyInstitutionSkPrefill($prefillSkCode);
        }

        $prefillTeacherName = trim((string) request()->query('teacher_name', ''));
        if ($prefillTeacherName !== '') {
            $this->formTarget = $prefillTeacherName;
            if ($this->canUseTeacherReportMode()) {
                $this->reportMode = 'teacher';
                $this->applyDefaultCompletionForReportMode();
            }
            $this->syncFormTeacherIdFromTargetName();
        }

        $prefillSupportType = trim((string) request()->query('support_type', ''));
        if ($prefillSupportType !== '') {
            $this->formSupportType = $prefillSupportType;
        }

        $this->syncInlineVisitFormState();
    }

    public function canUseTeacherReportMode(): bool
    {
        // 교사 지원 보고서는 Coach 팀 전용 (CO·CS 팀은 사용하지 않음)
        return ! in_array($this->formTeamMenu, ['co', 'cs'], true);
    }

    /** CS 팀만 "기관 이슈" 경량 보고서 토글을 사용한다. */
    public function canUseIssueReportMode(): bool
    {
        return $this->formTeamMenu === 'cs';
    }

    public function usesCoachTypedTeacherSupportCreate(): bool
    {
        return $this->formTeamMenu === 'coach'
            && $this->reportMode === 'teacher'
            && blank($this->formCoachTeacherCreateAction);
    }

    /** CS 팀은 기관 이슈, Coach 팀은 교사 지원, 그 외는 기관 지원 보고서가 기본 모드다. */
    private function defaultReportMode(): string
    {
        if ($this->canUseIssueReportMode()) {
            return 'issue';
        }

        if ($this->formTeamMenu === 'coach') {
            return 'teacher';
        }

        return 'institution';
    }

    private function applyInitialReportMode(): void
    {
        $requestedReportMode = request()->query('report_mode');

        if ($requestedReportMode === 'teacher' && $this->canUseTeacherReportMode()) {
            $this->reportMode = 'teacher';

            return;
        }

        if ($requestedReportMode === 'issue' && $this->canUseIssueReportMode()) {
            $this->reportMode = 'issue';

            return;
        }

        if ($requestedReportMode === 'institution') {
            $this->reportMode = 'institution';

            return;
        }

        $this->reportMode = $this->defaultReportMode();
    }

    private function applyDefaultCompletionForReportMode(): void
    {
        $this->formCompleted = $this->reportMode === 'institution';
    }

    public function setReportMode(string $mode): void
    {
        if (! in_array($mode, ['institution', 'teacher', 'issue'], true)) {
            return;
        }

        if ($mode === 'teacher' && ! $this->canUseTeacherReportMode()) {
            $this->reportMode = $this->defaultReportMode();

            return;
        }

        if ($mode === 'issue' && ! $this->canUseIssueReportMode()) {
            $this->reportMode = $this->defaultReportMode();

            return;
        }

        $previousMode = $this->reportMode;
        $this->reportMode = $mode;

        if ($previousMode !== $this->reportMode) {
            $this->applyDefaultCompletionForReportMode();
            $this->syncCommunicationTemplatesOnModeChange($previousMode);
            $this->formTeacherId = null;

            if ($this->formTeamMenu === 'coach' && $this->reportMode === 'teacher') {
                $this->syncInlineVisitFormState();
            } else {
                $this->formCoachTeacherCreateAction = null;
                $this->visitTeacherId = null;
                $this->visitForm = [];
                $this->resetSupportRoundSelection();
            }
        }
    }

    public function startCoachTeacherSupportCreate(string $action): void
    {
        if (! ($this->usesCoachTypedTeacherSupportCreate() || $this->usesCoachTypedTeacherSupportForm())) {
            return;
        }

        if (! in_array($action, $this->coachTeacherSupportCreateActions(), true)) {
            return;
        }

        if (blank($this->formSkCode)) {
            $this->addError('formSkCode', '기관을 먼저 선택해 주세요.');

            return;
        }

        $teacherId = $this->selectedTeacherId();
        if ($teacherId === null) {
            $this->addError('formTeacherId', '교사를 선택해 주세요.');

            return;
        }

        $selectedType = collect(config('coach_teacher_support_create.types', []))
            ->map(function (array|string $pill): ?array {
                if (! is_array($pill)) {
                    return null;
                }

                $label = isset($pill['label']) ? trim((string) $pill['label']) : '';
                $typeAction = isset($pill['action']) ? trim((string) $pill['action']) : '';

                if ($label === '' || $typeAction === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'action' => $typeAction,
                ];
            })
            ->filter()
            ->first(fn (array $pill): bool => $pill['action'] === $action);

        if ($selectedType === null) {
            return;
        }

        $this->formCoachTeacherCreateAction = $action;
        $this->formSupportType = (string) $selectedType['label'];

        if ($action === 'visit') {
            $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
            if (! $teacher) {
                $this->addError('formTeacherId', '교사를 선택해 주세요.');
                $this->formCoachTeacherCreateAction = null;

                return;
            }

            $institution = $teacher->institution;
            if (! $institution) {
                $normalizedSkCode = SkCodeNormalizer::normalize($teacher->SK_Code);
                if ($normalizedSkCode) {
                    $institution = Institution::query()
                        ->with('accountInfo')
                        ->where('SKcode', $normalizedSkCode)
                        ->first();
                }
            }

            $accountInfo = $institution?->accountInfo;
            $user = auth()->user();
            $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

            $this->visitTeacherId = $teacherId;
            $this->visitMarkCompleted = $this->formCompleted;
            $this->visitForm = $this->defaultVisitForm(
                skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
                coachName: $coachName,
                institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
                teacherName: (string) $teacher->Name,
            );
            $this->seedSupportRoundSelection($teacher);

            return;
        }

        $this->closeOpenSupportReportModals();
        $this->openCoachTeacherSupportCreateModal($action, $teacherId);
    }

    private function defaultCoachTeacherSupportCreateAction(): string
    {
        return 'visit';
    }

    private function openDefaultCoachTeacherSupportCreateIfReady(): void
    {
        if ($this->formTeamMenu !== 'coach' || $this->reportMode !== 'teacher') {
            return;
        }

        $this->syncInlineVisitFormState();
    }

    public function usesCoachTypedTeacherSupportForm(): bool
    {
        return $this->formTeamMenu === 'coach'
            && $this->reportMode === 'teacher'
            && filled($this->formCoachTeacherCreateAction);
    }

    public function resetCoachTeacherSupportCreate(): void
    {
        $this->syncInlineVisitFormState();
    }

    public function coachTypedTeacherSupportCreateLabel(): ?string
    {
        if (blank($this->formCoachTeacherCreateAction)) {
            return null;
        }

        $selected = collect(config('coach_teacher_support_create.types', []))
            ->map(function (array|string $pill): ?array {
                if (! is_array($pill)) {
                    return null;
                }

                $label = isset($pill['label']) ? trim((string) $pill['label']) : '';
                $typeAction = isset($pill['action']) ? trim((string) $pill['action']) : '';

                if ($label === '' || $typeAction === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'action' => $typeAction,
                ];
            })
            ->filter()
            ->first(fn (array $pill): bool => $pill['action'] === $this->formCoachTeacherCreateAction);

        return $selected['label'] ?? null;
    }

    protected function expectedSupportHistorySkCodeForDetail(): ?string
    {
        return filled($this->formSkCode) ? $this->formSkCode : null;
    }

    protected function findVisibleTeacherForSupportModal(int $teacherId): ?Teacher
    {
        if ($this->selectedTeacherId() !== $teacherId || $teacherId <= 0 || blank($this->formSkCode)) {
            return null;
        }

        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if ($teacher === null || $teacher->isRetired()) {
            return null;
        }

        $candidates = SkCodeNormalizer::candidates($this->formSkCode);
        $teacherSk = SkCodeNormalizer::normalize((string) $teacher->SK_Code) ?? (string) $teacher->SK_Code;

        if (! in_array((string) $teacher->SK_Code, $candidates, true)
            && ! in_array($teacherSk, $candidates, true)) {
            return null;
        }

        return $teacher;
    }

    protected function finalizeCoachTeacherSupportReportSave(int $teacherId, callable $closeModal): void
    {
        $closeModal();
        $this->syncInlineVisitFormState();
    }

    protected function afterCoachTeacherSupportModalClosed(): void
    {
        if ($this->formTeamMenu === 'coach' && $this->reportMode === 'teacher') {
            $this->syncInlineVisitFormState();
        }
    }

    /**
     * @return list<string>
     */
    private function coachTeacherSupportCreateActions(): array
    {
        return collect(config('coach_teacher_support_create.types', []))
            ->map(fn (array|string $pill): ?string => is_array($pill) ? ($pill['action'] ?? null) : null)
            ->filter(fn (?string $action): bool => filled($action))
            ->values()
            ->all();
    }

    private function applyInstitutionSkPrefill(string $skCode): void
    {
        $institution = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $skCode)
            ->first();
        if ($institution === null) {
            session()->flash('warning', '기관을 찾을 수 없습니다. SK코드를 확인해 주세요.');

            return;
        }

        if ($this->isTerminatedInstitution($institution)) {
            session()->flash('warning', '해지된 기관입니다. 신규 지원보고서 작성이 제한됩니다.');

            return;
        }

        $this->formSkCode = (string) $institution->SKcode;
        $this->formAccountName = $institution->resolvedAccountName();
        $this->formInstitutionKeyword = $this->formAccountName;
        $this->formPotentialTargetId = null;
        $this->formIsPotential = false;
        $this->formPossibility = '';
        $this->applyDefaultCommunicationTemplatesIfEmpty();
    }

    private function resolveAfterSaveRouteName(string $return): string
    {
        return match ($return) {
            'institutions' => 'institutions.index',
            default => 'supports.index',
        };
    }

    private function applyPotentialTargetPrefill(int $id): void
    {
        $potential = $this->findPotentialById($id);
        if (! $potential) {
            session()->flash('warning', '잠재기관을 찾을 수 없거나 이미 계약 처리되었습니다.');

            return;
        }
        if (! $this->canManagePotentialTarget($potential)) {
            session()->flash('warning', '본인이 등록한 잠재기관만 관리할 수 있습니다.');

            return;
        }

        $this->formPotentialTargetId = (int) $potential->ID;
        $this->formSkCode = trim((string) ($potential->AccountCode ?? ''));
        $this->formAccountName = (string) ($potential->AccountName ?? '');
        $this->formIsPotential = true;
        $this->formPossibility = (string) ($potential->Possibility ?? '');
        $this->applyDefaultCommunicationTemplatesIfEmpty();
    }

    public function updatedFormSkCode(string $value): void
    {
        if (blank($value)) {
            $this->formAccountName = '';
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';
            if ($this->isUrgent) {
                $this->clearUrgentRecipients();
            }

            return;
        }

        $potential = $this->findPotentialBySkCode($value);
        $inst = Institution::query()
            ->with('accountInfo')
            ->where('SKcode', $value)
            ->first();
        if ($this->isTerminatedInstitution($inst)) {
            session()->flash('warning', '해지된 기관입니다. 신규 지원보고서 작성이 제한됩니다.');
            $this->formSkCode = '';
            $this->formAccountName = '';
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';

            return;
        }
        $this->formAccountName = (string) ($inst?->AccountName ?? $potential?->AccountName ?? '');
        $this->formPotentialTargetId = $potential?->ID ? (int) $potential->ID : null;
        $this->formIsPotential = $potential !== null;
        $this->formPossibility = $potential ? (string) ($potential->Possibility ?? '') : '';
        if (filled($value)) {
            $this->applyDefaultCommunicationTemplatesIfEmpty();
            if ($this->isUrgent) {
                $this->refreshUrgentRecipients();
            }
        }
    }

    public function updatedFormInstitutionKeyword(string $value): void
    {
        $keyword = trim($value);

        if ($keyword === '') {
            $this->formSkCode = '';
            $this->formAccountName = '';
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';
            $this->formTeacherId = null;
            if ($this->isUrgent) {
                $this->clearUrgentRecipients();
            }

            return;
        }

        $potential = $this->findPotentialByKeyword($keyword);
        if ($potential) {
            $this->formSkCode = trim((string) ($potential->AccountCode ?? ''));
            $this->formAccountName = (string) $potential->AccountName;
            $this->formPotentialTargetId = (int) $potential->ID;
            $this->formIsPotential = true;
            $this->formPossibility = (string) ($potential->Possibility ?? '');
            $this->applyDefaultCommunicationTemplatesIfEmpty();
            if ($this->isUrgent) {
                $this->refreshUrgentRecipients();
            }

            return;
        }

        $inst = Institution::query()
            ->with('accountInfo')
            ->where(function ($query) use ($keyword): void {
                $query->where('AccountName', $keyword)
                    ->orWhere('SKcode', $keyword)
                    ->orWhereHas('accountInfo', function ($info) use ($keyword): void {
                        $info->where('Account_Name', $keyword);
                    });
            })
            ->first();

        if ($inst) {
            if ($this->isTerminatedInstitution($inst)) {
                session()->flash('warning', '해지된 기관입니다. 신규 지원보고서 작성이 제한됩니다.');
                $this->formSkCode = '';
                $this->formAccountName = '';
                $this->formPotentialTargetId = null;
                $this->formIsPotential = false;
                $this->formPossibility = '';
                $this->formTeacherId = null;

                return;
            }

            $this->formSkCode = (string) $inst->SKcode;
            $this->formAccountName = $inst->resolvedAccountName();
            $this->formPotentialTargetId = null;
            $this->formIsPotential = false;
            $this->formPossibility = '';
            $this->applyDefaultCommunicationTemplatesIfEmpty();
            if ($this->isUrgent) {
                $this->refreshUrgentRecipients();
            }

            return;
        }

        $this->formSkCode = '';
        $this->formAccountName = '';
        $this->formPotentialTargetId = null;
        $this->formIsPotential = false;
        $this->formPossibility = '';
        $this->formTeacherId = null;
        if ($this->isUrgent) {
            $this->clearUrgentRecipients();
        }
    }

    public function selectInstitution(string $skCode = '', bool $isPotential = false, ?int $potentialTargetId = null): void
    {
        $trimmedSkCode = trim($skCode);
        $inst = $trimmedSkCode !== ''
            ? Institution::query()->with('accountInfo')->where('SKcode', $trimmedSkCode)->first()
            : null;
        $potential = $potentialTargetId !== null
            ? $this->findPotentialById($potentialTargetId)
            : null;
        if ($potential === null && $trimmedSkCode !== '') {
            $potential = $this->findPotentialBySkCode($trimmedSkCode);
        }
        if ($potential !== null && ! $this->canManagePotentialTarget($potential)) {
            return;
        }

        if (($isPotential || $potential !== null) && ! config('potential_institutions.show_support_report_ui')) {
            session()->flash('warning', '잠재기관에서는 기관 지원 보고서 작성 기능을 사용하지 않습니다.');

            return;
        }

        if (! $inst && ! $isPotential && $potential === null) {
            return;
        }

        if ($this->isTerminatedInstitution($inst)) {
            session()->flash('warning', '해지된 기관입니다. 신규 지원보고서 작성이 제한됩니다.');

            return;
        }

        $this->formSkCode = $inst
            ? (string) $inst->SKcode
            : trim((string) ($potential?->AccountCode ?? $trimmedSkCode));
        $this->formAccountName = $inst
            ? (string) $inst->AccountName
            : (string) ($potential?->AccountName ?? '');
        $this->formInstitutionKeyword = $this->formAccountName;
        $this->formPotentialTargetId = ($isPotential || $potential !== null) && $potential?->ID
            ? (int) $potential->ID
            : null;
        $this->formIsPotential = $this->formPotentialTargetId !== null;
        $this->formPossibility = $this->formIsPotential ? (string) ($potential?->Possibility ?? '') : '';
        $this->formTeacherId = null;
        $this->syncInlineVisitFormState();
        $this->syncFormTeacherIdFromTargetName();
        $this->applyDefaultCommunicationTemplatesIfEmpty();
        if ($this->isUrgent) {
            $this->refreshUrgentRecipients();
        }
    }

    public function updatedIsUrgent(bool $value): void
    {
        if (! $value) {
            $this->clearUrgentRecipients();

            return;
        }

        $this->refreshUrgentRecipients();
    }

    public function addRecipient(): void
    {
        $recipientId = (int) $this->selectedUrgentRecipientId;
        if ($recipientId <= 0) {
            return;
        }

        $recipientExists = collect($this->availableRecipients)
            ->contains(fn (array $recipient): bool => (int) ($recipient['id'] ?? 0) === $recipientId);

        if (! $recipientExists) {
            return;
        }

        if (! in_array($recipientId, $this->urgentRecipientIds, true)) {
            $this->urgentRecipientIds[] = $recipientId;
            $this->urgentRecipientIds = array_values(array_unique(array_map('intval', $this->urgentRecipientIds)));
        }

        $this->selectedUrgentRecipientId = '';
    }

    public function removeRecipient(int $recipientId): void
    {
        $this->urgentRecipientIds = array_values(array_filter(
            $this->urgentRecipientIds,
            fn (int $id): bool => $id !== $recipientId
        ));
    }

    private function clearUrgentRecipients(): void
    {
        $this->urgentRecipientIds = [];
        $this->availableRecipients = [];
        $this->selectedUrgentRecipientId = '';
    }

    private function refreshUrgentRecipients(): void
    {
        if (! $this->hasInstitutionSelection() || blank($this->formSkCode)) {
            $this->clearUrgentRecipients();

            return;
        }

        $autoRecipients = app(ResolveInstitutionRecipients::class)
            ->execute($this->formSkCode)
            ->keyBy('id');

        $users = User::query()
            ->where('is_active', true)
            ->with('employee')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'employee_empno']);

        $this->availableRecipients = $users->map(function (User $user) use ($autoRecipients): array {
            $auto = $autoRecipients->get((int) $user->id);

            return [
                'id' => (int) $user->id,
                'name' => $user->preferredDisplayName(),
                'email' => filled($user->email) ? (string) $user->email : null,
                'roles' => is_array($auto['roles'] ?? null) ? $auto['roles'] : [],
                'is_auto' => $auto !== null,
            ];
        })->values()->all();

        $existing = collect($this->urgentRecipientIds)
            ->map(fn (int $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->all();
        $autoIds = $autoRecipients->keys()->map(fn (mixed $id): int => (int) $id)->all();

        $this->urgentRecipientIds = array_values(array_unique(array_merge($existing, $autoIds)));
    }

    public function updatedFormTeacherId(mixed $value): void
    {
        $teacherId = $this->normalizeTeacherId($value);
        $this->formTeacherId = $teacherId;

        if ($teacherId === null) {
            $this->formTarget = '';
            $this->syncInlineVisitFormState();

            return;
        }

        $teacher = Teacher::query()->find($teacherId);
        if ($teacher?->isRetired()) {
            $this->addError('formTeacherId', '퇴직 교사는 선택할 수 없습니다.');
            $this->formTeacherId = null;
            $this->formTarget = '';
            $this->visitTeacherId = null;
            $this->syncInlineVisitFormState();

            return;
        }

        $this->formTarget = $teacher !== null ? (string) $teacher->Name : '';
        $this->resetValidation('formTeacherId');
        $this->openDefaultCoachTeacherSupportCreateIfReady();
    }

    private function syncFormTeacherIdFromTargetName(): void
    {
        if (! $this->usesCoachTypedTeacherSupportCreate() || blank($this->formSkCode) || blank($this->formTarget)) {
            return;
        }

        $teacher = Teacher::query()
            ->whereIn('SK_Code', SkCodeNormalizer::candidates($this->formSkCode))
            ->excludeRetired()
            ->where('Name', $this->formTarget)
            ->first();

        $this->formTeacherId = $teacher !== null ? (int) $teacher->ID : null;
        $this->openDefaultCoachTeacherSupportCreateIfReady();
    }

    private function normalizeTeacherId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        $teacherId = (int) $trimmed;

        return $teacherId > 0 ? $teacherId : null;
    }

    private function selectedTeacherId(): ?int
    {
        return $this->normalizeTeacherId($this->formTeacherId);
    }

    /**
     * @return Collection<int, Teacher>
     */
    private function institutionTeachers(): Collection
    {
        if (blank($this->formSkCode)) {
            return collect();
        }

        return Teacher::query()
            ->whereIn('SK_Code', SkCodeNormalizer::candidates($this->formSkCode))
            ->excludeRetired()
            ->orderBy('Name')
            ->get(['ID', 'Name']);
    }

    /**
     * 기관 선택 직후, 소통 필드가 비어 있을 때만 config 템플릿을 넣습니다.
     */
    private function applyDefaultCommunicationTemplatesIfEmpty(): void
    {
        if (! $this->hasInstitutionSelection()) {
            return;
        }

        // 기관 이슈 모드의 "처리 내역"에는 소통 템플릿을 채우지 않는다.
        if ($this->reportMode === 'issue') {
            return;
        }

        if ($this->formToAccount === '') {
            $this->formToAccount = $this->communicationTemplate('to_account', $this->reportMode);
        }

        if ($this->formToDepart === '') {
            $this->formToDepart = $this->communicationTemplate('to_depart', $this->reportMode);
        }
    }

    public function save(): void
    {
        TeamMenuContext::abortIfCrossTeamReadOnly(auth()->user(), $this->formTeamMenu);

        if ($this->reportMode === 'issue') {
            $this->saveInstitutionIssue();

            return;
        }

        if ($this->usesCoachTypedTeacherSupportForm()
            && $this->visitTeacherId !== null
            && $this->visitTeacherId > 0
            && Teacher::query()->whereKey($this->visitTeacherId)->retired()->exists()) {
            $this->addError('formTeacherId', '퇴직 교사는 선택할 수 없습니다.');

            return;
        }

        if ($this->usesCoachTypedTeacherSupportForm()) {
            if ($this->formCoachTeacherCreateAction === 'visit') {
                if ($this->visitTeacherId === null || $this->visitTeacherId <= 0) {
                    $this->addError('formTeacherId', '교사를 선택해 주세요.');

                    return;
                }

                $this->visitMarkCompleted = $this->formCompleted;
                $this->saveVisitReport();
                if ($this->getErrorBag()->isEmpty()) {
                    $this->redirect(
                        TeamMenuContext::route($this->afterSaveRouteName, [], null, $this->formTeamMenu),
                        navigate: true,
                    );
                }

                return;
            }
        }

        if ($this->usesCoachTypedTeacherSupportCreate()) {
            $this->addError('formTeacherId', '교사를 선택하면 지원 및 참관 보고서 입력 화면이 열립니다.');

            return;
        }

        $rules = $this->rules;
        if ($this->reportMode === 'teacher') {
            $rules['formTarget'] = ['required', 'string', 'max:255'];
        }

        $this->validate($rules);

        if ($this->formPotentialTargetId !== null && ! config('potential_institutions.show_support_report_ui')) {
            $this->addError('formSkCode', '잠재기관에서는 기관 지원 보고서 작성 기능을 사용하지 않습니다.');

            return;
        }

        $upload = $this->sfUpload;
        if ($upload instanceof TemporaryUploadedFile && blank($this->formSkCode)) {
            $this->addError('sfUpload', 'SK코드가 발급된 기관만 파일 업로드가 가능합니다. (미계약 잠재기관은 보고서만 저장)');

            return;
        }

        $resolvedPotentialTargetId = $this->resolveUncontractedPotentialTargetId();
        $storedPath = null;
        $originalFilename = null;
        $detectedMimeType = null;
        $detectedSize = null;
        $supportRecord = null;

        try {
            if ($upload instanceof TemporaryUploadedFile) {
                // TemporaryUploadedFile은 storeAs 이후 임시 파일 메타 접근이 실패할 수 있어 사전 캡처합니다.
                $originalFilename = $upload->getClientOriginalName();
                $detectedMimeType = $upload->getMimeType();
                $detectedSize = $upload->getSize();

                $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $originalFilename) ?? 'support-file';
                $storedName = Str::uuid()->toString().'_'.$safeOriginal;
                $directory = 'contract-documents/'.$this->formSkCode;
                $storedPath = $upload->storeAs($directory, $storedName, 'local');

                if ($storedPath === false) {
                    $this->addError('sfUpload', '파일 저장에 실패했습니다.');

                    return;
                }
            }

            DB::transaction(function () use ($upload, $storedPath, $originalFilename, $detectedMimeType, $detectedSize, $resolvedPotentialTargetId, &$supportRecord): void {
                $supportRecord = SupportRecord::query()->create(
                    SupportRecord::filterAttributesForTable([
                        'Year' => (int) date('Y', strtotime($this->formSupportDate)),
                        'SK_Code' => $this->formSkCode !== '' ? $this->formSkCode : null,
                        'potential_target_id' => $resolvedPotentialTargetId,
                        'Account_Name' => $this->formAccountName,
                        'TR_Name' => $this->formCoName,
                        'Support_Date' => $this->formSupportDate,
                        'Meet_Time' => $this->formSupportTime.':00',
                        'Support_Type' => $this->formSupportType,
                        'Target' => $this->formTarget,
                        'Issue' => null,
                        'TO_Account' => $this->formToAccount,
                        'TO_Depart' => $this->formToDepart,
                        'is_urgent' => $this->isUrgent,
                        'CreatedDate' => now(),
                        ...SupportRecord::completionAttributes($this->formCompleted),
                    ])
                );

                $this->mirrorSupportToPotentialDetail($supportRecord);

                if ($upload instanceof TemporaryUploadedFile && is_string($storedPath) && $storedPath !== '') {
                    $documentTime = strlen($this->formSupportTime) >= 5
                        ? substr($this->formSupportTime, 0, 5).':00'
                        : $this->formSupportTime;
                    $filenameForRecord = is_string($originalFilename) && $originalFilename !== ''
                        ? $originalFilename
                        : $upload->getClientOriginalName();

                    ContractDocument::query()->create([
                        'sk_code' => $this->formSkCode,
                        'account_name' => $this->formAccountName ?: '-',
                        'changed_account_name' => null,
                        'business_number' => null,
                        'document_date' => $this->formSupportDate,
                        'document_time' => $documentTime,
                        'consultant' => $this->formCoName ?: (string) (auth()->user()?->nameForCoReports() ?? ''),
                        'original_filename' => $filenameForRecord,
                        'stored_disk' => 'local',
                        'stored_path' => $storedPath,
                        'mime_type' => $detectedMimeType,
                        'size_bytes' => $detectedSize,
                        'uploaded_by' => auth()->user()?->nameForCoReports(),
                    ]);

                    if (Schema::hasTable('SF_Files')) {
                        SalesforceFile::query()->create([
                            'fileName' => $this->buildSfUploadFileName($filenameForRecord, $this->formAccountName),
                            'download_Cnt' => 0,
                            'LastUpdate_Date' => now()->format('Y-m-d H:i:s'),
                            'User' => (string) (auth()->user()?->nameForCoReports() ?? $this->formCoName),
                            'created_Date' => now()->format('Y-m-d H:i:s'),
                        ]);
                    }
                }
            });
        } catch (\Throwable $e) {
            if (is_string($storedPath) && $storedPath !== '' && Storage::disk('local')->exists($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $e;
        }

        if ($supportRecord instanceof SupportRecord) {
            SupportReportStoredMailNotifier::send(
                $supportRecord,
                auth()->user(),
                $this->formTeamMenu,
                $this->reportMode === 'teacher' ? 'teacher' : 'institution',
            );
        }

        if ($supportRecord instanceof SupportRecord && $this->isUrgent) {
            $this->sendUrgentNotifications($supportRecord);
        }

        $this->sfUpload = null;
        session()->flash(
            'success',
            $this->reportMode === 'teacher'
                ? '교사 지원 보고서가 저장되었습니다.'
                : '지원 보고서가 저장되었습니다.',
        );
        $this->redirect(
            TeamMenuContext::route($this->afterSaveRouteName, [], null, $this->formTeamMenu),
            navigate: true,
        );
    }

    /**
     * CS 기관 이슈(경량) 저장 — record_kind='issue'.
     * 처리 내역은 formToAccount(TO_Account)에 저장한다.
     */
    private function saveInstitutionIssue(): void
    {
        if (! $this->canUseIssueReportMode()) {
            $this->reportMode = 'institution';

            return;
        }

        $this->validate([
            'formSkCode' => ['required'],
            'formSupportDate' => ['required', 'date'],
            'formSupportTime' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'formIssue' => ['required', 'string', 'max:5000'],
        ], [
            'formSkCode.required' => '기관을 선택해 주세요.',
            'formSupportDate.required' => '발생일을 입력해 주세요.',
            'formSupportDate.date' => '올바른 날짜 형식이 아닙니다.',
            'formSupportTime.required' => '시간을 입력해 주세요.',
            'formSupportTime.regex' => '시간은 HH:MM 형식으로 입력해 주세요.',
            'formIssue.required' => '이슈 내용을 입력해 주세요.',
        ]);

        $issueRecord = null;

        DB::transaction(function () use (&$issueRecord): void {
            $issueRecord = SupportRecord::query()->create(
                SupportRecord::filterAttributesForTable([
                    'Year' => (int) date('Y', strtotime($this->formSupportDate)),
                    'SK_Code' => $this->formSkCode,
                    'Account_Name' => $this->formAccountName,
                    'TR_Name' => $this->formCoName,
                    'Support_Date' => $this->formSupportDate,
                    'Meet_Time' => $this->formSupportTime.':00',
                    'Support_Type' => '기관이슈',
                    'Issue' => $this->formIssue,
                    'TO_Account' => null,
                    'is_urgent' => $this->isUrgent,
                    'record_kind' => SupportRecord::KIND_ISSUE,
                    'CreatedDate' => now(),
                    ...SupportRecord::completionAttributes($this->formCompleted),
                ])
            );
        });

        if ($issueRecord instanceof SupportRecord && $this->isUrgent) {
            $this->sendUrgentNotifications($issueRecord);
        }

        session()->flash('success', '기관 이슈가 저장되었습니다.');

        $this->redirect(
            TeamMenuContext::route('institutions.index', [], null, $this->formTeamMenu),
            navigate: true,
        );
    }

    private function sendUrgentNotifications(SupportRecord $supportRecord): void
    {
        if (! Schema::hasTable('urgent_support_notifications')) {
            session()->flash(
                'warning',
                '긴급 알림 테이블이 없어 인앱 알림을 저장하지 못했습니다. `php artisan migrate` 실행 후 다시 시도해 주세요.',
            );

            return;
        }

        $recipientIds = collect($this->urgentRecipientIds)
            ->map(fn (int $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($recipientIds === []) {
            session()->flash('warning', '긴급 알림이 설정되었지만 수신자가 없어 알림을 보내지 않았습니다.');

            return;
        }

        $sender = auth()->user();
        if ($sender === null) {
            return;
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            UrgentSupportNotification::query()->create([
                'support_record_id' => (int) $supportRecord->ID,
                'recipient_user_id' => (int) $recipient->id,
                'sender_user_id' => (int) $sender->id,
                'sk_code' => filled($supportRecord->SK_Code) ? (string) $supportRecord->SK_Code : null,
                'account_name' => filled($supportRecord->Account_Name) ? (string) $supportRecord->Account_Name : null,
                'message' => filled($supportRecord->TO_Account) ? (string) $supportRecord->TO_Account : null,
                'is_read' => false,
                'read_at' => null,
            ]);

            if (! filled($recipient->email)) {
                continue;
            }

            try {
                Mail::to($recipient->email)->send(
                    new UrgentSupportNotificationMail(
                        supportRecord: $supportRecord,
                        recipient: $recipient,
                        sender: $sender,
                        teamMenu: $this->formTeamMenu,
                    )
                );
            } catch (\Throwable $mailException) {
                report($mailException);
                Log::warning('긴급 기관 지원 알림 메일 발송 실패', [
                    'recipient_user_id' => $recipient->id,
                    'recipient_email' => $recipient->email,
                    'support_record_id' => $supportRecord->ID,
                    'exception' => $mailException->getMessage(),
                ]);
            }
        }

        $this->dispatch('notifications-updated');
    }

    public function clearSfUpload(): void
    {
        $this->sfUpload = null;
        $this->resetValidation('sfUpload');
    }

    private function mirrorSupportToPotentialDetail(SupportRecord $supportRecord): void
    {
        $target = null;
        $potentialTargetId = (int) ($supportRecord->potential_target_id ?? 0);
        if ($potentialTargetId > 0) {
            $target = CoNewTarget::query()
                ->whereKey($potentialTargetId)
                ->where('IsContract', false)
                ->first();
        }

        if (! $target) {
            $skCode = trim((string) $supportRecord->SK_Code);
            if ($skCode === '') {
                return;
            }

            $target = CoNewTarget::query()
                ->where('AccountCode', $skCode)
                ->where('IsContract', false)
                ->orderByDesc('ID')
                ->first();
        }

        if (! $target) {
            return;
        }

        // 폼에서 입력한 가능성 값이 있으면 CoNewTarget에도 반영
        $possibility = filled($this->formPossibility) ? $this->formPossibility : ($target->Possibility ?: null);
        if (filled($this->formPossibility) && $this->formPossibility !== $target->Possibility) {
            $target->Possibility = $this->formPossibility;
            $target->save();
        }

        $accountBlockLabel = $this->reportMode === 'teacher' ? '[교사 소통내용]' : '[기관 소통내용]';
        $descriptionBlocks = array_filter([
            filled($this->formToAccount) ? $accountBlockLabel.PHP_EOL.$this->formToAccount : null,
            filled($this->formToDepart) ? '[본사/타 부서 공유]'.PHP_EOL.$this->formToDepart : null,
        ]);

        CoNewTargetDetail::query()->create([
            'Year' => (int) date('Y', strtotime($this->formSupportDate)),
            'AccountName' => (string) ($target->AccountName ?? $this->formAccountName),
            'AccountManager' => filled($target->AccountManager) ? $target->AccountManager : ($this->formCoName ?: null),
            'MeetingDate' => $this->formSupportDate,
            'MeetingTime' => $this->formSupportTime,
            'MeetingTime_End' => null,
            'Description' => implode(PHP_EOL.PHP_EOL, $descriptionBlocks),
            'ConsultingType' => $this->formSupportType,
            'Possibility' => $possibility,
        ]);
    }

    public function render(): View
    {
        $keyword = trim($this->formInstitutionKeyword);
        $normalizedKeyword = preg_replace('/\s+/u', '', $keyword) ?? '';

        $institutionSuggestions = collect();
        if ($normalizedKeyword !== '') {
            // Eloquent\Collection::merge()는 항목을 모델로 간주해 getKey()를 호출한다.
            // 배열로 합치려면 일반 Support\Collection으로 바꾼 뒤 merge 한다.
            // SupportList와 동일하게 Institution::search() 사용.
            // 표시명(resolvedAccountName)은 S_Account_Information.Account_Name 우선이므로
            // 마스터 AccountName만 검색하면 한글 지역명(예: 의왕) 검색이 누락된다.
            $institutionSuggestions = collect(
                Institution::query()
                    ->with('accountInfo')
                    ->whereDoesntHave('accountInfo', function ($query): void {
                        $query->where('Customer_Type', 'like', '%해지%');
                    })
                    ->search($keyword)
                    ->orderBy('AccountName')
                    ->limit(8)
                    ->get()
            )->map(fn (Institution $inst): array => [
                'SKcode' => (string) $inst->SKcode,
                'AccountName' => $inst->resolvedAccountName(),
                'is_potential' => false,
                'potential_target_id' => null,
                'dedupe_key' => 'sk:'.(string) $inst->SKcode,
            ]);
        }

        $potentialSuggestions = $this->potentialSuggestions($normalizedKeyword);

        $mergedSuggestions = $institutionSuggestions
            ->merge($potentialSuggestions)
            ->groupBy('dedupe_key')
            ->map(function (Collection $group): array {
                $potentialItem = $group->firstWhere('is_potential', true);
                $item = $potentialItem ?? $group->first();

                return [
                    'SKcode' => (string) ($item['SKcode'] ?? ''),
                    'AccountName' => (string) ($item['AccountName'] ?? ''),
                    'is_potential' => (bool) ($item['is_potential'] ?? false),
                    'potential_target_id' => isset($item['potential_target_id']) ? (int) $item['potential_target_id'] : null,
                    'dedupe_key' => (string) ($item['dedupe_key'] ?? ''),
                ];
            })
            ->sortBy('AccountName', SORT_NATURAL | SORT_FLAG_CASE)
            ->take(8)
            ->values()
            ->map(fn (array $item): object => (object) $item);

        return view('livewire.support-create-form', array_merge([
            'institutionSuggestions' => $mergedSuggestions,
            'supportTypeOptions' => $this->supportTypeOptions(),
            'institutionTeachers' => $this->institutionTeachers(),
            'coachTeacherSupportCreateTypes' => config('coach_teacher_support_create.types', []),
            'crossTeamReadOnly' => TeamMenuContext::isCrossTeamReadOnlyContext(auth()->user(), $this->formTeamMenu),
        ], $this->coachTeacherSupportReportModalConfigs()));
    }

    /**
     * @return list<string>
     */
    private function supportTypeOptions(): array
    {
        $configKey = $this->reportMode === 'teacher'
            ? 'support_report_defaults.teacher_support_types'
            : 'support_report_defaults.institution_support_types';

        $options = config($configKey);

        return is_array($options) ? array_values($options) : [];
    }

    private function syncInlineVisitFormState(): void
    {
        if ($this->formTeamMenu !== 'coach' || $this->reportMode !== 'teacher') {
            return;
        }

        $this->formCoachTeacherCreateAction = $this->defaultCoachTeacherSupportCreateAction();
        $this->formSupportType = '교사 지원 및 참관';
        $this->visitMarkCompleted = $this->formCompleted;

        $teacherId = $this->selectedTeacherId();
        $teacher = $teacherId !== null
            ? $this->findVisibleTeacherForSupportModal($teacherId)
            : null;

        if (! $teacher) {
            $this->visitTeacherId = null;
            $this->visitForm = $this->defaultVisitForm(
                skCode: SkCodeNormalizer::normalize($this->formSkCode) ?? (string) $this->formSkCode,
                coachName: (string) $this->formCoName,
                institutionName: (string) $this->formAccountName,
                teacherName: (string) $this->formTarget,
            );
            $this->resetSupportRoundSelection();

            return;
        }

        $institution = $teacher->institution;
        if (! $institution) {
            $normalizedSkCode = SkCodeNormalizer::normalize($teacher->SK_Code);
            if ($normalizedSkCode) {
                $institution = Institution::query()
                    ->with('accountInfo')
                    ->where('SKcode', $normalizedSkCode)
                    ->first();
            }
        }

        $accountInfo = $institution?->accountInfo;
        $user = auth()->user();
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->visitTeacherId = (int) $teacher->ID;
        $this->visitForm = $this->defaultVisitForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
    }

    private function communicationTemplate(string $field, string $mode): string
    {
        $configKey = $mode === 'teacher'
            ? "support_report_defaults.teacher_{$field}_template"
            : "support_report_defaults.{$field}_template";

        return (string) config($configKey, '');
    }

    private function syncCommunicationTemplatesOnModeChange(string $previousMode): void
    {
        // 기관 이슈 모드로 전환하면 처리 내역은 사용자가 직접 입력한다.
        if ($this->reportMode === 'issue') {
            return;
        }

        foreach (['to_account' => 'formToAccount', 'to_depart' => 'formToDepart'] as $field => $property) {
            $current = $this->{$property};
            $previousTemplate = $this->communicationTemplate($field, $previousMode);

            if ($current === '' || $current === $previousTemplate) {
                $this->{$property} = $this->communicationTemplate($field, $this->reportMode);
            }
        }
    }

    private function findPotentialBySkCode(string $skCode): ?CoNewTarget
    {
        $trimmedSk = trim($skCode);
        if ($trimmedSk === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where('AccountCode', $trimmedSk)
            ->orderByDesc('ID')
            ->first();
    }

    private function findPotentialById(?int $potentialTargetId): ?CoNewTarget
    {
        if ($potentialTargetId === null || $potentialTargetId <= 0) {
            return null;
        }

        return CoNewTarget::query()
            ->whereKey($potentialTargetId)
            ->where('IsContract', false)
            ->first();
    }

    private function findPotentialByKeyword(string $keyword): ?CoNewTarget
    {
        $trimmedKeyword = trim($keyword);
        if ($trimmedKeyword === '') {
            return null;
        }

        return CoNewTarget::query()
            ->where('IsContract', false)
            ->where(function ($query) use ($trimmedKeyword): void {
                $query->where('AccountName', $trimmedKeyword)
                    ->orWhere('AccountCode', $trimmedKeyword);
            })
            ->orderByDesc('ID')
            ->first();
    }

    private function potentialSuggestions(string $normalizedKeyword): Collection
    {
        if ($normalizedKeyword === '') {
            return collect();
        }

        return collect(
            CoNewTarget::query()
                ->where('IsContract', false)
                ->where(function ($query): void {
                    $user = auth()->user();
                    if ($user?->hasFullAccess()) {
                        return;
                    }

                    $query->where('created_by', $user?->id);
                })
                ->where(function ($query) use ($normalizedKeyword): void {
                    $query->whereRaw("REPLACE(AccountName, ' ', '') like ?", ["%{$normalizedKeyword}%"])
                        ->orWhereRaw("REPLACE(IFNULL(AccountCode,''), ' ', '') like ?", ["%{$normalizedKeyword}%"]);
                })
                ->orderBy('AccountName')
                ->limit(8)
                ->get(['ID', 'AccountCode', 'AccountName'])
        )->map(fn (CoNewTarget $target): array => [
            'SKcode' => trim((string) ($target->AccountCode ?? '')),
            'AccountName' => (string) $target->AccountName,
            'is_potential' => true,
            'potential_target_id' => (int) $target->ID,
            'dedupe_key' => filled($target->AccountCode)
                ? 'sk:'.(string) $target->AccountCode
                : 'pot:'.(int) $target->ID,
        ]);
    }

    private function hasInstitutionSelection(): bool
    {
        return filled($this->formSkCode) || $this->formPotentialTargetId !== null;
    }

    private function isTerminatedInstitution(?Institution $institution): bool
    {
        if ($institution === null) {
            return false;
        }

        return str_contains((string) ($institution->accountInfo?->Customer_Type ?? ''), '해지');
    }

    private function resolveUncontractedPotentialTargetId(): ?int
    {
        if ($this->formPotentialTargetId === null || $this->formPotentialTargetId <= 0) {
            return null;
        }

        $target = CoNewTarget::query()
            ->whereKey($this->formPotentialTargetId)
            ->where('IsContract', false)
            ->first();

        if (! $target || ! $this->canManagePotentialTarget($target)) {
            return null;
        }

        return (int) $target->ID;
    }

    private function canManagePotentialTarget(CoNewTarget $target): bool
    {
        $user = auth()->user();

        return $user !== null && $target->isManagedBy($user);
    }

    private function buildSfUploadFileName(string $originalFilename, string $accountName): string
    {
        $fallback = trim($originalFilename) !== '' ? $originalFilename : 'uploaded-file';
        $accountId = $this->resolveSalesforceAccountIdByName($accountName);

        if ($accountId === '') {
            return $fallback;
        }

        if (str_starts_with($fallback, $accountId.'_')) {
            return $fallback;
        }

        return $accountId.'_'.$fallback;
    }

    private function resolveSalesforceAccountIdByName(string $accountName): string
    {
        if (! Schema::hasTable('SF_Account')
            || ! Schema::hasColumn('SF_Account', 'account_ID')
            || ! Schema::hasColumn('SF_Account', 'Name')) {
            return '';
        }

        $trimmedName = trim($accountName);
        if ($trimmedName === '') {
            return '';
        }

        $exact = SalesforceAccount::query()
            ->where('Name', $trimmedName)
            ->whereNotNull('account_ID')
            ->orderByDesc('ID')
            ->value('account_ID');

        if (filled($exact)) {
            return trim((string) $exact);
        }

        $normalizedTarget = $this->normalizeNameForMatch($trimmedName);
        if ($normalizedTarget === '') {
            return '';
        }

        $candidates = SalesforceAccount::query()
            ->select(['account_ID', 'Name'])
            ->whereNotNull('account_ID')
            ->orderByDesc('ID')
            ->limit(1000)
            ->get();

        foreach ($candidates as $candidate) {
            if ($this->normalizeNameForMatch((string) ($candidate->Name ?? '')) === $normalizedTarget) {
                return trim((string) ($candidate->account_ID ?? ''));
            }
        }

        return '';
    }

    private function normalizeNameForMatch(string $value): string
    {
        $normalized = $value;
        if (class_exists(\Normalizer::class)) {
            $normalizedValue = \Normalizer::normalize($value, \Normalizer::FORM_C);
            if (is_string($normalizedValue) && $normalizedValue !== '') {
                $normalized = $normalizedValue;
            }
        }

        $lower = mb_strtolower($normalized);

        return preg_replace('/[^\p{L}\p{N}]/u', '', $lower) ?? $lower;
    }
}
