<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveGrantRequest extends FormRequest
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
            'doc_ids' => ['required', 'array', 'min:1', 'max:50'],
            'doc_ids.*' => ['required', 'string', 'uuid', 'distinct'],
            'ttl_minutes' => ['required', 'integer'],
            'grant_key_b64' => ['required', 'string', 'max:64'],
            'bundle_iv' => ['required', 'string', 'max:64'],
            'bundle' => ['required', 'file', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'doc_ids.required' => 'Cần chọn ít nhất một tài liệu.',
            'doc_ids.min' => 'Cần chọn ít nhất một tài liệu.',
            'doc_ids.*.uuid' => 'doc_ids chứa mã tài liệu không hợp lệ.',
            'doc_ids.*.distinct' => 'Danh sách tài liệu bị trùng.',
            'ttl_minutes.required' => 'Cần chọn thời hạn grant.',
            'ttl_minutes.integer' => 'Thời hạn grant phải là số phút.',
            'grant_key_b64.required' => 'Thiếu khoá grant (grant_key_b64).',
            'bundle_iv.required' => 'Thiếu IV của bundle (bundle_iv).',
            'bundle.required' => 'Thiếu file bundle.',
            'bundle.max' => 'Bundle vượt quá 50 MB.',
        ];
    }
}
