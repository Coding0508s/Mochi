<?php

namespace App\Livewire\Concerns;

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
use App\Actions\UpdateLegacyTeacherSupportReport;
use App\Actions\UpdateTeacherSupportReport;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SkCodeNormalizer;
use App\Support\TeacherSupportReportEditAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

trait ManagesCoachTeacherSupportCreateModals
{
    public function openCoachTeacherSupportCreateModal(string $action, int $teacherId): void
    {
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
        ];

        $method = $methodMap[$action] ?? null;
        if ($method === null || ! method_exists($this, $method)) {
            return;
        }

        $this->{$method}($teacherId);
    }

    abstract protected function findVisibleTeacherForSupportModal(int $teacherId): ?Teacher;

    abstract protected function finalizeCoachTeacherSupportReportSave(int $teacherId, callable $closeModal): void;

    protected function institutionDisplayName(?Institution $institution, ?string $schoolName = null): string
    {
        $fromInstitution = trim($institution?->resolvedAccountName() ?? '');
        if ($fromInstitution !== '') {
            return $fromInstitution;
        }

        return trim((string) ($schoolName ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function updateViewingSupportReportIfEditing(array $payload, User $user): bool
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
    protected function finishSupportReportPersistence(int $teacherId, callable $closeModal): void
    {
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }

        $this->finalizeCoachTeacherSupportReportSave($teacherId, $closeModal);
    }

    public function openDemoLessonModal(int $teacherId): void
    {
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

        $this->demoLessonTeacherId = (int) $teacherId;
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

        $teacherId = (int) $this->demoLessonTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeDemoLessonModal());
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

        $teacherId = (int) $this->lvaFrTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeLvaFrModal());
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

        $teacherId = (int) $this->lvaFbTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeLvaFbModal());
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

        $teacherId = (int) $this->lsOnsiteLvaTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeLsOnsiteLvaModal());
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

        $teacherId = (int) $this->littleseedConTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeLittleseedConModal());
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

        $teacherId = (int) $this->openClassTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeOpenClassModal());
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

        $teacherId = (int) $this->unit21PlusTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeUnit21PlusModal());
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

        $teacherId = (int) $this->unit31PlusTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeUnit31PlusModal());
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

        $teacherId = (int) $this->proConTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeProConModal());
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

        $teacherId = (int) $this->onsiteTeacherId;
        $this->finalizeCoachTeacherSupportReportSave($teacherId, fn () => $this->closeOnsiteModal());
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
}
