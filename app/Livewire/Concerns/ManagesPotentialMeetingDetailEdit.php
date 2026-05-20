<?php

namespace App\Livewire\Concerns;

use App\Actions\UpdatePotentialMeetingDetail;
use App\Models\CoNewTarget;
use App\Models\CoNewTargetDetail;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

trait ManagesPotentialMeetingDetailEdit
{
    public bool $meetingDetailEditMode = false;

    public string $editMeetingDate = '';

    public string $editMeetingTime = '';

    public string $editMeetingTimeEnd = '';

    public string $editConsultingType = '';

    public string $editPossibility = '';

    public string $editDescription = '';

    public string $editMeetingAccountManager = '';

    public function enterMeetingDetailEditMode(): void
    {
        if ($this->selectedTarget === null || $this->selectedMeeting === null) {
            return;
        }

        if (($this->selectedTarget['is_contract'] ?? false) || ! ($this->selectedTarget['can_manage'] ?? false)) {
            return;
        }

        $this->hydrateMeetingDetailEditFormFromSelectedMeeting();
        $this->meetingDetailEditMode = true;
        $this->resetValidation();
    }

    public function cancelMeetingDetailEdit(): void
    {
        $this->meetingDetailEditMode = false;
        $this->resetMeetingDetailEditForm();
        $this->resetValidation();
    }

    public function saveMeetingDetailEdit(): void
    {
        if ($this->selectedTarget === null || $this->selectedMeeting === null) {
            return;
        }

        $targetId = (int) ($this->selectedTarget['id'] ?? 0);
        $detailId = (int) ($this->selectedMeeting['id'] ?? 0);

        if ($targetId <= 0 || $detailId <= 0) {
            return;
        }

        $validated = $this->validate($this->meetingDetailEditRules(), $this->meetingDetailEditMessages());

        $target = CoNewTarget::query()->find($targetId);
        if (! $target) {
            return;
        }

        try {
            $updated = app(UpdatePotentialMeetingDetail::class)($target, $detailId, [
                'meeting_date' => $validated['editMeetingDate'],
                'meeting_time' => $validated['editMeetingTime'] ?: null,
                'meeting_time_end' => $validated['editMeetingTimeEnd'] ?: null,
                'consulting_type' => $validated['editConsultingType'],
                'possibility' => $validated['editPossibility'] ?: null,
                'description' => $validated['editDescription'] ?: null,
                'account_manager' => $validated['editMeetingAccountManager'] ?: null,
            ]);
        } catch (AuthorizationException $e) {
            $this->addError('meetingDetailEdit', $e->getMessage());

            return;
        } catch (ModelNotFoundException $e) {
            report($e);
            $this->addError('meetingDetailEdit', '수정할 미팅/컨설팅 이력을 찾을 수 없습니다.');

            return;
        }

        $this->meetingDetailEditMode = false;
        $this->resetMeetingDetailEditForm();
        $this->reloadMeetingDetailAfterUpdate($updated);
        session()->flash('success', '미팅/컨설팅 이력을 수정했습니다.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function meetingDetailEditRules(): array
    {
        return [
            'editMeetingDate' => ['required', 'date'],
            'editMeetingTime' => ['nullable', 'date_format:H:i'],
            'editMeetingTimeEnd' => ['nullable', 'date_format:H:i'],
            'editConsultingType' => ['required', 'string', 'max:100'],
            'editPossibility' => ['nullable', 'string', Rule::in(['', 'A', 'B', 'C', 'D'])],
            'editDescription' => ['nullable', 'string', 'max:2000'],
            'editMeetingAccountManager' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function meetingDetailEditMessages(): array
    {
        return [
            'editMeetingDate.required' => '미팅일자를 입력해 주세요.',
            'editMeetingDate.date' => '미팅일자 형식이 올바르지 않습니다.',
            'editMeetingTime.date_format' => '미팅 시작시간 형식이 올바르지 않습니다.',
            'editMeetingTimeEnd.date_format' => '미팅 종료시간 형식이 올바르지 않습니다.',
            'editConsultingType.required' => '컨설팅 유형을 입력해 주세요.',
        ];
    }

    protected function resetMeetingDetailEditState(): void
    {
        $this->meetingDetailEditMode = false;
        $this->resetMeetingDetailEditForm();
    }

    protected function resetMeetingDetailEditForm(): void
    {
        $this->editMeetingDate = '';
        $this->editMeetingTime = '';
        $this->editMeetingTimeEnd = '';
        $this->editConsultingType = '';
        $this->editPossibility = '';
        $this->editDescription = '';
        $this->editMeetingAccountManager = '';
    }

    protected function hydrateMeetingDetailEditFormFromSelectedMeeting(): void
    {
        if ($this->selectedMeeting === null) {
            return;
        }

        $this->editMeetingDate = $this->displayMeetingValueToEditString($this->selectedMeeting['meeting_date'] ?? '');
        $this->editMeetingTime = $this->displayMeetingValueToEditString($this->selectedMeeting['meeting_time'] ?? '');
        $this->editMeetingTimeEnd = $this->displayMeetingValueToEditString($this->selectedMeeting['meeting_time_end'] ?? '');
        $this->editConsultingType = $this->displayMeetingValueToEditString($this->selectedMeeting['consulting_type'] ?? '');
        $this->editPossibility = $this->displayMeetingValueToEditString($this->selectedMeeting['possibility'] ?? '');
        $this->editDescription = $this->displayMeetingValueToEditString($this->selectedMeeting['description'] ?? '');
        $this->editMeetingAccountManager = $this->displayMeetingValueToEditString($this->selectedMeeting['account_manager'] ?? '');
    }

    protected function displayMeetingValueToEditString(mixed $value): string
    {
        $stringValue = trim((string) $value);

        return $stringValue === '-' ? '' : $stringValue;
    }

    abstract protected function reloadMeetingDetailAfterUpdate(CoNewTargetDetail $detail): void;
}
