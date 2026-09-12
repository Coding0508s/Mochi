<?php

namespace App\Livewire\Concerns;

use App\Support\VisitObserveCurriculumRows;

trait ManagesVisitObserveCurriculumRows
{
    public function addVisitObserveRow(string $type): void
    {
        $this->visitForm['observe_rows'] = VisitObserveCurriculumRows::add(
            $this->visitForm['observe_rows'] ?? [],
            $type,
        );
    }

    public function removeVisitObserveRow(int $index): void
    {
        $this->visitForm['observe_rows'] = VisitObserveCurriculumRows::remove(
            $this->visitForm['observe_rows'] ?? [],
            $index,
        );
    }

    /**
     * @return list<string>
     */
    public function visitObserveAddableTypes(): array
    {
        return VisitObserveCurriculumRows::addableTypes($this->visitForm['observe_rows'] ?? []);
    }
}
