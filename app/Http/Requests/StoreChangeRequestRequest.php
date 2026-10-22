<?php

namespace App\Http\Requests;

use App\Support\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChangeRequestRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(ChangeRequestType::all())],
            'payload' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Cần chọn loại Change Request.',
            'type.in' => 'Loại Change Request không hợp lệ.',
            'payload.required' => 'Thiếu payload cho Change Request.',
            'payload.array' => 'Payload phải là một object JSON.',
        ];
    }
}
