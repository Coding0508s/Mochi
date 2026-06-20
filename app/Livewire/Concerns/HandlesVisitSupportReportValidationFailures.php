<?php

namespace App\Livewire\Concerns;

use App\Support\VisitSupportReportValidationPresenter;
use Illuminate\Validation\ValidationException;

trait HandlesVisitSupportReportValidationFailures
{
    protected function failVisitReportValidation(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError(VisitSupportReportValidationPresenter::livewireField($field), $message);
            }
        }

        $message = VisitSupportReportValidationPresenter::alertMessage($exception);

        $this->js('alert('.json_encode($message, JSON_UNESCAPED_UNICODE).')');

        $this->dispatch(
            'visit-support-show-alert',
            message: $message,
        );
    }
}
