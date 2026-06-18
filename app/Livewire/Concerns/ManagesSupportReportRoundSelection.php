<?php

namespace App\Livewire\Concerns;

use App\Models\Teacher;
use App\Support\ExcelSerialDate;
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
        $referenceYear = $this->supportRoundReferenceYear();
        $recommendedRound = $this->firstRecommendedRound($teacher, $referenceYear, $this->supportRoundRecorded);
        $firstEmpty = TeacherSupportSlotSync::firstEmptyRound($teacher);
        $defaultRound = $recommendedRound ?? $firstEmpty;
        $this->supportRound = $defaultRound !== null ? (string) $defaultRound : '';
    }

    protected function resetSupportRoundSelection(): void
    {
        $this->supportRound = '';
        $this->supportRoundRecorded = [];
    }

    protected function seedSupportRoundSelectionForEdit(?int $fallbackTeacherId = null): void
    {
        $teacherId = $fallbackTeacherId ?? $this->activeSupportReportTeacherId();
        if ($teacherId === null || $teacherId <= 0) {
            return;
        }

        $teacher = Teacher::query()->find($teacherId);
        if ($teacher !== null) {
            $this->seedSupportRoundSelection($teacher);
        }
    }

    protected function activeSupportReportTeacherId(): ?int
    {
        foreach ([
            'visitTeacherId',
            'onsiteTeacherId',
            'demoLessonTeacherId',
            'lvaFrTeacherId',
            'lvaFbTeacherId',
            'lsOnsiteLvaTeacherId',
            'littleseedConTeacherId',
            'proConTeacherId',
            'openClassTeacherId',
            'unit21PlusTeacherId',
            'unit31PlusTeacherId',
        ] as $property) {
            if (! property_exists($this, $property)) {
                continue;
            }

            $teacherId = (int) ($this->{$property} ?? 0);
            if ($teacherId > 0) {
                return $teacherId;
            }
        }

        return null;
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

    /**
     * @return list<array{value:string,label:string,disabled:bool,recommended:bool}>
     */
    public function supportRoundOptions(): array
    {
        $teacherId = $this->activeSupportReportTeacherId();
        if ($teacherId === null || $teacherId <= 0) {
            return $this->defaultSupportRoundOptions();
        }

        $teacher = Teacher::query()->find($teacherId);
        if ($teacher === null) {
            return $this->defaultSupportRoundOptions();
        }

        $recordedRounds = TeacherSupportSlotSync::recordedRounds($teacher);
        $referenceYear = $this->supportRoundReferenceYear();
        $columns = config('coach_teacher_support.columns');

        $options = [];
        foreach ([1, 2, 3, 4] as $round) {
            $planColumn = $columns['plan_'.$this->roundSuffix($round)] ?? null;
            $planDate = $planColumn !== null
                ? ExcelSerialDate::parse($teacher->getRawOriginal($planColumn))
                : null;
            $recommended = $referenceYear !== null
                && $planDate !== null
                && $planDate->year === $referenceYear;
            $disabled = in_array($round, $recordedRounds, true);

            $label = $round.'차';
            $tags = [];
            if ($recommended) {
                $tags[] = '해당 연도 계획';
            }
            if ($disabled) {
                $tags[] = '기록됨';
            }
            if ($tags !== []) {
                $label .= ' ('.implode(', ', $tags).')';
            }

            $options[] = [
                'value' => (string) $round,
                'label' => $label,
                'disabled' => $disabled,
                'recommended' => $recommended,
            ];
        }

        return $options;
    }

    public function supportRoundReferenceYear(): ?int
    {
        $supportDate = $this->activeSupportReportSupportDate();
        $parsed = ExcelSerialDate::parse($supportDate);

        return $parsed?->year;
    }

    /**
     * @return list<array{value:string,label:string,disabled:bool,recommended:bool}>
     */
    private function defaultSupportRoundOptions(): array
    {
        return collect([1, 2, 3, 4])
            ->map(fn (int $round): array => [
                'value' => (string) $round,
                'label' => $round.'차',
                'disabled' => false,
                'recommended' => false,
            ])
            ->values()
            ->all();
    }

    private function firstRecommendedRound(Teacher $teacher, ?int $referenceYear, array $recordedRounds): ?int
    {
        if ($referenceYear === null) {
            return null;
        }

        $columns = config('coach_teacher_support.columns');
        foreach ([1, 2, 3, 4] as $round) {
            if (in_array($round, $recordedRounds, true)) {
                continue;
            }

            $planColumn = $columns['plan_'.$this->roundSuffix($round)] ?? null;
            if (! is_string($planColumn) || $planColumn === '') {
                continue;
            }

            $planDate = ExcelSerialDate::parse($teacher->getRawOriginal($planColumn));
            if ($planDate !== null && $planDate->year === $referenceYear) {
                return $round;
            }
        }

        return null;
    }

    private function activeSupportReportSupportDate(): mixed
    {
        $candidates = [
            ['teacher_id' => 'visitTeacherId', 'form' => 'visitForm'],
            ['teacher_id' => 'onsiteTeacherId', 'form' => 'onsiteForm'],
            ['teacher_id' => 'demoLessonTeacherId', 'form' => 'demoLessonForm'],
            ['teacher_id' => 'lvaFrTeacherId', 'form' => 'lvaFrForm'],
            ['teacher_id' => 'lvaFbTeacherId', 'form' => 'lvaFbForm'],
            ['teacher_id' => 'lsOnsiteLvaTeacherId', 'form' => 'lsOnsiteLvaForm'],
            ['teacher_id' => 'littleseedConTeacherId', 'form' => 'littleseedConForm'],
            ['teacher_id' => 'proConTeacherId', 'form' => 'proConForm'],
            ['teacher_id' => 'openClassTeacherId', 'form' => 'openClassForm'],
            ['teacher_id' => 'unit21PlusTeacherId', 'form' => 'unit21PlusForm'],
            ['teacher_id' => 'unit31PlusTeacherId', 'form' => 'unit31PlusForm'],
        ];

        foreach ($candidates as $candidate) {
            $teacherIdProperty = $candidate['teacher_id'];
            $formProperty = $candidate['form'];

            if (! property_exists($this, $teacherIdProperty) || ! property_exists($this, $formProperty)) {
                continue;
            }

            $teacherId = (int) ($this->{$teacherIdProperty} ?? 0);
            if ($teacherId <= 0) {
                continue;
            }

            $form = $this->{$formProperty};
            if (! is_array($form)) {
                continue;
            }

            return $form['support_date'] ?? null;
        }

        return null;
    }

    private function roundSuffix(int $round): string
    {
        return match ($round) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            default => '',
        };
    }
}
