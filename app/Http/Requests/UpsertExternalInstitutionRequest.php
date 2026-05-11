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

    protected function prepareForValidation(): void
    {
        $current = $this->input('institution_name');
        if (is_string($current) && trim($current) !== '') {
            return;
        }

        foreach (['institutionName', 'account_name', 'accountName', 'name'] as $alt) {
            $value = $this->input($alt);
            if (is_string($value) && trim($value) !== '') {
                $this->merge(['institution_name' => trim($value)]);

                return;
            }
        }

        $data = $this->input('data');
        if (is_array($data)) {
            foreach (['institution_name', 'institutionName', 'name'] as $nestedKey) {
                if (! isset($data[$nestedKey]) || ! is_string($data[$nestedKey])) {
                    continue;
                }
                $v = trim($data[$nestedKey]);
                if ($v !== '') {
                    $this->merge(['institution_name' => $v]);

                    return;
                }
            }
        }
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
            'portal_campus_id' => ['sometimes', 'nullable', 'string', 'max:100'],
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
            'replaces_sk' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._\-]+$/'],
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
            $replacesSk = $this->replacesSk();

            if ($replacesSk !== null && $replacesSk === $sk) {
                $v->errors()->add('replaces_sk', '등록 대상 기관 SK와 등록된 기관 SK가 같습니다.');

                return;
            }

            if ($exists) {
                if ($replacesSk !== null) {
                    $v->errors()->add('replaces_sk', '이미 존재하는 SK에는 replaces_sk를 사용할 수 없습니다.');
                }

                return;
            }

            if ($replacesSk !== null) {
                if (! Institution::query()->where('SKcode', $replacesSk)->exists()) {
                    $v->errors()->add('replaces_sk', '치환 대상 SK가 기관 목록에 없습니다.');
                }

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
        return collect($this->validated())
            ->except('replaces_sk')
            ->all();
    }

    public function replacesSk(): ?string
    {
        $replacesSk = $this->input('replaces_sk');
        if (! is_string($replacesSk)) {
            return null;
        }

        $replacesSk = trim($replacesSk);

        return $replacesSk === '' ? null : $replacesSk;
    }
}
