<?php

namespace App\Livewire\Concerns;

use App\Models\Teacher;
use App\Support\TeacherSupportSlotSync;

trait ManagesSupportReportRoundSelection
{
    /** @var ''|'1'|'2'|'3'|'4' */
    public string $supportRound = '';

    /** @var list<int> */
    public array $supportRoundRecorded = [];

    protected function seedSupportRoundSelection(Teacher $teacher): void
    {
        $teacher->refresh();
        $this->supportRoundRecorded = TeacherSupportSlotSync::recordedRounds($teacher);
        $firstEmpty = TeacherSupportSlotSync::firstEmptyRound($teacher);
        $this->supportRound = $firstEmpty !== null ? (string) $firstEmpty : '';
    }

    protected function resetSupportRoundSelection(): void
    {
        $this->supportRound = '';
        $this->supportRoundRecorded = [];
    }

    /**
     * @return array{support_round?: int}
     */
    protected function supportReportRoundPayload(bool $markCompleted): array
    {
        if (! $markCompleted || $this->supportRound === '') {
            return [];
        }

        return ['support_round' => (int) $this->supportRound];
    }
}
