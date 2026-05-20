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
use App\Actions\UpdateTeacherProfile;
use App\Actions\UpdateTeacherSupport;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\Teacher;
use App\Support\CoachTeacherScope;
use App\Support\SkCodeNormalizer;
use App\Support\TeacherSupportHistoryAggregator;
use App\Support\TeacherSupportHistoryDetailResolver;
use App\Support\TeacherSupportHistoryFormLoader;
use App\Support\TeacherSupportKpiCalculator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class CoachTeacherSupportList extends Component
{
    use WithPagination;

    public int $filterYear;

    public string $filterRound = '';

    public string $filterMonth = '';

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

    public bool $teacherModalEditMode = false;

    public array $teacherProfileForm = [];

    public bool $confirmingRetire = false;

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

    public function mount(): void
    {
        $this->filterYear = (int) (config('coach_teacher_support.default_year') ?? now()->year);
    }

    public function openInstitutionModal(string $skCode): void
    {
        $normalizedSkCode = SkCodeNormalizer::normalize($skCode) ?? $skCode;
        $candidateSkCodes = SkCodeNormalizer::candidates($skCode);

        $institution = Institution::query()
            ->whereIn('SKcode', $candidateSkCodes)
            ->first();

        if (! $institution) {
            return;
        }

        $accountInfo = $institution->accountInfo;

        $this->institutionInfo = [
            'sk_code' => $normalizedSkCode,
            'name' => $institution->AccountName,
            'address' => $institution->Address ?? '',
            'co' => $accountInfo?->CO ?? '',
            'tr' => $accountInfo?->TR ?? '',
            'cs' => $accountInfo?->CS ?? '',
        ];

        try {
            $this->institutionSupportHistory = SupportRecord::query()
                ->whereIn('SK_Code', $candidateSkCodes)
                ->where('Status', '완료')
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
                ])
                ->all();
        } catch (\Throwable) {
            $this->institutionSupportHistory = [];
        }

        try {
            $this->teacherSupportHistory = app(TeacherSupportHistoryAggregator::class)
                ->forInstitution($candidateSkCodes, limit: 10);
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
                ->where('ClassInOut', true)
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

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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

        $this->teacherDetailInfo = [
            'id' => $teacher->ID,
            'name' => $teacher->Name,
            'email' => $teacher->Email,
            'phone' => $teacher->Phone,
            'position' => $teacher->Position,
            'class_in_out' => (bool) $teacher->ClassInOut,
            'description' => $teacher->Description,
            'sk_code' => SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            'school_name' => $teacher->School_Name ?? $institution?->AccountName,
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

        $expectedSkCode = $this->teacherDetailInfo['sk_code']
            ?? $this->institutionInfo['skcode']
            ?? null;

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

    /**
     * @param  array<string, mixed>  $form
     */
    private function openSupportReportView(string $action, int $teacherId, array $form, bool $markCompleted = true): void
    {
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
            default => $this->supportReportViewMode = false,
        };
    }

    private function endSupportReportViewMode(): void
    {
        $this->supportReportViewMode = false;
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

    private function canViewTeacher(int $teacherId): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->hasFullAccess()) {
            return true;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacherId);
        CoachTeacherScope::apply($scopedQuery, $user);

        return $scopedQuery->exists();
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
        $this->confirmingRetire = true;
    }

    public function cancelRetireTeacher(): void
    {
        $this->confirmingRetire = false;
    }

    public function retireTeacher(): void
    {
        if (! $this->teacherDetailInfo) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $action = new RetireTeacher;
        $action->execute($this->teacherDetailInfo['id'], $user);

        session()->flash('success', '교사가 퇴직 처리되었습니다.');
        $this->closeTeacherModal();
    }

    public function openDemoLessonModal(int $teacherId): void
    {
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->demoLessonTeacherId = $teacherId;
        $this->demoLessonMarkCompleted = true;
        $this->demoLessonForm = $this->defaultDemoLessonForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showDemoLessonModal = true;
    }

    public function closeDemoLessonModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showDemoLessonModal = false;
        $this->demoLessonTeacherId = null;
        $this->demoLessonForm = [];
        $this->demoLessonMarkCompleted = true;
    }

    public function saveDemoLessonReport(): void
    {
        if (! $this->demoLessonTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->demoLessonForm, [
            'mark_completed' => $this->demoLessonMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->lvaFrTeacherId = $teacherId;
        $this->lvaFrMarkCompleted = true;
        $this->lvaFrForm = $this->defaultLvaFrForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showLvaFrModal = true;
    }

    public function closeLvaFrModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showLvaFrModal = false;
        $this->lvaFrTeacherId = null;
        $this->lvaFrForm = [];
        $this->lvaFrMarkCompleted = true;
    }

    public function saveLvaFrReport(): void
    {
        if (! $this->lvaFrTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lvaFrForm, [
            'mark_completed' => $this->lvaFrMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->lvaFbTeacherId = $teacherId;
        $this->lvaFbMarkCompleted = true;
        $this->lvaFbForm = $this->defaultLvaFbForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showLvaFbModal = true;
    }

    public function closeLvaFbModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showLvaFbModal = false;
        $this->lvaFbTeacherId = null;
        $this->lvaFbForm = [];
        $this->lvaFbMarkCompleted = true;
    }

    public function saveLvaFbReport(): void
    {
        if (! $this->lvaFbTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lvaFbForm, [
            'mark_completed' => $this->lvaFbMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->lsOnsiteLvaTeacherId = $teacherId;
        $this->lsOnsiteLvaMarkCompleted = true;
        $this->lsOnsiteLvaForm = $this->defaultLsOnsiteLvaForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showLsOnsiteLvaModal = true;
    }

    public function closeLsOnsiteLvaModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showLsOnsiteLvaModal = false;
        $this->lsOnsiteLvaTeacherId = null;
        $this->lsOnsiteLvaForm = [];
        $this->lsOnsiteLvaMarkCompleted = true;
    }

    public function saveLsOnsiteLvaReport(): void
    {
        if (! $this->lsOnsiteLvaTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->lsOnsiteLvaForm, [
            'mark_completed' => $this->lsOnsiteLvaMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->littleseedConTeacherId = $teacherId;
        $this->littleseedConMarkCompleted = true;
        $this->littleseedConForm = $this->defaultLittleseedConForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showLittleseedConModal = true;
    }

    public function closeLittleseedConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showLittleseedConModal = false;
        $this->littleseedConTeacherId = null;
        $this->littleseedConForm = [];
        $this->littleseedConMarkCompleted = true;
    }

    public function saveLittleseedConReport(): void
    {
        if (! $this->littleseedConTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->littleseedConForm, [
            'mark_completed' => $this->littleseedConMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->openClassTeacherId = $teacherId;
        $this->openClassMarkCompleted = true;
        $this->openClassForm = $this->defaultOpenClassForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showOpenClassModal = true;
    }

    public function closeOpenClassModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showOpenClassModal = false;
        $this->openClassTeacherId = null;
        $this->openClassForm = [];
        $this->openClassMarkCompleted = true;
    }

    public function saveOpenClassReport(): void
    {
        if (! $this->openClassTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->openClassForm, [
            'mark_completed' => $this->openClassMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->unit21PlusTeacherId = $teacherId;
        $this->unit21PlusMarkCompleted = true;
        $this->unit21PlusForm = $this->defaultUnit21PlusForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showUnit21PlusModal = true;
    }

    public function closeUnit21PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showUnit21PlusModal = false;
        $this->unit21PlusTeacherId = null;
        $this->unit21PlusForm = [];
        $this->unit21PlusMarkCompleted = true;
    }

    public function saveUnit21PlusReport(): void
    {
        if (! $this->unit21PlusTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->unit21PlusForm, [
            'mark_completed' => $this->unit21PlusMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->unit31PlusTeacherId = $teacherId;
        $this->unit31PlusMarkCompleted = true;
        $this->unit31PlusForm = $this->defaultUnit31PlusForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showUnit31PlusModal = true;
    }

    public function closeUnit31PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showUnit31PlusModal = false;
        $this->unit31PlusTeacherId = null;
        $this->unit31PlusForm = [];
        $this->unit31PlusMarkCompleted = true;
    }

    public function saveUnit31PlusReport(): void
    {
        if (! $this->unit31PlusTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->unit31PlusForm, [
            'mark_completed' => $this->unit31PlusMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->proConTeacherId = $teacherId;
        $this->proConMarkCompleted = true;
        $this->proConForm = $this->defaultProConForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showProConModal = true;
    }

    public function closeProConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showProConModal = false;
        $this->proConTeacherId = null;
        $this->proConForm = [];
        $this->proConMarkCompleted = true;
    }

    public function saveProConReport(): void
    {
        if (! $this->proConTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->proConForm, [
            'mark_completed' => $this->proConMarkCompleted,
        ]);

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
        $teacher = Teacher::query()
            ->with(['institution.accountInfo'])
            ->find($teacherId);

        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess()) {
            $scopedQuery = Teacher::query()->where('ID', $teacherId);
            CoachTeacherScope::apply($scopedQuery, $user);
            if (! $scopedQuery->exists()) {
                return;
            }
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
        $coachName = $accountInfo?->TR ?? ($user?->nameForCoReports() ?? '');

        $this->onsiteTeacherId = $teacherId;
        $this->onsiteMarkCompleted = true;
        $this->onsiteForm = $this->defaultOnsiteForm(
            skCode: SkCodeNormalizer::normalize($teacher->SK_Code) ?? '',
            coachName: $coachName,
            institutionName: (string) ($teacher->School_Name ?? $institution?->AccountName ?? ''),
            teacherName: (string) $teacher->Name,
        );
        $this->showOnsiteModal = true;
    }

    public function closeOnsiteModal(): void
    {
        $this->endSupportReportViewMode();
        $this->showOnsiteModal = false;
        $this->onsiteTeacherId = null;
        $this->onsiteForm = [];
        $this->onsiteMarkCompleted = true;
    }

    public function saveOnsiteReport(): void
    {
        if (! $this->onsiteTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $payload = array_merge($this->onsiteForm, [
            'mark_completed' => $this->onsiteMarkCompleted,
        ]);

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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterYear(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRound(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMonth(): void
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
        $this->filterRound = '';
        $this->filterMonth = '';
        $this->kpiFilter = '';
        $this->resetPage();
    }

    public function openEditModal(int $id): void
    {
        $teacher = Teacher::find($id);
        if (! $teacher) {
            return;
        }

        $user = auth()->user();
        if (! $user?->hasFullAccess() && ! $this->canEditTeacher($teacher)) {
            return;
        }

        $cols = config('coach_teacher_support.columns');

        $this->editingTeacherId = $id;
        $this->editForm = [
            'plan_1st' => $teacher->{$cols['plan_1st']}?->format('Y-m-d'),
            'plan_2nd' => $teacher->{$cols['plan_2nd']}?->format('Y-m-d'),
            'plan_type_1st' => $teacher->{$cols['plan_type_1st']} ?? '',
            'plan_type_2nd' => $teacher->{$cols['plan_type_2nd']} ?? '',
            'completed_1st' => $teacher->{$cols['completed_1st']}?->format('Y-m-d'),
            'completed_2nd' => $teacher->{$cols['completed_2nd']}?->format('Y-m-d'),
            'completed_3rd' => $teacher->{$cols['completed_3rd']}?->format('Y-m-d'),
            'completed_4th' => $teacher->{$cols['completed_4th']}?->format('Y-m-d'),
            'type_1st' => $teacher->{$cols['type_1st']} ?? '',
            'type_2nd' => $teacher->{$cols['type_2nd']} ?? '',
            'type_3rd' => $teacher->{$cols['type_3rd']} ?? '',
            'type_4th' => $teacher->{$cols['type_4th']} ?? '',
            'essentials_gs' => $teacher->{$cols['essentials_gs']}?->format('Y-m-d'),
            'essentials_ls' => $teacher->{$cols['essentials_ls']}?->format('Y-m-d'),
        ];
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
        if (! $this->editingTeacherId) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            return;
        }

        $action = new UpdateTeacherSupport;
        $action->execute($this->editingTeacherId, $this->editForm, $user);

        session()->flash('success', '지원 일정이 저장되었습니다.');
        $this->closeEditModal();
    }

    public function render()
    {
        $baseQuery = $this->buildBaseQuery();

        $kpis = TeacherSupportKpiCalculator::calculate(clone $baseQuery, $this->filterYear);

        $teachers = (clone $baseQuery)
            ->tap(fn (Builder $q) => $this->applyKpiFilter($q))
            ->tap(fn (Builder $q) => $this->applyRoundFilter($q))
            ->tap(fn (Builder $q) => $this->applyMonthFilter($q))
            ->with(CoachTeacherScope::eagerLoads())
            ->leftJoin('S_AccountName', 'Teachers.SK_Code', '=', 'S_AccountName.SKcode')
            ->orderByRaw('COALESCE(S_AccountName.AccountName, Teachers.School_Name) ASC')
            ->orderBy('Teachers.SK_Code')
            ->orderBy('Teachers.Name')
            ->select('Teachers.*')
            ->paginate(50);

        return view('livewire.coach-teacher-support-list', [
            'teachers' => $teachers,
            'kpis' => $kpis,
            'supportTypes' => config('coach_teacher_support.support_types', []),
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
        ]);
    }

    private function buildBaseQuery(): Builder
    {
        $query = Teacher::query();

        if (! $this->showAllTeachers) {
            $query->where('ClassInOut', true);
        }

        CoachTeacherScope::apply($query);

        if (filled($this->search)) {
            $term = '%'.preg_replace('/\s+/u', '', $this->search).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->whereRaw("REPLACE(Name, ' ', '') LIKE ?", [$term])
                    ->orWhereRaw("REPLACE(School_Name, ' ', '') LIKE ?", [$term])
                    ->orWhere('SK_Code', 'LIKE', $term);
            });
        }

        return $query;
    }

    private function applyKpiFilter(Builder $query): void
    {
        if ($this->kpiFilter === '') {
            return;
        }

        $cols = config('coach_teacher_support.columns');
        $year = $this->filterYear;

        match ($this->kpiFilter) {
            'first_round' => $query->whereNotNull($cols['completed_1st'])
                ->whereYear($cols['completed_1st'], $year),
            'second_round' => $query->whereNotNull($cols['completed_2nd'])
                ->whereYear($cols['completed_2nd'], $year),
            'completed' => $query->whereNotNull($cols['completed_1st'])
                ->whereYear($cols['completed_1st'], $year)
                ->whereNotNull($cols['completed_2nd'])
                ->whereYear($cols['completed_2nd'], $year),
            'unsupported' => $query->where(function (Builder $q) use ($cols, $year): void {
                $q->where(function (Builder $sub) use ($cols, $year): void {
                    $sub->whereNotNull($cols['plan_1st'])
                        ->where(function (Builder $inner) use ($cols, $year): void {
                            $inner->whereNull($cols['completed_1st'])
                                ->orWhereYear($cols['completed_1st'], '!=', $year);
                        });
                })->orWhere(function (Builder $sub) use ($cols, $year): void {
                    $sub->whereNotNull($cols['plan_2nd'])
                        ->where(function (Builder $inner) use ($cols, $year): void {
                            $inner->whereNull($cols['completed_2nd'])
                                ->orWhereYear($cols['completed_2nd'], '!=', $year);
                        });
                });
            }),
            default => null,
        };
    }

    private function applyRoundFilter(Builder $query): void
    {
        if ($this->filterRound === '') {
            return;
        }

        $cols = config('coach_teacher_support.columns');
        $year = $this->filterYear;

        match ($this->filterRound) {
            '1' => $query->whereNotNull($cols['plan_1st']),
            '2' => $query->whereNotNull($cols['plan_2nd']),
            default => null,
        };
    }

    private function applyMonthFilter(Builder $query): void
    {
        if ($this->filterMonth === '') {
            return;
        }

        $cols = config('coach_teacher_support.columns');
        $month = (int) $this->filterMonth;

        $query->where(function (Builder $q) use ($cols, $month): void {
            $q->where(function (Builder $sub) use ($cols, $month): void {
                $sub->whereNotNull($cols['plan_1st'])
                    ->whereMonth($cols['plan_1st'], $month);
            })->orWhere(function (Builder $sub) use ($cols, $month): void {
                $sub->whereNotNull($cols['plan_2nd'])
                    ->whereMonth($cols['plan_2nd'], $month);
            });
        });
    }

    private function canEditTeacher(Teacher $teacher): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        $scopedQuery = Teacher::query()->where('ID', $teacher->ID);
        CoachTeacherScope::apply($scopedQuery, $user);

        return $scopedQuery->exists();
    }
}
