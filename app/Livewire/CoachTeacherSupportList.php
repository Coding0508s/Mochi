<?php

namespace App\Livewire;

use App\Actions\RetireTeacher;
use App\Actions\StoreTeacherDemoLessonSupportReport;
use App\Actions\StoreTeacherLittleseedConSupportReport;
use App\Actions\StoreTeacherLsOnsiteLvaSupportReport;
use App\Actions\StoreTeacherLvaFbSupportReport;
use App\Actions\StoreTeacherLvaFrSupportReport;
use App\Actions\StoreTeacherOnsiteSupportReport;
use App\Actions\StoreTeacherOpenClassSupportReport;
use App\Actions\StoreTeacherProConSupportReport;
use App\Actions\StoreTeacherUnit21PlusSupportReport;
use App\Actions\StoreTeacherUnit31PlusSupportReport;
use App\Actions\StoreTeacherVisitSupportReport;
use App\Actions\UpdateLegacyTeacherSupportReport;
use App\Actions\UpdateTeacherProfile;
use App\Actions\UpdateTeacherSupport;
use App\Actions\UpdateTeacherSupportReport;
use App\Livewire\Concerns\GuardsCrossTeamReadOnlyContext;
use App\Livewire\Concerns\HandlesVisitSupportReportValidationFailures;
use App\Livewire\Concerns\ManagesSupportReportRoundSelection;
use App\Models\AccountInformation;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CoachTeacherScope;
use App\Support\CoachTeamKpiAggregator;
use App\Support\ExcelSerialDate;
use App\Support\InstitutionResolver;
use App\Support\ManagerNameNormalizer;
use App\Support\SkCodeNormalizer;
use App\Support\TeacherRetirementRecommendation;
use App\Support\TeacherSupportCompletionDisplay;
use App\Support\TeacherSupportHistoryAggregator;
use App\Support\TeacherSupportHistoryDetailResolver;
use App\Support\TeacherSupportHistoryFormLoader;
use App\Support\TeacherSupportKpiCalculator;
use App\Support\TeacherSupportListActivity;
use App\Support\TeacherSupportReportEditAuthorization;
use App\Support\TeamMenuContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithPagination;

class CoachTeacherSupportList extends Component
{
    use GuardsCrossTeamReadOnlyContext;
    use HandlesVisitSupportReportValidationFailures;
    use ManagesSupportReportRoundSelection;
    use WithPagination;

    public string $filterYear = '';

    public string $filterRound = '';

    public string $filterMonth = '';

    public string $filterCoach = '';

    public bool $showAllInstitutionsView = false;

    public string $search = '';

    public string $kpiFilter = '';

    public bool $showExtendedColumns = false;

    public bool $showAllTeachers = false;

    public bool $showEditModal = false;

    public ?int $editingTeacherId = null;

    public array $editForm = [];

    public bool $showInstitutionModal = false;

    public ?array $institutionInfo = null;

    public array $institutionSupportHistory = [];

    public array $teacherSupportHistory = [];

    public array $institutionContacts = [];

    public bool $showTeacherModal = false;

    public ?array $teacherDetailInfo = null;

    public array $teacherDetailHistory = [];

    public bool $showTeacherSupportHistoryDetailModal = false;

    public ?array $selectedTeacherSupportHistoryDetail = null;

    public bool $supportReportViewMode = false;

    public ?string $viewingSupportReportDetailKey = null;

    /** @var array<int, bool>|null */
    private ?array $editModalAllowedByTeacherId = null;

    public bool $teacherModalEditMode = false;

    public array $teacherProfileForm = [];

    public bool $confirmingRetire = false;

    public string $retireRecommendChoice = 'no';

    public string $retireRecommendDescription = '';

    public bool $showDemoLessonModal = false;

    public ?int $demoLessonTeacherId = null;

    public array $demoLessonForm = [];

    public bool $demoLessonMarkCompleted = true;

    public bool $showLvaFrModal = false;

    public ?int $lvaFrTeacherId = null;

    public array $lvaFrForm = [];

    public bool $lvaFrMarkCompleted = true;

    public bool $showLvaFbModal = false;

    public ?int $lvaFbTeacherId = null;

    public array $lvaFbForm = [];

    public bool $lvaFbMarkCompleted = true;

    public bool $showLsOnsiteLvaModal = false;

    public ?int $lsOnsiteLvaTeacherId = null;

    public array $lsOnsiteLvaForm = [];

    public bool $lsOnsiteLvaMarkCompleted = true;

    public bool $showLittleseedConModal = false;

    public ?int $littleseedConTeacherId = null;

    public array $littleseedConForm = [];

    public bool $littleseedConMarkCompleted = true;

    public bool $showOnsiteModal = false;

    public ?int $onsiteTeacherId = null;

    public array $onsiteForm = [];

    public bool $onsiteMarkCompleted = true;

    public bool $showProConModal = false;

    public ?int $proConTeacherId = null;

    public array $proConForm = [];

    public bool $proConMarkCompleted = true;

    public bool $showOpenClassModal = false;

    public ?int $openClassTeacherId = null;

    public array $openClassForm = [];

    public bool $openClassMarkCompleted = true;

    public bool $showUnit21PlusModal = false;

    public ?int $unit21PlusTeacherId = null;

    public array $unit21PlusForm = [];

    public bool $unit21PlusMarkCompleted = true;

    public bool $showUnit31PlusModal = false;

    public ?int $unit31PlusTeacherId = null;

    public array $unit31PlusForm = [];

    public bool $unit31PlusMarkCompleted = true;

    public bool $showVisitModal = false;

    public ?int $visitTeacherId = null;

    public array $visitForm = [];

    public bool $visitMarkCompleted = true;

    public bool $crossTeamReadOnly = false;

    public function mount(): void
    {
        $this->crossTeamReadOnly = $this->isCrossTeamReadOnlyContext(TeamMenuContext::MENU_COACH);

        $year = request()->query('filterYear');
        $this->filterYear = is_numeric($year) ? (string) (int) $year : '';

        $coach = request()->query('filterCoach');
        if (is_string($coach) && filled($coach)) {
            $this->filterCoach = $coach;
        }

        $month = request()->query('filterMonth');
        if (is_string($month) && $month !== '' && (int) $month >= 1 && (int) $month <= 12) {
            $this->filterMonth = (string) (int) $month;
        }

        $this->maybeOpenCreateSupportFromQuery();

        $this->filterCoach = $this->resolveAllowedFilterCoach();
    }

    private function maybeOpenCreateSupportFromQuery(): void
    {
        $teacherId = request()->integer('teacher_id');
        $action = request()->query('create_action');

        if ($teacherId <= 0 || ! is_string($action) || $action === '') {
            return;
        }

        if ($this->crossTeamReadOnly) {
            return;
        }

        $allowedActions = collect(config('coach_teacher_support_create.types', []))
            ->map(fn (array|string $pill): ?string => is_array($pill) ? ($pill['action'] ?? null) : null)
            ->filter(fn (?string $value): bool => filled($value))
            ->values()
            ->all();

        if (! in_array($action, $allowedActions, true)) {
            return;
        }

        $methodMap = [
            'demo_lesson' => 'openDemoLessonModal',
            'lva_fr' => 'openLvaFrModal',
            'lva_fb' => 'openLvaFbModal',
            'ls_onsite_lva' => 'openLsOnsiteLvaModal',
            'littleseed_con' => 'openLittleseedConModal',
            'onsite' => 'openOnsiteModal',
            'pro_con' => 'openProConModal',
            'open_class' => 'openOpenClassModal',
            'unit21_plus' => 'openUnit21PlusModal',
            'unit31_plus' => 'openUnit31PlusModal',
            'visit' => 'openVisitModal',
        ];

        $method = $methodMap[$action] ?? null;
        if ($method === null || ! method_exists($this, $method)) {
            return;
        }

        $this->{$method}($teacherId);
    }

    public function openInstitutionModal(string $skCode): void
    {
        $normalizedSkCode = SkCodeNormalizer::normalize($skCode) ?? $skCode;
        $candidateSkCodes = SkCodeNormalizer::candidates($skCode);

        if (! $this->canViewInstitutionSk($skCode)) {
            return;
        }

        $institution = InstitutionResolver::resolve($candidateSkCodes);

        if (! $institution) {
            return;
        }

        $accountInfo = $institution->accountInfo;

        $this->institutionInfo = [
            'sk_code' => $normalizedSkCode,
            'name' => $institution->resolvedAccountName(),
            'address' => trim((string) ($institution->Address ?? '')) !== ''
                ? $institution->Address
                : ($accountInfo?->Address ?? ''),
            'co' => $accountInfo?->CO ?? '',
            'tr' => $accountInfo?->TR ?? '',
            'cs' => $accountInfo?->CS ?? '',
            'is_terminated' => $institution->isTerminatedCustomer(),
        ];

        try {
            $this->institutionSupportHistory = SupportRecord::query()
                ->whereIn('SK_Code', $candidateSkCodes)
                ->completed()
                ->orderByDesc('Support_Date')
                ->limit(10)
                ->get(['ID', 'TR_Name', 'Support_Date', 'Support_Type', 'Issue', 'Status'])
                ->map(fn ($r) => [
                    'id' => $r->ID,
                    'coach' => $r->TR_Name,
                    'date' => $r->Support_Date?->format('n/j/Y H:i:s'),
                    'type' => $r->Support_Type,
                    'issue' => $r->Issue,
                    'status' => $r->Status,
                    'detail_key' => 'account:'.$r->ID,
                    'teacher_id' => null,
                ])
                ->all();
        } catch (\Throwable) {
            $this->institutionSupportHistory = [];
        }

        try {
            $this->teacherSupportHistory = app(TeacherSupportHistoryAggregator::class)
                ->forInstitution(
                    $candidateSkCodes,
                    limit: 10,
                    includeRetiredTeachers: $this->showAllTeachers,
                );
        } catch (\Throwable $e) {
            \Log::debug('[InstitutionModal] teacherSupportHistory error: '.$e->getMessage());
            $this->teacherSupportHistory = [];
        }

        try {
            $cols = config('coach_teacher_support.columns', []);
            $selectColumns = array_values(array_unique(array_filter([
                'ID',
                'Name',
                'Position',
                'Phone',
                'Email',
                'GrapeSEEDEssentials',
                'LittleSEEDEssentials',
                $cols['completed_1st'] ?? null,
                $cols['completed_2nd'] ?? null,
                $cols['completed_3rd'] ?? null,
                $cols['completed_4th'] ?? null,
                $cols['type_1st'] ?? null,
                $cols['type_2nd'] ?? null,
                $cols['type_3rd'] ?? null,
                $cols['type_4th'] ?? null,
            ])));

            $this->institutionContacts = Teacher::query()
                ->whereIn('SK_Code', $candidateSkCodes)
                ->tap(fn (Builder $query) => $this->applyTeacherListVisibilityFilter($query))
                ->orderBy('Name')
                ->get($selectColumns)
                ->map(fn (Teacher $teacher) => $this->mapInstitutionContact($teacher, $cols))
                ->all();
        } catch (\Throwable $e) {
            \Log::debug('[InstitutionModal] institutionContacts error: '.$e->getMessage());
            $this->institutionContacts = [];
        }

        $this->showInstitutionModal = true;
    }

    public function closeInstitutionModal(): void
    {
        $this->showInstitutionModal = false;
        $this->institutionInfo = null;
        $this->institutionSupportHistory = [];
        $this->teacherSupportHistory = [];
        $this->institutionContacts = [];
    }

    public function openTeacherModal(int $teacherId): void
    {
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        if (! $this->canViewTeacher($teacherId)) {
            return;
        }

        $institution = InstitutionResolver::resolveForTeacher($teacher);

        $accountInfo = $institution?->accountInfo;

        $this->teacherDetailInfo = [
            'id' => $teacher->ID,
            'name' => $teacher->Name,
            'email' => $teacher->Email,
            'phone' => $teacher->Phone,
            'position' => $teacher->Position,
            'class_in_out' => (bool) $teacher->ClassInOut,
            'description' => $teacher->Description,
            'sk_code' => SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            'school_name' => $this->institutionDisplayName($institution, $teacher->School_Name),
            'is_terminated' => $institution?->isTerminatedCustomer() ?? false,
            'gs_essentials' => $teacher->GrapeSEEDEssentials?->format('Y-m-d'),
            'ls_essentials' => $teacher->LittleSEEDEssentials?->format('Y-m-d'),
            'tr' => $accountInfo?->TR ?? '',
            'cs' => $accountInfo?->CS ?? '',
            'co' => $accountInfo?->CO ?? '',
        ];

        try {
            $this->teacherDetailHistory = app(TeacherSupportHistoryAggregator::class)
                ->forTeacher($teacher);
        } catch (\Throwable $e) {
            \Log::debug('[TeacherModal] teacherDetailHistory error: '.$e->getMessage());
            $this->teacherDetailHistory = [];
        }

        $this->showTeacherModal = true;
    }

    public function closeTeacherModal(): void
    {
        $this->showTeacherModal = false;
        $this->teacherDetailInfo = null;
        $this->teacherDetailHistory = [];
        $this->closeTeacherSupportHistoryDetailModal();
        $this->teacherModalEditMode = false;
        $this->teacherProfileForm = [];
        $this->confirmingRetire = false;
        $this->resetRetireRecommendationForm();
    }

    public function openTeacherSupportHistoryDetail(string $detailKey, ?int $teacherId = null): void
    {
        if ($detailKey === '') {
            return;
        }

        $resolvedTeacherId = $teacherId ?? (int) ($this->teacherDetailInfo['id'] ?? 0);
        $expectedTeacherId = $resolvedTeacherId > 0 ? $resolvedTeacherId : null;

        if ($expectedTeacherId !== null && ! $this->canViewTeacher($expectedTeacherId)) {
            return;
        }

        $expectedSkCode = $this->expectedSupportHistorySkCode();

        $loaded = app(TeacherSupportHistoryFormLoader::class)->load(
            $detailKey,
            $expectedTeacherId,
            $expectedSkCode,
        );

        if ($loaded !== null) {
            $this->closeTeacherSupportHistoryDetailModal();
            $this->openSupportReportView(
                $loaded['action'],
                $loaded['teacher_id'],
                $loaded['form'],
                $loaded['mark_completed'],
                $detailKey,
            );

            return;
        }

        $detail = app(TeacherSupportHistoryDetailResolver::class)->resolve(
            $detailKey,
            $expectedTeacherId,
            $expectedSkCode,
        );

        if ($detail === null) {
            return;
        }

        $this->selectedTeacherSupportHistoryDetail = $detail;
        $this->showTeacherSupportHistoryDetailModal = true;
    }

    public function closeTeacherSupportHistoryDetailModal(): void
    {
        $this->showTeacherSupportHistoryDetailModal = false;
        $this->selectedTeacherSupportHistoryDetail = null;
    }

    private function expectedSupportHistorySkCode(): ?string
    {
        $skCode = $this->teacherDetailInfo['sk_code']
            ?? $this->institutionInfo['sk_code']
            ?? null;

        return filled($skCode) ? (string) $skCode : null;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openSupportReportView(
        string $action,
        int $teacherId,
        array $form,
        bool $markCompleted = true,
        ?string $detailKey = null,
    ): void {
        $this->viewingSupportReportDetailKey = $detailKey;
        $this->supportReportViewMode = true;

        match ($action) {
            'demo_lesson' => $this->openDemoLessonView($teacherId, $form, $markCompleted),
            'lva_fr' => $this->openLvaFrView($teacherId, $form, $markCompleted),
            'lva_fb' => $this->openLvaFbView($teacherId, $form, $markCompleted),
            'ls_onsite_lva' => $this->openLsOnsiteLvaView($teacherId, $form, $markCompleted),
            'littleseed_con' => $this->openLittleseedConView($teacherId, $form, $markCompleted),
            'onsite' => $this->openOnsiteView($teacherId, $form, $markCompleted),
            'pro_con' => $this->openProConView($teacherId, $form, $markCompleted),
            'open_class' => $this->openOpenClassView($teacherId, $form, $markCompleted),
            'unit21_plus' => $this->openUnit21PlusView($teacherId, $form, $markCompleted),
            'unit31_plus' => $this->openUnit31PlusView($teacherId, $form, $markCompleted),
            'visit' => $this->openVisitView($teacherId, $form, $markCompleted),
            default => $this->supportReportViewMode = false,
        };
    }

    private function endSupportReportViewMode(): void
    {
        $this->clearSupportReportViewContext();
    }

    private function clearSupportReportViewContext(): void
    {
        $this->viewingSupportReportDetailKey = null;
        $this->supportReportViewMode = false;
    }

    public function canEditViewingSupportReport(): bool
    {
        if ($this->crossTeamReadOnly) {
            return false;
        }

        if ($this->viewingSupportReportDetailKey === null) {
            return false;
        }

        $parsed = TeacherSupportReportEditAuthorization::parseEditableDetailKey($this->viewingSupportReportDetailKey);
        if ($parsed === null) {
            return false;
        }

        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return Gate::allows('updateTeacherSupportReport', [$parsed['table'], $parsed['id']]);
    }

    public function startSupportReportEdit(): void
    {
        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->canEditViewingSupportReport()) {
            return;
        }

        $this->supportReportViewMode = false;
        $this->seedSupportRoundSelectionForEdit(
            (int) ($this->teacherDetailInfo['id'] ?? 0) ?: null,
        );
    }

    public function cancelSupportReportEdit(): void
    {
        if ($this->viewingSupportReportDetailKey === null) {
            return;
        }

        $detailKey = $this->viewingSupportReportDetailKey;
        $parsed = TeacherSupportReportEditAuthorization::parseEditableDetailKey($detailKey);
        if ($parsed === null) {
            return;
        }

        $teacherId = (int) ($this->teacherDetailInfo['id'] ?? 0);
        $expectedTeacherId = $teacherId > 0 ? $teacherId : null;
        $expectedSkCode = $this->expectedSupportHistorySkCode();

        $loaded = app(TeacherSupportHistoryFormLoader::class)->load(
            $detailKey,
            $expectedTeacherId,
            $expectedSkCode,
        );

        if ($loaded === null) {
            return;
        }

        $this->closeOpenSupportReportModals();
        $this->openSupportReportView(
            $loaded['action'],
            $loaded['teacher_id'],
            $loaded['form'],
            $loaded['mark_completed'],
            $detailKey,
        );
    }

    private function closeOpenSupportReportModals(): void
    {
        $this->showDemoLessonModal = false;
        $this->showLvaFrModal = false;
        $this->showLvaFbModal = false;
        $this->showLsOnsiteLvaModal = false;
        $this->showLittleseedConModal = false;
        $this->showOnsiteModal = false;
        $this->showProConModal = false;
        $this->showOpenClassModal = false;
        $this->showUnit21PlusModal = false;
        $this->showUnit31PlusModal = false;
        $this->showVisitModal = false;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function updateViewingSupportReportIfEditing(array $payload, User $user): bool
    {
        $parsed = $this->viewingSupportReportDetailKey !== null
            ? TeacherSupportReportEditAuthorization::parseEditableDetailKey($this->viewingSupportReportDetailKey)
            : null;

        if ($parsed === null) {
            return false;
        }

        try {
            if ($parsed['source'] === 'legacy') {
                app(UpdateLegacyTeacherSupportReport::class)->execute($parsed['table'], $parsed['id'], $payload, $user);
            } else {
                app(UpdateTeacherSupportReport::class)->execute($parsed['table'], $parsed['id'], $payload, $user);
            }
        } catch (AuthorizationException|InvalidArgumentException $exception) {
            $this->addError('supportReportEdit', $exception->getMessage());

            return true;
        }

        session()->flash('success', '교사 지원 보고서를 수정했습니다.');

        return true;
    }

    /**
     * @param  callable(): void  $closeModal
     */
    private function finishSupportReportPersistence(int $teacherId, callable $closeModal): void
    {
        $closeModal();
        $this->closeTeacherModal();

        if ($teacherId > 0) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openDemoLessonView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->demoLessonTeacherId = $teacherId;
        $this->demoLessonForm = $form;
        $this->demoLessonMarkCompleted = $markCompleted;
        $this->showDemoLessonModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openLvaFrView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->lvaFrTeacherId = $teacherId;
        $this->lvaFrForm = $form;
        $this->lvaFrMarkCompleted = $markCompleted;
        $this->showLvaFrModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openLvaFbView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->lvaFbTeacherId = $teacherId;
        $this->lvaFbForm = $form;
        $this->lvaFbMarkCompleted = $markCompleted;
        $this->showLvaFbModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openLsOnsiteLvaView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->lsOnsiteLvaTeacherId = $teacherId;
        $this->lsOnsiteLvaForm = $form;
        $this->lsOnsiteLvaMarkCompleted = $markCompleted;
        $this->showLsOnsiteLvaModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openLittleseedConView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->littleseedConTeacherId = $teacherId;
        $this->littleseedConForm = $form;
        $this->littleseedConMarkCompleted = $markCompleted;
        $this->showLittleseedConModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openOnsiteView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->onsiteTeacherId = $teacherId;
        $this->onsiteForm = $form;
        $this->onsiteMarkCompleted = $markCompleted;
        $this->showOnsiteModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openProConView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->proConTeacherId = $teacherId;
        $this->proConForm = $form;
        $this->proConMarkCompleted = $markCompleted;
        $this->showProConModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openOpenClassView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->openClassTeacherId = $teacherId;
        $this->openClassForm = $form;
        $this->openClassMarkCompleted = $markCompleted;
        $this->showOpenClassModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openUnit21PlusView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->unit21PlusTeacherId = $teacherId;
        $this->unit21PlusForm = $form;
        $this->unit21PlusMarkCompleted = $markCompleted;
        $this->showUnit21PlusModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openUnit31PlusView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->unit31PlusTeacherId = $teacherId;
        $this->unit31PlusForm = $form;
        $this->unit31PlusMarkCompleted = $markCompleted;
        $this->showUnit31PlusModal = true;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function openVisitView(int $teacherId, array $form, bool $markCompleted): void
    {
        $this->visitTeacherId = $teacherId;
        $this->visitForm = $form;
        $this->visitMarkCompleted = $markCompleted;
        $this->showVisitModal = true;
    }

    private function canViewTeacher(int $teacherId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $scopedQuery = Teacher::query()
            ->where('ID', $teacherId);
        $this->applyTeacherListVisibilityFilter($scopedQuery);

        if (TeamMenuContext::hasExpandedReadScope($user)) {
            return $scopedQuery->exists();
        }

        $this->applyTeacherListScope($scopedQuery, $user);

        return $scopedQuery->exists();
    }

    private function canViewInstitutionSk(string $skCode): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (TeamMenuContext::hasExpandedReadScope($user)) {
            return InstitutionResolver::resolve(SkCodeNormalizer::candidates($skCode)) !== null;
        }

        $scopedQuery = Teacher::query()->whereIn('SK_Code', SkCodeNormalizer::candidates($skCode));
        $this->applyTeacherListVisibilityFilter($scopedQuery);
        $this->applyTeacherListScope($scopedQuery, $user);

        return $scopedQuery->exists();
    }

    private function findVisibleTeacherForSupportModal(int $teacherId): ?Teacher
    {
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher || ! $this->canViewTeacher($teacherId)) {
            return null;
        }

        return $teacher;
    }

    /**
     * @param  array<string, string>  $cols
     * @return array<string, mixed>
     */
    private function mapInstitutionContact(Teacher $teacher, array $cols): array
    {
        $lastSupport = $this->resolveLatestTeacherSupport($teacher, $cols);

        return [
            'id' => $teacher->ID,
            'name' => $teacher->Name,
            'position' => $teacher->Position,
            'phone' => $teacher->Phone,
            'email' => $teacher->Email,
            'gs_essentials' => $teacher->GrapeSEEDEssentials?->format('Y-m-d'),
            'ls_essentials' => $teacher->LittleSEEDEssentials?->format('Y-m-d'),
            'last_support_date' => $lastSupport['date'],
            'last_support_type' => $lastSupport['type'],
        ];
    }

    /**
     * @param  array<string, string>  $cols
     * @return array{date: string, type: string}
     */
    private function resolveLatestTeacherSupport(Teacher $teacher, array $cols): array
    {
        $latestDate = null;
        $latestType = '';

        foreach ([
            ['date' => $cols['completed_1st'] ?? null, 'type' => $cols['type_1st'] ?? null],
            ['date' => $cols['completed_2nd'] ?? null, 'type' => $cols['type_2nd'] ?? null],
            ['date' => $cols['completed_3rd'] ?? null, 'type' => $cols['type_3rd'] ?? null],
            ['date' => $cols['completed_4th'] ?? null, 'type' => $cols['type_4th'] ?? null],
        ] as $slot) {
            if (! filled($slot['date'])) {
                continue;
            }

            $date = $teacher->{$slot['date']};
            if ($date === null) {
                continue;
            }

            if ($latestDate === null || $date->gt($latestDate)) {
                $latestDate = $date;
                $latestType = (string) ($teacher->{$slot['type']} ?? '');
            }
        }

        return [
            'date' => $latestDate?->format('Y-m-d') ?? '',
            'type' => $latestType,
        ];
    }

    public function startTeacherEdit(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->teacherDetailInfo) {
            return;
        }

        $this->teacherProfileForm = [
            'name' => $this->teacherDetailInfo['name'] ?? '',
            'email' => $this->teacherDetailInfo['email'] ?? '',
            'phone' => $this->teacherDetailInfo['phone'] ?? '',
            'position' => $this->teacherDetailInfo['position'] ?? '',
            'description' => $this->teacherDetailInfo['description'] ?? '',
            'class_in_out' => $this->teacherDetailInfo['class_in_out'] ?? true,
        ];
        $this->teacherModalEditMode = true;
    }

    public function saveTeacherProfile(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->teacherDetailInfo) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $action = new UpdateTeacherProfile;
        $action->execute($this->teacherDetailInfo['id'], $this->teacherProfileForm, $user);

        session()->flash('success', '교사 정보가 저장되었습니다.');
        $this->teacherModalEditMode = false;

        $this->openTeacherModal($this->teacherDetailInfo['id']);
    }

    public function confirmRetireTeacher(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $this->resetRetireRecommendationForm();
        $this->confirmingRetire = true;
    }

    public function cancelRetireTeacher(): void
    {
        $this->confirmingRetire = false;
        $this->resetRetireRecommendationForm();
    }

    public function retireTeacher(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->teacherDetailInfo) {
            return;
        }

        $this->validate(
            TeacherRetirementRecommendation::livewireRules(fn (): bool => $this->retireRecommendChoice === 'yes'),
            TeacherRetirementRecommendation::livewireMessages(),
        );

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $recommendation = TeacherRetirementRecommendation::fromForm(
            $this->retireRecommendChoice,
            $this->retireRecommendDescription,
        );

        $action = new RetireTeacher;
        $action->execute($this->teacherDetailInfo['id'], $user, $recommendation);

        session()->flash('success', '교사가 퇴직 처리되었습니다.');
        $this->closeTeacherModal();
    }

    private function resetRetireRecommendationForm(): void
    {
        $this->retireRecommendChoice = 'no';
        $this->retireRecommendDescription = '';
        $this->resetValidation([
            'retireRecommendChoice',
            'retireRecommendDescription',
        ]);
    }

    public function openDemoLessonModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->demoLessonTeacherId = $teacherId;
        $this->demoLessonMarkCompleted = true;
        $this->demoLessonForm = $this->defaultDemoLessonForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showDemoLessonModal = true;
    }

    public function closeDemoLessonModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showDemoLessonModal = false;
        $this->demoLessonTeacherId = null;
        $this->demoLessonForm = [];
        $this->demoLessonMarkCompleted = true;
    }

    public function saveDemoLessonReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->demoLessonTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->demoLessonForm, [
            'mark_completed' => $this->demoLessonMarkCompleted,
        ], $this->supportReportRoundPayload($this->demoLessonMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->demoLessonTeacherId, fn () => $this->closeDemoLessonModal());

            return;
        }

        $action = new StoreTeacherDemoLessonSupportReport;
        $action->execute($this->demoLessonTeacherId, $payload, $user);

        session()->flash('success', $this->demoLessonMarkCompleted
            ? '신규 교사 시연 수업 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->demoLessonTeacherId;
        $this->closeDemoLessonModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultDemoLessonForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $defaultEvaluations = [];
        foreach (array_keys(config('coach_teacher_demo_lesson.evaluation_criteria', [])) as $key) {
            $defaultEvaluations[$key] = 2;
        }

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'progress_unit' => null,
            'progress_lesson' => null,
            'other_notes' => '',
            'procedures' => [],
            'verbal_tools' => [],
            'language_arts_tools' => [],
            'comments_primary' => '',
            'comments_secondary' => '',
            'evaluations' => $defaultEvaluations,
            'overall_comments' => '',
        ];
    }

    public function openLvaFrModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->lvaFrTeacherId = $teacherId;
        $this->lvaFrMarkCompleted = true;
        $this->lvaFrForm = $this->defaultLvaFrForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showLvaFrModal = true;
    }

    public function closeLvaFrModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLvaFrModal = false;
        $this->lvaFrTeacherId = null;
        $this->lvaFrForm = [];
        $this->lvaFrMarkCompleted = true;
    }

    public function saveLvaFrReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->lvaFrTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lvaFrForm, [
            'mark_completed' => $this->lvaFrMarkCompleted,
        ], $this->supportReportRoundPayload($this->lvaFrMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->lvaFrTeacherId, fn () => $this->closeLvaFrModal());

            return;
        }

        $action = new StoreTeacherLvaFrSupportReport;
        $action->execute($this->lvaFrTeacherId, $payload, $user);

        session()->flash('success', $this->lvaFrMarkCompleted
            ? 'LVA+FR 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->lvaFrTeacherId;
        $this->closeLvaFrModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLvaFrForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_lva_fr.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'observe_unit' => null,
            'observe_lesson' => null,
            'observe_class' => '',
            'observe_age' => '',
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_lva_fr.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_lva_fr.method_options.0', '화상'),
            'other_notes' => '',
            'video_length_minutes' => null,
            'procedures' => [],
            'strength_areas' => [],
            'growth_areas' => [],
        ];
    }

    public function openLvaFbModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->lvaFbTeacherId = $teacherId;
        $this->lvaFbMarkCompleted = true;
        $this->lvaFbForm = $this->defaultLvaFbForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showLvaFbModal = true;
    }

    public function closeLvaFbModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLvaFbModal = false;
        $this->lvaFbTeacherId = null;
        $this->lvaFbForm = [];
        $this->lvaFbMarkCompleted = true;
    }

    public function saveLvaFbReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->lvaFbTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lvaFbForm, [
            'mark_completed' => $this->lvaFbMarkCompleted,
        ], $this->supportReportRoundPayload($this->lvaFbMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->lvaFbTeacherId, fn () => $this->closeLvaFbModal());

            return;
        }

        $action = new StoreTeacherLvaFbSupportReport;
        $action->execute($this->lvaFbTeacherId, $payload, $user);

        session()->flash('success', $this->lvaFbMarkCompleted
            ? 'LVA+FB 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->lvaFbTeacherId;
        $this->closeLvaFbModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLvaFbForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_lva_fb.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'observe_unit' => null,
            'observe_lesson' => null,
            'observe_class' => '',
            'observe_age' => '',
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_lva_fb.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_lva_fb.method_options.0', '화상'),
            'other_notes' => '',
            'video_length_minutes' => null,
            'procedures' => [],
            'strength_areas' => [],
            'growth_areas' => [],
        ];
    }

    public function openLsOnsiteLvaModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->lsOnsiteLvaTeacherId = $teacherId;
        $this->lsOnsiteLvaMarkCompleted = true;
        $this->lsOnsiteLvaForm = $this->defaultLsOnsiteLvaForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showLsOnsiteLvaModal = true;
    }

    public function closeLsOnsiteLvaModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLsOnsiteLvaModal = false;
        $this->lsOnsiteLvaTeacherId = null;
        $this->lsOnsiteLvaForm = [];
        $this->lsOnsiteLvaMarkCompleted = true;
    }

    public function saveLsOnsiteLvaReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->lsOnsiteLvaTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lsOnsiteLvaForm, [
            'mark_completed' => $this->lsOnsiteLvaMarkCompleted,
        ], $this->supportReportRoundPayload($this->lsOnsiteLvaMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->lsOnsiteLvaTeacherId, fn () => $this->closeLsOnsiteLvaModal());

            return;
        }

        $action = new StoreTeacherLsOnsiteLvaSupportReport;
        $action->execute($this->lsOnsiteLvaTeacherId, $payload, $user);

        session()->flash('success', $this->lsOnsiteLvaMarkCompleted
            ? 'LS On-Site & LVA 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->lsOnsiteLvaTeacherId;
        $this->closeLsOnsiteLvaModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLsOnsiteLvaForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_ls_onsite_lva.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'observe_set' => null,
            'observe_day' => null,
            'observe_summary_extra' => '',
            'observe_class' => '',
            'observe_age' => '',
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_ls_onsite_lva.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_ls_onsite_lva.method_options.0', '화상'),
            'other_notes' => '',
            'lesson_length_minutes' => null,
            'procedures' => [],
            'teacher_strengths' => '',
            'areas_of_concerns' => '',
            'next_step' => '',
        ];
    }

    public function openLittleseedConModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->littleseedConTeacherId = $teacherId;
        $this->littleseedConMarkCompleted = true;
        $this->littleseedConForm = $this->defaultLittleseedConForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showLittleseedConModal = true;
    }

    public function closeLittleseedConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLittleseedConModal = false;
        $this->littleseedConTeacherId = null;
        $this->littleseedConForm = [];
        $this->littleseedConMarkCompleted = true;
    }

    public function saveLittleseedConReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->littleseedConTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->littleseedConForm, [
            'mark_completed' => $this->littleseedConMarkCompleted,
        ], $this->supportReportRoundPayload($this->littleseedConMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->littleseedConTeacherId, fn () => $this->closeLittleseedConModal());

            return;
        }

        $action = new StoreTeacherLittleseedConSupportReport;
        $action->execute($this->littleseedConTeacherId, $payload, $user);

        session()->flash('success', $this->littleseedConMarkCompleted
            ? 'LittleSEED Con 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->littleseedConTeacherId;
        $this->closeLittleseedConModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLittleseedConForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_littleseed_con.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_littleseed_con.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_littleseed_con.method_options.0', '화상'),
            'procedures' => [],
            'teacher_issue' => '',
            'discussion_content' => '',
            'solution_plan' => '',
        ];
    }

    public function openOpenClassModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->openClassTeacherId = $teacherId;
        $this->openClassMarkCompleted = true;
        $this->openClassForm = $this->defaultOpenClassForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showOpenClassModal = true;
    }

    public function closeOpenClassModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showOpenClassModal = false;
        $this->openClassTeacherId = null;
        $this->openClassForm = [];
        $this->openClassMarkCompleted = true;
    }

    public function saveOpenClassReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->openClassTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->openClassForm, [
            'mark_completed' => $this->openClassMarkCompleted,
        ], $this->supportReportRoundPayload($this->openClassMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->openClassTeacherId, fn () => $this->closeOpenClassModal());

            return;
        }

        $action = new StoreTeacherOpenClassSupportReport;
        $action->execute($this->openClassTeacherId, $payload, $user);

        session()->flash('success', $this->openClassMarkCompleted
            ? 'Open-Class 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->openClassTeacherId;
        $this->closeOpenClassModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultOpenClassForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_open_class.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_open_class.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_open_class.method_options.0', '화상'),
            'progress_unit' => null,
            'progress_lesson' => null,
            'progress_other' => '',
            'procedures' => [],
            'support_content' => [],
            'remarks' => '',
        ];
    }

    public function openUnit21PlusModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->unit21PlusTeacherId = $teacherId;
        $this->unit21PlusMarkCompleted = true;
        $this->unit21PlusForm = $this->defaultUnit21PlusForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showUnit21PlusModal = true;
    }

    public function closeUnit21PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showUnit21PlusModal = false;
        $this->unit21PlusTeacherId = null;
        $this->unit21PlusForm = [];
        $this->unit21PlusMarkCompleted = true;
    }

    public function saveUnit21PlusReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->unit21PlusTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->unit21PlusForm, [
            'mark_completed' => $this->unit21PlusMarkCompleted,
        ], $this->supportReportRoundPayload($this->unit21PlusMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->unit21PlusTeacherId, fn () => $this->closeUnit21PlusModal());

            return;
        }

        $action = new StoreTeacherUnit21PlusSupportReport;
        $action->execute($this->unit21PlusTeacherId, $payload, $user);

        session()->flash('success', $this->unit21PlusMarkCompleted
            ? 'Unit 21+ 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->unit21PlusTeacherId;
        $this->closeUnit21PlusModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUnit21PlusForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_unit21_plus.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_unit21_plus.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_unit21_plus.method_options.0', '화상'),
            'progress_unit' => null,
            'progress_lesson' => null,
            'progress_other' => '',
            'procedures' => [],
            'verbal_materials' => [],
            'language_arts_materials' => [],
            'verbal_comments' => '',
            'language_arts_comments' => '',
            'overall_comments' => '',
        ];
    }

    public function openUnit31PlusModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->unit31PlusTeacherId = $teacherId;
        $this->unit31PlusMarkCompleted = true;
        $this->unit31PlusForm = $this->defaultUnit31PlusForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showUnit31PlusModal = true;
    }

    public function closeUnit31PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showUnit31PlusModal = false;
        $this->unit31PlusTeacherId = null;
        $this->unit31PlusForm = [];
        $this->unit31PlusMarkCompleted = true;
    }

    public function saveUnit31PlusReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->unit31PlusTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->unit31PlusForm, [
            'mark_completed' => $this->unit31PlusMarkCompleted,
        ], $this->supportReportRoundPayload($this->unit31PlusMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->unit31PlusTeacherId, fn () => $this->closeUnit31PlusModal());

            return;
        }

        $action = new StoreTeacherUnit31PlusSupportReport;
        $action->execute($this->unit31PlusTeacherId, $payload, $user);

        session()->flash('success', $this->unit31PlusMarkCompleted
            ? 'Unit 31+ 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->unit31PlusTeacherId;
        $this->closeUnit31PlusModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUnit31PlusForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_unit31_plus.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_unit31_plus.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_unit31_plus.method_options.0', '화상'),
            'progress_unit' => null,
            'progress_lesson' => null,
            'progress_other' => '',
            'procedures' => [],
            'verbal_materials' => [],
            'language_arts_materials' => [],
            'verbal_comments' => '',
            'language_arts_comments' => '',
            'overall_comments' => '',
        ];
    }

    public function openProConModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->proConTeacherId = $teacherId;
        $this->proConMarkCompleted = true;
        $this->proConForm = $this->defaultProConForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showProConModal = true;
    }

    public function closeProConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showProConModal = false;
        $this->proConTeacherId = null;
        $this->proConForm = [];
        $this->proConMarkCompleted = true;
    }

    public function saveProConReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->proConTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->proConForm, [
            'mark_completed' => $this->proConMarkCompleted,
        ], $this->supportReportRoundPayload($this->proConMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->proConTeacherId, fn () => $this->closeProConModal());

            return;
        }

        $action = new StoreTeacherProConSupportReport;
        $action->execute($this->proConTeacherId, $payload, $user);

        session()->flash('success', $this->proConMarkCompleted
            ? 'Pro Con 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->proConTeacherId;
        $this->closeProConModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultProConForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_pro_con.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_pro_con.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_pro_con.method_options.0', '화상'),
            'procedures' => [],
            'teacher_issue' => '',
            'discussion_content' => '',
            'solution_plan' => '',
        ];
    }

    public function openOnsiteModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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

        $this->onsiteTeacherId = $teacherId;
        $this->onsiteMarkCompleted = true;
        $this->onsiteForm = $this->defaultOnsiteForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showOnsiteModal = true;
    }

    public function closeOnsiteModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showOnsiteModal = false;
        $this->onsiteTeacherId = null;
        $this->onsiteForm = [];
        $this->onsiteMarkCompleted = true;
    }

    public function saveOnsiteReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->onsiteTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->onsiteForm, [
            'mark_completed' => $this->onsiteMarkCompleted,
        ], $this->supportReportRoundPayload($this->onsiteMarkCompleted));

        if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
            if ($this->getErrorBag()->isNotEmpty()) {
                return;
            }

            $this->finishSupportReportPersistence((int) $this->onsiteTeacherId, fn () => $this->closeOnsiteModal());

            return;
        }

        $action = new StoreTeacherOnsiteSupportReport;
        $action->execute($this->onsiteTeacherId, $payload, $user);

        session()->flash('success', $this->onsiteMarkCompleted
            ? 'On-Site 지원 보고서가 저장되었습니다.'
            : '임시 저장되었습니다.');

        $teacherId = $this->onsiteTeacherId;
        $this->closeOnsiteModal();
        $this->closeTeacherModal();

        if ($teacherId) {
            $this->openTeacherModal($teacherId);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultOnsiteForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        $experienceOptions = config('coach_teacher_onsite.teacher_experience_options', []);

        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'observe_unit' => null,
            'observe_lesson' => null,
            'observe_summary_extra' => '',
            'observe_class' => '',
            'observe_age' => '',
            'teacher_experience' => $experienceOptions[0] ?? '1-2 Years',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_onsite.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'method' => config('coach_teacher_onsite.method_options.0', '대면'),
            'other_notes' => '',
            'procedures' => [],
            'strength_areas' => [],
            'growth_areas' => [],
        ];
    }

    public function openVisitModal(int $teacherId): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = $this->findVisibleTeacherForSupportModal($teacherId);
        if (! $teacher) {
            return;
        }

        $this->clearSupportReportViewContext();

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
        $this->visitMarkCompleted = true;
        $this->visitForm = $this->defaultVisitForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: $this->institutionDisplayName($institution, $teacher->School_Name),
            teacherName: (string) $teacher->Name,
        );
        $this->seedSupportRoundSelection($teacher);
        $this->showVisitModal = true;
    }

    public function closeVisitModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showVisitModal = false;
        $this->visitTeacherId = null;
        $this->visitForm = [];
        $this->visitMarkCompleted = true;
    }

    public function saveVisitReport(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->visitTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $this->syncVisitSessionFromSupportRound($this->visitMarkCompleted);

        $payload = array_merge($this->visitForm, [
            'mark_completed' => $this->visitMarkCompleted,
        ], $this->supportReportRoundPayload($this->visitMarkCompleted));

        try {
            if ($this->updateViewingSupportReportIfEditing($payload, $user)) {
                if ($this->getErrorBag()->isNotEmpty()) {
                    return;
                }

                $this->finishSupportReportPersistence((int) $this->visitTeacherId, fn () => $this->closeVisitModal());

                return;
            }

            $action = new StoreTeacherVisitSupportReport;
            $action->execute($this->visitTeacherId, $payload, $user);

            session()->flash('success', $this->visitMarkCompleted
                ? '교사 지원 및 참관 보고서가 저장되었습니다.'
                : '임시 저장되었습니다.');

            $teacherId = $this->visitTeacherId;
            $this->closeVisitModal();
            $this->closeTeacherModal();

            if ($teacherId) {
                $this->openTeacherModal($teacherId);
            }
        } catch (ValidationException $exception) {
            $this->failVisitReportValidation($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultVisitForm(
        string $skCode,
        string $coachName,
        string $institutionName,
        string $teacherName,
    ): array {
        return [
            'sk_code' => $skCode,
            'coach_name' => $coachName,
            'institution_name' => $institutionName,
            'teacher_name' => $teacherName,
            'support_date' => now()->format('Y-m-d'),
            'support_location' => '',
            'support_purpose' => '',
            'observe_unit' => null,
            'observe_lesson' => null,
            'observe_summary_extra' => '',
            'observe_class' => '',
            'observe_age' => '',
            'session_number' => 1,
            'semester_label' => config('coach_teacher_visit.semester_options.0', '1학기 지원'),
            'interview_date' => now()->format('Y-m-d'),
            'interview_time' => now()->format('H:i'),
            'meeting_type' => config('coach_teacher_visit.method_options.0', '신규교사 시연수업'),
            'pre_request_notes' => '',
            'monitoring_feedback' => '',
            'interview_and_action_plan' => '',
            'special_notes' => '',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
    {
        $this->resetPage();

        if ($this->filterYear === '') {
            return;
        }

        $maxYear = now()->year;
        $minYear = $maxYear - 10;
        $year = (int) $this->filterYear;

        $this->filterYear = (string) max($minYear, min($maxYear, $year));
    }

    public function updatedFilterRound(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMonth(): void
    {
        $this->resetPage();
    }

    public function updatedFilterCoach(): void
    {
        $this->filterCoach = $this->resolveAllowedFilterCoach();
        $this->resetPage();
    }

    public function updatedShowAllInstitutionsView(): void
    {
        $this->resetPage();
    }

    public function updatedShowAllTeachers(): void
    {
        $this->resetPage();
    }

    public function setKpiFilter(string $filter): void
    {
        $this->kpiFilter = $this->kpiFilter === $filter ? '' : $filter;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterYear = '';
        $this->filterRound = '';
        $this->filterMonth = '';
        $this->filterCoach = '';
        $this->kpiFilter = '';
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function clearRoundFilter(): void
    {
        $this->filterRound = '';
        $this->resetPage();
    }

    public function clearMonthFilter(): void
    {
        $this->filterMonth = '';
        $this->resetPage();
    }

    public function clearKpiFilter(): void
    {
        $this->kpiFilter = '';
        $this->resetPage();
    }

    public function clearCoachFilter(): void
    {
        $this->filterCoach = '';
        $this->resetPage();
    }

    public function openEditModal(int $id): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        $teacher = Teacher::find($id);
        if (! $teacher || ! $this->canOpenEditModalForTeacher($teacher)) {
            return;
        }

        $this->showEditModal = false;
        $this->editingTeacherId = null;
        $this->editForm = [];
        $this->editingTeacherId = $id;
        $this->editForm = $this->buildEditFormFromTeacher($teacher);
        $this->showEditModal = true;
    }

    public function closeEditModal(): void
    {
        $this->showEditModal = false;
        $this->editingTeacherId = null;
        $this->editForm = [];
    }

    public function saveEditForm(): void
    {

        $this->assertCanMutateInTeamContext(TeamMenuContext::MENU_COACH);

        if (! $this->editingTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $action = new UpdateTeacherSupport;
        try {
            $action->execute($this->editingTeacherId, $this->editForm, $user);
        } catch (AuthorizationException $e) {
            $this->addError('editForm', $e->getMessage());

            return;
        }

        session()->flash('success', '지원 일정이 저장되었습니다.');
        $this->closeEditModal();
    }

    public function canOpenEditModal(int $id): bool
    {
        if ($this->editModalAllowedByTeacherId !== null) {
            return $this->editModalAllowedByTeacherId[$id] ?? false;
        }

        $teacher = Teacher::find($id);
        if (! $teacher) {
            return false;
        }

        return $this->canOpenEditModalForTeacher($teacher);
    }

    public function render()
    {
        $this->editModalAllowedByTeacherId = null;

        $baseQuery = $this->buildBaseQuery();

        $this->applyDefaultYearScope($baseQuery);

        $kpiQuery = clone $baseQuery;
        $this->applyRoundFilter($kpiQuery);
        $this->applyMonthFilter($kpiQuery);

        $kpis = TeacherSupportKpiCalculator::calculate($kpiQuery, $this->resolvedFilterYear());
        if ($this->resolvedFilterYear() !== null) {
            $kpis = $this->synchronizeRoundKpisWithDisplay($kpiQuery, $kpis, $this->resolvedFilterYear());
        }

        $teachers = (clone $baseQuery)
            ->tap(fn (Builder $q) => $this->applyKpiFilter($q))
            ->tap(fn (Builder $q) => $this->applyRoundFilter($q))
            ->tap(fn (Builder $q) => $this->applyMonthFilter($q))
            ->with(CoachTeacherScope::eagerLoads())
            ->tap(fn (Builder $q) => $this->applyTeacherListOrdering($q))
            ->select('Teachers.*')
            ->paginate(50);

        TeacherSupportCompletionDisplay::flushRequestCache();
        TeacherSupportCompletionDisplay::preloadForTeachers(
            $teachers->getCollection(),
            $this->resolvedFilterYear(),
        );

        $displayYear = $this->resolvedFilterYear();
        $visibleRound1CompletionCount = $teachers->getCollection()
            ->filter(function (Teacher $teacher) use ($displayYear): bool {
                return TeacherSupportCompletionDisplay::parts($teacher, 1, $displayYear)['date'] !== '';
            })
            ->count();
        $visibleRound2CompletionCount = $teachers->getCollection()
            ->filter(function (Teacher $teacher) use ($displayYear): bool {
                return TeacherSupportCompletionDisplay::parts($teacher, 2, $displayYear)['date'] !== '';
            })
            ->count();

        $this->hydrateTeacherInstitutions($teachers);
        $this->editModalAllowedByTeacherId = $this->buildEditModalAllowedMap($teachers->getCollection());

        return view('livewire.coach-teacher-support-list', [
            'teachers' => $teachers,
            'kpis' => $kpis,
            'yearFilterOptions' => $this->yearFilterOptions(),
            'coachFilterOptions' => $this->coachFilterOptions(),
            'supportTypes' => config('coach_teacher_support.support_types', []),
            'planSupportTypes' => config('coach_teacher_support.plan_support_types', []),
            'completionSupportTypes' => config('coach_teacher_support.completion_support_types', []),
            'demoLessonConfig' => config('coach_teacher_demo_lesson'),
            'lvaFrConfig' => config('coach_teacher_lva_fr'),
            'lvaFbConfig' => config('coach_teacher_lva_fb'),
            'lsOnsiteLvaConfig' => config('coach_teacher_ls_onsite_lva'),
            'littleseedConConfig' => config('coach_teacher_littleseed_con'),
            'onsiteConfig' => config('coach_teacher_onsite'),
            'proConConfig' => config('coach_teacher_pro_con'),
            'openClassConfig' => config('coach_teacher_open_class'),
            'unit21PlusConfig' => config('coach_teacher_unit21_plus'),
            'unit31PlusConfig' => config('coach_teacher_unit31_plus'),
            'visitConfig' => config('coach_teacher_visit'),
            'displayYear' => $this->resolvedFilterYear(),
            'crossTeamReadOnly' => $this->crossTeamReadOnly,
        ]);
    }

    private function resolvedFilterYear(): ?int
    {
        if ($this->filterYear === '') {
            return null;
        }

        return (int) $this->filterYear;
    }

    /**
     * 현재 사용자 스코프에서 실제 지원 데이터(계획/완료/Essentials)에 존재하는 연도 목록.
     *
     * @return Collection<int, int>
     */
    private function yearFilterOptions(): Collection
    {
        $baseQuery = Teacher::query();
        $this->applyTeacherListVisibilityFilter($baseQuery);
        $this->applyTeacherListScope($baseQuery);
        $this->applyCoachFilter($baseQuery);

        $dateColumns = collect(ExcelSerialDate::teacherSupportDateColumns())
            ->filter(fn (string $column): bool => Schema::hasColumn('Teachers', $column))
            ->values();

        if ($dateColumns->isEmpty()) {
            return collect();
        }

        $yearSourceQuery = null;

        foreach ($dateColumns as $column) {
            $qualifiedColumn = "Teachers.{$column}";
            $normalizedDateExpression = ExcelSerialDate::sqlNormalizedDateColumn($qualifiedColumn);
            $extractYearExpression = $this->sqlExtractYearExpression($normalizedDateExpression);

            $columnQuery = (clone $baseQuery)
                ->toBase()
                ->selectRaw("{$extractYearExpression} AS filter_year")
                ->whereRaw(ExcelSerialDate::sqlDateValueIsNotBlank($qualifiedColumn));

            if ($yearSourceQuery === null) {
                $yearSourceQuery = $columnQuery;
            } else {
                $yearSourceQuery->unionAll($columnQuery);
            }
        }

        if ($yearSourceQuery === null) {
            return collect();
        }

        $years = DB::query()
            ->fromSub($yearSourceQuery, 'teacher_support_years')
            ->whereNotNull('filter_year')
            ->where('filter_year', '>=', 1900)
            ->where('filter_year', '<=', now()->year + 1)
            ->distinct()
            ->orderByDesc('filter_year')
            ->pluck('filter_year')
            ->map(fn (mixed $year): int => (int) $year)
            ->values();

        return $years;
    }

    private function sqlExtractYearExpression(string $dateExpression): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%Y', {$dateExpression}) AS INTEGER)",
            default => "YEAR({$dateExpression})",
        };
    }

    /**
     * Teachers.SK_Code가 *SK2693 형태이거나 S_AccountName 행이 없을 때도
     * S_Account_Information 기준으로 institution·해지 스타일을 붙인다.
     *
     * @param  LengthAwarePaginator<Teacher>  $teachers
     */
    private function hydrateTeacherInstitutions($teachers): void
    {
        $collection = $teachers->getCollection();

        $normalizedSkCodes = $collection
            ->map(fn (Teacher $teacher): ?string => SkCodeNormalizer::normalize($teacher->SK_Code))
            ->filter()
            ->unique()
            ->values();

        if ($normalizedSkCodes->isEmpty()) {
            return;
        }

        $institutionsBySk = Institution::query()
            ->with('accountInfo')
            ->whereIn('SKcode', $normalizedSkCodes->all())
            ->get()
            ->keyBy('SKcode');

        $accountInfosBySk = AccountInformation::query()
            ->whereIn('SK_Code', $normalizedSkCodes->all())
            ->get()
            ->keyBy(fn (AccountInformation $info): string => SkCodeNormalizer::normalize($info->SK_Code) ?? $info->SK_Code);

        $collection->each(function (Teacher $teacher) use ($institutionsBySk, $accountInfosBySk): void {
            $normalizedSkCode = SkCodeNormalizer::normalize($teacher->SK_Code);

            if ($normalizedSkCode === null) {
                return;
            }

            $institution = $teacher->institution ?? $institutionsBySk->get($normalizedSkCode);

            if ($institution !== null) {
                $institution->loadMissing('accountInfo');

                if ($institution->accountInfo === null) {
                    $accountInfo = $accountInfosBySk->get($normalizedSkCode);

                    if ($accountInfo !== null) {
                        $institution->setRelation('accountInfo', $accountInfo);
                    }
                }

                $teacher->setRelation('institution', $institution);

                return;
            }

            $accountInfo = $accountInfosBySk->get($normalizedSkCode);

            if ($accountInfo === null) {
                return;
            }

            $teacher->setRelation('institution', InstitutionResolver::fromAccountInformation($accountInfo));
        });

        $teachers->setCollection($collection);
    }

    private function buildBaseQuery(): Builder
    {
        $query = Teacher::query();
        $this->applyTeacherListVisibilityFilter($query);
        $this->applyTeacherListScope($query);

        if (filled($this->search)) {
            $term = '%'.preg_replace('/\s+/u', '', $this->search).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw("REPLACE(Teachers.Name, ' ', '') LIKE ?", [$term])
                    ->orWhereRaw("REPLACE(Teachers.School_Name, ' ', '') LIKE ?", [$term])
                    ->orWhere('Teachers.SK_Code', 'LIKE', $term);
            });
        }

        $this->applyCoachFilter($query);

        if (! $this->showAllInstitutionsView) {
            // 최신 지원 보기의 기본 집합은 "지원 이력 있는 교사"만 유지하되,
            // 연도 필터는 아래 applyDefaultYearScope/applyKpiFilter 등에서 일관되게 처리한다.
            // 여기서 연도를 함께 넣으면 완료/MOCHI 이력 기준으로 먼저 0건이 되어
            // 계획/완료 통합 연도 필터가 무력화될 수 있다.
            TeacherSupportListActivity::applyHasSupportHistoryScope($query, null);
        }

        return $query;
    }

    /**
     * 목록·KPI·필터 옵션에 공통 적용하는 교사 스코프.
     * 기본: TR 담당 기관. 전체 기관 보기 또는 KPI 담당 코치 필터 시 숨김 기관만 제외.
     *
     * @param  Builder<Teacher>  $query
     */
    private function applyTeacherListScope(Builder $query, ?User $user = null): void
    {
        $user ??= auth()->user();

        if (! $user) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($this->usesWideInstitutionListScope($user) || TeamMenuContext::hasExpandedReadScope($user)) {
            CoachTeacherScope::excludeHiddenInstitutions($query);

            return;
        }

        CoachTeacherScope::apply($query, $user);
    }

    private function usesWideInstitutionListScope(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($this->showAllInstitutionsView) {
            return true;
        }

        return $user?->canViewCoachTeamKpi() && filled($this->resolveAllowedFilterCoach());
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    private function applyCoachFilter(Builder $query): void
    {
        $coach = $this->resolveAllowedFilterCoach();

        if (! filled($coach)) {
            return;
        }

        $normalizedCoach = ManagerNameNormalizer::normalize($coach);
        $sqlNormalizedTr = ManagerNameNormalizer::sqlColumnExpression('TR');

        $query->whereHas('institution.accountInfo', function (Builder $sub) use ($normalizedCoach, $sqlNormalizedTr): void {
            $sub->whereRaw("{$sqlNormalizedTr} = ?", [$normalizedCoach]);
        });
    }

    private function resolveAllowedFilterCoach(): string
    {
        if (! filled($this->filterCoach)) {
            return '';
        }

        $user = auth()->user();

        if (! $user) {
            return '';
        }

        if ($user->canViewCoachTeamKpi()) {
            return $this->filterCoach;
        }

        $normalizedFilter = ManagerNameNormalizer::normalize($this->filterCoach);

        foreach (CoachTeacherScope::resolveTrAliases($user) as $alias) {
            if (ManagerNameNormalizer::normalize($alias) === $normalizedFilter) {
                return $this->filterCoach;
            }
        }

        return '';
    }

    /**
     * 현재 사용자 스코프 안에서 실제 담당(TR)이 있는 코치 목록.
     *
     * @return Collection<int, string>
     */
    private function coachFilterOptions(): Collection
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        $scopedTeacherQuery = Teacher::query();
        $this->applyTeacherListVisibilityFilter($scopedTeacherQuery);
        $this->applyTeacherListScope($scopedTeacherQuery, $user);

        $query = AccountInformation::query()
            ->whereIn('SK_Code', $scopedTeacherQuery->select('SK_Code'))
            ->whereNotNull('TR')
            ->where('TR', '!=', '')
            ->distinct();

        if (Schema::hasTable('employee')) {
            $query->whereExists(function (\Illuminate\Database\Query\Builder $subQuery) {
                $subQuery->select(DB::raw(1))
                    ->from('employee')
                    ->where('STATUS', 1)
                    ->where(function (\Illuminate\Database\Query\Builder $nameQuery) {
                        $nameQuery->whereColumn('employee.ENGLISHNAME', 'S_Account_Information.TR')
                            ->orWhereColumn('employee.KOREANAME', 'S_Account_Information.TR');
                    });
            });
        }

        return $query->orderBy('TR')->pluck('TR');
    }

    /**
     * @param  Builder<Teacher>  $query
     */
    private function applyTeacherListOrdering(Builder $query): void
    {
        if (! $this->showAllInstitutionsView) {
            TeacherSupportListActivity::applyLatestSupportOrdering($query, $this->resolvedFilterYear());

            return;
        }

        // S_AccountName에 같은 SKcode가 중복 존재할 수 있어 join 대신
        // 스칼라 서브쿼리로 정렬한다(행 복제 방지).
        $normalizedSkCode = $this->sqlNormalizedTeacherSkCodeExpression();

        $query->orderByRaw(
            'COALESCE((SELECT MIN(san.AccountName) FROM S_AccountName AS san'
            ." WHERE san.SKcode = {$normalizedSkCode}), Teachers.School_Name) ASC"
        )
            ->orderBy('Teachers.SK_Code')
            ->orderBy('Teachers.Name');
    }

    private function sqlNormalizedTeacherSkCodeExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "ltrim(Teachers.SK_Code, '*')",
            default => "TRIM(LEADING '*' FROM Teachers.SK_Code)",
        };
    }

    /**
     * 기본: 퇴직 제외(수업 미참여 포함). showAllTeachers 시 퇴직 교사도 포함.
     *
     * @param  Builder<Teacher>  $query
     */
    private function applyTeacherListVisibilityFilter(Builder $query): void
    {
        if ($this->showAllTeachers) {
            return;
        }

        $query->excludeRetired();
    }

    /**
     * KPI·월·차수·상태 필터가 없을 때 선택 연도에 계획 또는 완료가 있는 교사만 목록·KPI에 포함한다.
     *
     * @param  Builder<Teacher>  $query
     */
    private function applyDefaultYearScope(Builder $query): void
    {
        if ($this->kpiFilter !== '' || $this->filterRound !== '' || $this->filterMonth !== '') {
            return;
        }

        $year = $this->resolvedFilterYear();
        if ($year === null) {
            return;
        }

        CoachTeamKpiAggregator::applyAnySupportYearScope($query, $year);
    }

    private function applyKpiFilter(Builder $query): void
    {
        if ($this->kpiFilter === '') {
            return;
        }

        $year = $this->resolvedFilterYear();

        if ($year === null) {
            TeacherSupportKpiCalculator::applyKpiFilterWithoutYear($query, $this->kpiFilter);

            return;
        }

        match ($this->kpiFilter) {
            'completed' => TeacherSupportKpiCalculator::applyAllRoundsCompletedScope($query, $year),
            'unsupported' => TeacherSupportKpiCalculator::applyUnsupportedScope($query, $year),
            default => $this->applyRoundCompletedDisplayScope($query, $this->kpiFilter, $year),
        };
    }

    private function applyRoundFilter(Builder $query): void
    {
        if ($this->filterRound === '') {
            return;
        }

        $year = $this->resolvedFilterYear();
        if ($year === null) {
            $planColumn = TeacherSupportKpiCalculator::planColumnForFilterRound($this->filterRound);
            if ($planColumn !== null) {
                $query->whereNotNull($planColumn);
            }

            return;
        }

        TeacherSupportKpiCalculator::applyPlanRoundScope($query, $this->filterRound, $year);
    }

    private function applyMonthFilter(Builder $query): void
    {
        if ($this->filterMonth === '') {
            return;
        }

        $month = (int) $this->filterMonth;
        $year = $this->resolvedFilterYear();
        $planRound = $this->filterRound !== '' ? $this->filterRound : '1';
        $planColumn = TeacherSupportKpiCalculator::planColumnForFilterRound($planRound);

        if ($planColumn === null) {
            return;
        }

        $query->whereNotNull($planColumn)
            ->where(function (Builder $nested) use ($planColumn, $year, $month): void {
                if ($year !== null) {
                    ExcelSerialDate::applyWhereYear($nested, $planColumn, $year);
                }

                $nested->whereMonth($planColumn, $month);
            });
    }

    /**
     * @param  array<string, int>  $kpis
     * @return array<string, int>
     */
    private function synchronizeRoundKpisWithDisplay(Builder $query, array $kpis, int $year): array
    {
        $displayCounts = $this->calculateDisplayRoundCounts($query, $year);

        foreach ($displayCounts as $kpiKey => $count) {
            $kpis[$kpiKey] = $count;
        }

        return $kpis;
    }

    /**
     * @return array<string, int>
     */
    private function calculateDisplayRoundCounts(Builder $query, int $year): array
    {
        $teacherRows = (clone $query)
            ->select('Teachers.*')
            ->get();

        TeacherSupportCompletionDisplay::preloadForTeachers($teacherRows, $year);

        $counts = [];
        foreach (config('coach_teacher_support.kpi_rounds', []) as $kpiKey => $round) {
            $roundNumber = (int) ($round['filter_round'] ?? 0);
            if ($roundNumber < 1 || $roundNumber > 4) {
                continue;
            }

            $counts[$kpiKey] = $teacherRows
                ->filter(fn (Teacher $teacher): bool => TeacherSupportCompletionDisplay::parts($teacher, $roundNumber, $year)['date'] !== '')
                ->count();
        }

        return $counts;
    }

    private function applyRoundCompletedDisplayScope(Builder $query, string $kpiKey, int $year): void
    {
        $round = config('coach_teacher_support.kpi_rounds.'.$kpiKey);
        if (! is_array($round)) {
            return;
        }

        $roundNumber = (int) ($round['filter_round'] ?? 0);
        if ($roundNumber < 1 || $roundNumber > 4) {
            return;
        }

        $teacherRows = (clone $query)
            ->select('Teachers.*')
            ->get();

        TeacherSupportCompletionDisplay::preloadForTeachers($teacherRows, $year);

        $matchedTeacherIds = $teacherRows
            ->filter(fn (Teacher $teacher): bool => TeacherSupportCompletionDisplay::parts($teacher, $roundNumber, $year)['date'] !== '')
            ->pluck('ID')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($matchedTeacherIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('Teachers.ID', $matchedTeacherIds);
    }

    private function institutionDisplayName(?Institution $institution, ?string $schoolName = null): string
    {
        $fromInstitution = trim($institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        return trim((string) ($schoolName ?? ''));
    }

    private function canEditTeacher(Teacher $teacher): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $scopedQuery = Teacher::query()
            ->where('ID', $teacher->ID);
        $this->applyTeacherListVisibilityFilter($scopedQuery);
        CoachTeacherScope::apply($scopedQuery, $user);

        return $scopedQuery->exists();
    }

    private function canOpenEditModalForTeacher(Teacher $teacher): bool
    {
        if (! $this->canViewTeacher($teacher->ID)) {
            return false;
        }

        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $institution = $teacher->relationLoaded('institution')
            ? $teacher->institution
            : InstitutionResolver::resolveForTeacher($teacher);

        if ($institution?->isTerminatedCustomer()) {
            return false;
        }

        if ($this->crossTeamReadOnly) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        return $this->canEditTeacher($teacher);
    }

    /**
     * 목록 행마다 canOpenEditModal()을 호출하면 Teacher::find·exists가 N번 발생한다.
     * 현재 페이지 교사 ID만 모아 1~2회 쿼리로 권한 맵을 만든다.
     *
     * @param  Collection<int, Teacher>  $teachers
     * @return array<int, bool>
     */
    private function buildEditModalAllowedMap(Collection $teachers): array
    {
        $user = auth()->user();
        if (! $user || $teachers->isEmpty()) {
            return [];
        }

        $ids = $teachers->pluck('ID')->unique()->values()->all();
        if ($ids === []) {
            return [];
        }

        $viewableQuery = Teacher::query()->whereIn('ID', $ids);
        $this->applyTeacherListVisibilityFilter($viewableQuery);
        if (! TeamMenuContext::hasExpandedReadScope($user)) {
            $this->applyTeacherListScope($viewableQuery, $user);
        }
        $viewableIds = array_fill_keys($viewableQuery->pluck('ID')->all(), true);

        $editableIds = $viewableIds;
        if (! $user->hasFullAccess()) {
            $editableQuery = Teacher::query()->whereIn('ID', $ids);
            $this->applyTeacherListVisibilityFilter($editableQuery);
            CoachTeacherScope::apply($editableQuery, $user);
            $editableIds = array_fill_keys($editableQuery->pluck('ID')->all(), true);
        }

        $map = [];
        foreach ($teachers as $teacher) {
            if (! isset($viewableIds[$teacher->ID])) {
                $map[$teacher->ID] = false;

                continue;
            }

            $institution = $teacher->relationLoaded('institution')
                ? $teacher->institution
                : InstitutionResolver::resolveForTeacher($teacher);

            if ($institution?->isTerminatedCustomer()) {
                $map[$teacher->ID] = false;

                continue;
            }

            if ($user->hasFullAccess()) {
                $map[$teacher->ID] = true;

                continue;
            }

            $map[$teacher->ID] = isset($editableIds[$teacher->ID]);
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function buildEditFormFromTeacher(Teacher $teacher): array
    {
        $cols = config('coach_teacher_support.columns');

        return [
            'plan_1st' => $this->editFormDateValue($teacher, $cols['plan_1st']),
            'plan_2nd' => $this->editFormDateValue($teacher, $cols['plan_2nd']),
            'plan_3rd' => $this->editFormDateValue($teacher, $cols['plan_3rd']),
            'plan_4th' => $this->editFormDateValue($teacher, $cols['plan_4th']),
            'plan_type_1st' => trim((string) ($teacher->{$cols['plan_type_1st']} ?? '')),
            'plan_type_2nd' => trim((string) ($teacher->{$cols['plan_type_2nd']} ?? '')),
            'plan_type_3rd' => trim((string) ($teacher->{$cols['plan_type_3rd']} ?? '')),
            'plan_type_4th' => trim((string) ($teacher->{$cols['plan_type_4th']} ?? '')),
            'completed_1st' => $this->editFormDateValue($teacher, $cols['completed_1st']),
            'completed_2nd' => $this->editFormDateValue($teacher, $cols['completed_2nd']),
            'completed_3rd' => $this->editFormDateValue($teacher, $cols['completed_3rd']),
            'completed_4th' => $this->editFormDateValue($teacher, $cols['completed_4th']),
            'type_1st' => trim((string) ($teacher->{$cols['type_1st']} ?? '')),
            'type_2nd' => trim((string) ($teacher->{$cols['type_2nd']} ?? '')),
            'type_3rd' => trim((string) ($teacher->{$cols['type_3rd']} ?? '')),
            'type_4th' => trim((string) ($teacher->{$cols['type_4th']} ?? '')),
            'essentials_gs' => $this->editFormDateValue($teacher, $cols['essentials_gs']),
            'essentials_ls' => $this->editFormDateValue($teacher, $cols['essentials_ls']),
        ];
    }

    private function editFormDateValue(Teacher $teacher, string $column): string
    {
        $raw = $teacher->getAttributes()[$column] ?? $teacher->getRawOriginal($column);

        return ExcelSerialDate::toStorageString($raw) ?? '';
    }
}
