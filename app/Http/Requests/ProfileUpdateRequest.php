<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'english_name' => ['required', 'string', 'max:50'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::in([(string) ($this->user()?->email ?? '')]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '이름을 입력해 주세요.',
            'english_name.required' => '영어 이름을 입력해 주세요.',
            'english_name.max' => '영어 이름은 50자 이하로 입력해 주세요.',
            'email.required' => '이메일은 프로필에서 변경할 수 없습니다. 화면을 새로고침한 뒤 다시 시도해 주세요.',
            'email.email' => '유효한 이메일 형식으로 입력해 주세요.',
            'email.in' => '이메일은 프로필에서 변경할 수 없습니다. 변경이 필요하면 관리자에게 요청해 주세요.',
        ];
    }
}
