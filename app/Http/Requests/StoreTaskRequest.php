<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'playbook_slug' => ['required', 'string', 'exists:playbooks,slug'],
            'instruction' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'playbook_slug.required' => 'Cần chọn một lĩnh vực (playbook).',
            'playbook_slug.exists' => 'Lĩnh vực không hợp lệ.',
            'instruction.required' => 'Cần nhập chỉ dẫn cho agent.',
            'instruction.min' => 'Chỉ dẫn quá ngắn, vui lòng nhập thêm chi tiết.',
        ];
    }
}
