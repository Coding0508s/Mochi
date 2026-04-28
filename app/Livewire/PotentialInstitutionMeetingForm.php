<?php

namespace App\Livewire;

use App\Actions\CreatePotentialMeetingDetail;
use App\Models\CoNewTarget;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class PotentialInstitutionMeetingForm extends Component
{
    public int $coNewTargetId;

    public string $meetingDate = '';

    public string $meetingTime = '';

    public string $meetingTimeEnd = '';

    public string $consultingType = '';

    public string $possibility = '';

    public string $description = '';

    public string $accountManager = '';

    public function mount(int $coNewTargetId): void
    {
        $this->coNewTargetId = $coNewTargetId;
        $this->resetFormDefaults();
    }

    private function resetFormDefaults(): void
    {
        $target = CoNewTarget::query()->find($this->coNewTargetId);
        $this->meetingDate = now()->format('Y-m-d');
        $this->meetingTime = '';
        $this->meetingTimeEnd = '';
        $this->consultingType = $target ? (string) ($target->Gubun ?? '') : '';
        $this->possibility = $target ? (string) ($target->Possibility ?? '') : '';
        $this->description = '';
        $this->accountManager = $target && filled($target->AccountManager)
            ? (string) $target->AccountManager
            : (string) (auth()->user()?->name ?? '');
    }

    public function save(CreatePotentialMeetingDetail $createMeeting): void
    {
        $validated = $this->validate();
        $target = CoNewTarget::query()->findOrFail($this->coNewTargetId);

        try {
            $createMeeting($target, [
                'meeting_date' => $validated['meetingDate'],
                'meeting_time' => $validated['meetingTime'] ?: null,
                'meeting_time_end' => $validated['meetingTimeEnd'] ?: null,
                'description' => $validated['description'] ?: null,
                'consulting_type' => $validated['consultingType'],
                'possibility' => $validated['possibility'] ?: null,
                'account_manager' => $validated['accountManager'] ?: null,
            ]);
        } catch (AuthorizationException $e) {
            $this->addError('meetingForm', $e->getMessage());

            return;
        } catch (Throwable $e) {
            report($e);
            $this->addError('meetingForm', '저장 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');

            return;
        }

        $this->resetFormDefaults();
        $this->resetValidation();
        $this->dispatch('potential-meeting-saved', targetId: $this->coNewTargetId);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'meetingDate' => ['required', 'date'],
            'meetingTime' => ['nullable', 'date_format:H:i'],
            'meetingTimeEnd' => ['nullable', 'date_format:H:i'],
            'consultingType' => ['required', 'string', 'max:100'],
            'possibility' => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string', 'max:2000'],
            'accountManager' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function render(): View
    {
        return view('livewire.potential-institution-meeting-form');
    }
}
