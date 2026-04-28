<?php

namespace App\Http\Requests;

use App\Models\Institution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Validator;

class UpsertExternalInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string|ConditionalRules>>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'english_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'portal_account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_no' => ['sometimes', 'nullable', 'string', 'max:100'],
            'gs_no' => ['sometimes', 'nullable', 'string', 'max:100'],
            'director' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account_tel' => ['sometimes', 'nullable', 'string', 'max:100'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'gubun' => ['sometimes', 'nullable', 'string', 'max:100'],
            'possibility' => ['sometimes', 'nullable', 'string', 'max:20'],
            'ls' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'gs_k' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'gs_e' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'co' => ['sometimes', 'nullable', 'string', 'max:255'],
            'tr' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cs' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_type' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $sk = trim(rawurldecode((string) $this->route('sk', '')));
            if ($sk === '') {
                $v->errors()->add('sk', 'SK 경로가 비어 있습니다.');

                return;
            }

            $exists = Institution::query()->where('SKcode', $sk)->exists();
            if ($exists) {
                return;
            }

            $name = $this->input('institution_name');
            if (! is_string($name) || trim($name) === '') {
                $v->errors()->add('institution_name', '신규 기관에는 institution_name 이 필요합니다.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPatch(): array
    {
        return $this->validated();
    }
}
