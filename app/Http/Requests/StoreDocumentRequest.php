<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FR-VAULT-01: Tải lên tài liệu (PDF/PNG/JPG, tối đa 10 MB).
 * Mã hoá tại trình duyệt trước khi gửi; request chỉ chứa bản mã.
 */
class StoreDocumentRequest extends FormRequest
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240'],
            'iv' => ['required', 'string'],
            'sha256' => ['required', 'string', 'size:64', 'regex:/^[0-9a-f]{64}$/'],
            'doc_type' => ['required', 'string', 'in:' . implode(',', config('trustgate.doc_types'))],
            'title_ct' => ['required', 'string'],
            'enc_mode' => ['required', 'string', 'in:tide,fallback'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'Tài liệu quá lớn (tối đa 10 MB).',
            'doc_type.in' => 'Loại tài liệu không hợp lệ.',
            'enc_mode.in' => 'Chế độ mã hoá không hợp lệ.',
        ];
    }
}
