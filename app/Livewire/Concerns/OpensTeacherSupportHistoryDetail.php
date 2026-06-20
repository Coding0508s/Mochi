<?php

namespace App\Livewire\Concerns;

use App\Support\TeacherSupportHistoryDetailResolver;
use App\Support\TeacherSupportHistoryFormLoader;
use App\Support\TeacherSupportReportEditAuthorization;
use Illuminate\Support\Facades\Gate;

trait OpensTeacherSupportHistoryDetail
{
    use ManagesSupportReportRoundSelection;

    public bool $showTeacherSupportHistoryDetailModal = false;

    public ?array $selectedTeacherSupportHistoryDetail = null;

    public bool $supportReportViewMode = false;

    public ?string $viewingSupportReportDetailKey = null;

    public bool $showDemoLessonModal = false;

    public ?int $demoLessonTeacherId = null;

    /** @var array<string, mixed> */
    public array $demoLessonForm = [];

    public bool $demoLessonMarkCompleted = true;

    public bool $showLvaFrModal = false;

    public ?int $lvaFrTeacherId = null;

    /** @var array<string, mixed> */
    public array $lvaFrForm = [];

    public bool $lvaFrMarkCompleted = true;

    public bool $showLvaFbModal = false;

    public ?int $lvaFbTeacherId = null;

    /** @var array<string, mixed> */
    public array $lvaFbForm = [];

    public bool $lvaFbMarkCompleted = true;

    public bool $showLsOnsiteLvaModal = false;

    public ?int $lsOnsiteLvaTeacherId = null;

    /** @var array<string, mixed> */
    public array $lsOnsiteLvaForm = [];

    public bool $lsOnsiteLvaMarkCompleted = true;

    public bool $showLittleseedConModal = false;

    public ?int $littleseedConTeacherId = null;

    /** @var array<string, mixed> */
    public array $littleseedConForm = [];

    public bool $littleseedConMarkCompleted = true;

    public bool $showOnsiteModal = false;

    public ?int $onsiteTeacherId = null;

    /** @var array<string, mixed> */
    public array $onsiteForm = [];

    public bool $onsiteMarkCompleted = true;

    public bool $showProConModal = false;

    public ?int $proConTeacherId = null;

    /** @var array<string, mixed> */
    public array $proConForm = [];

    public bool $proConMarkCompleted = true;

    public bool $showOpenClassModal = false;

    public ?int $openClassTeacherId = null;

    /** @var array<string, mixed> */
    public array $openClassForm = [];

    public bool $openClassMarkCompleted = true;

    public bool $showUnit21PlusModal = false;

    public ?int $unit21PlusTeacherId = null;

    /** @var array<string, mixed> */
    public array $unit21PlusForm = [];

    public bool $unit21PlusMarkCompleted = true;

    public bool $showUnit31PlusModal = false;

    public ?int $unit31PlusTeacherId = null;

    /** @var array<string, mixed> */
    public array $unit31PlusForm = [];

    public bool $unit31PlusMarkCompleted = true;

    public bool $showVisitModal = false;

    public ?int $visitTeacherId = null;

    /** @var array<string, mixed> */
    public array $visitForm = [];

    public bool $visitMarkCompleted = true;

    abstract protected function expectedSupportHistorySkCodeForDetail(): ?string;

    public function openTeacherSupportHistoryDetail(string $detailKey, ?int $teacherId = null): void
    {
        if ($detailKey === '') {
            return;
        }

        $expectedSkCode = $this->expectedSupportHistorySkCodeForDetail();
        $expectedTeacherId = ($teacherId !== null && $teacherId > 0) ? $teacherId : null;

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

    public function closeAllTeacherSupportReportModals(): void
    {
        $this->closeTeacherSupportHistoryDetailModal();
        $this->closeOpenSupportReportModals();
        $this->clearSupportReportViewContext();
    }

    public function canEditViewingSupportReport(): bool
    {
        if (! $this->allowsEditingViewingSupportReport()) {
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
        if (! $this->canEditViewingSupportReport()) {
            return;
        }

        $this->supportReportViewMode = false;
        $this->seedSupportRoundSelectionForEdit();
    }

    public function closeDemoLessonModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showDemoLessonModal = false;
        $this->demoLessonTeacherId = null;
        $this->demoLessonForm = [];
        $this->demoLessonMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeLvaFrModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLvaFrModal = false;
        $this->lvaFrTeacherId = null;
        $this->lvaFrForm = [];
        $this->lvaFrMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeLvaFbModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLvaFbModal = false;
        $this->lvaFbTeacherId = null;
        $this->lvaFbForm = [];
        $this->lvaFbMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeLsOnsiteLvaModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLsOnsiteLvaModal = false;
        $this->lsOnsiteLvaTeacherId = null;
        $this->lsOnsiteLvaForm = [];
        $this->lsOnsiteLvaMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeLittleseedConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showLittleseedConModal = false;
        $this->littleseedConTeacherId = null;
        $this->littleseedConForm = [];
        $this->littleseedConMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeOnsiteModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showOnsiteModal = false;
        $this->onsiteTeacherId = null;
        $this->onsiteForm = [];
        $this->onsiteMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeProConModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showProConModal = false;
        $this->proConTeacherId = null;
        $this->proConForm = [];
        $this->proConMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeOpenClassModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showOpenClassModal = false;
        $this->openClassTeacherId = null;
        $this->openClassForm = [];
        $this->openClassMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeUnit21PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showUnit21PlusModal = false;
        $this->unit21PlusTeacherId = null;
        $this->unit21PlusForm = [];
        $this->unit21PlusMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeUnit31PlusModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showUnit31PlusModal = false;
        $this->unit31PlusTeacherId = null;
        $this->unit31PlusForm = [];
        $this->unit31PlusMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    public function closeVisitModal(): void
    {
        $this->endSupportReportViewMode();
        $this->resetSupportRoundSelection();
        $this->showVisitModal = false;
        $this->visitTeacherId = null;
        $this->visitForm = [];
        $this->visitMarkCompleted = true;
        $this->afterCoachTeacherSupportModalClosed();
    }

    protected function afterCoachTeacherSupportModalClosed(): void {}

    protected function allowsEditingViewingSupportReport(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $form
     */
    protected function openSupportReportView(
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

    /**
     * @return array<string, mixed>
     */
    protected function coachTeacherSupportReportModalConfigs(): array
    {
        return [
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
        ];
    }

    protected function endSupportReportViewMode(): void
    {
        $this->clearSupportReportViewContext();
    }

    protected function clearSupportReportViewContext(): void
    {
        $this->viewingSupportReportDetailKey = null;
        $this->supportReportViewMode = false;
    }

    protected function closeOpenSupportReportModals(): void
    {
        $this->resetSupportRoundSelection();
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
}
