<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class VisitSupportReportValidationPresenter
{
    public static function livewireField(string $validatorKey): string
    {
        return str_starts_with($validatorKey, 'visitForm.')
            ? $validatorKey
            : "visitForm.{$validatorKey}";
    }

    public static function alertMessage(ValidationException $exception): string
    {
        $lines = collect($exception->errors())->flatten()->unique()->values();

        if ($lines->isEmpty()) {
            return '필수 입력 항목을 확인해 주세요.';
        }

        return "필수 입력 항목이 누락되었습니다.\n\n".$lines->implode("\n");
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'support_date.required' => '지원 날짜를 입력해 주세요.',
            'support_date.date' => '올바른 지원 날짜 형식이 아닙니다.',
            'support_purpose.required' => '지원 목적을 입력해 주세요.',
            'monitoring_feedback.required' => '세부 지원 내용을 입력해 주세요.',
            'interview_and_action_plan.required' => '면담 내용 및 Action Plan을 입력해 주세요.',
            'observe_unit.integer' => 'Unit은 0~99 사이 숫자로 입력해 주세요.',
            'observe_unit.min' => 'Unit은 0~99 사이 숫자로 입력해 주세요.',
            'observe_unit.max' => 'Unit은 0~99 사이 숫자로 입력해 주세요.',
            'observe_lesson.integer' => 'Lesson은 0~99 사이 숫자로 입력해 주세요.',
            'observe_lesson.min' => 'Lesson은 0~99 사이 숫자로 입력해 주세요.',
            'observe_lesson.max' => 'Lesson은 0~99 사이 숫자로 입력해 주세요.',
            'session_number.integer' => 'Session은 1~9 사이 숫자로 입력해 주세요.',
            'session_number.min' => 'Session은 1~9 사이 숫자로 입력해 주세요.',
            'session_number.max' => 'Session은 1~9 사이 숫자로 입력해 주세요.',
        ];
    }
}
