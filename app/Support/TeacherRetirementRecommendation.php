<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class TeacherRetirementRecommendation
{
    public function __construct(
        public readonly bool $recommendYn,
        public readonly ?string $recommendDescription,
    ) {}

    public static function fromForm(string $choice, ?string $description): self
    {
        if ($choice === 'yes') {
            return new self(true, trim((string) $description));
        }

        return new self(
            false,
            config('coach_retired_teachers.recommendation.default_description_when_no'),
        );
    }

    /**
     * @param  callable(): bool  $recommendYes
     * @return array<string, mixed>
     */
    public static function livewireRules(callable $recommendYes): array
    {
        return [
            'retireRecommendChoice' => ['required', Rule::in(['yes', 'no'])],
            'retireRecommendDescription' => [
                Rule::requiredIf($recommendYes),
                'nullable',
                'string',
                'max:190',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function livewireMessages(): array
    {
        return [
            'retireRecommendChoice.required' => '추천 여부를 선택해 주세요.',
            'retireRecommendChoice.in' => '추천 여부를 선택해 주세요.',
            'retireRecommendDescription.required' => '추천 사유를 입력해 주세요.',
            'retireRecommendDescription.max' => '추천 사유는 190자 이내로 입력해 주세요.',
        ];
    }
}
