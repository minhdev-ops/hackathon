<?php

namespace App\Http\Resources;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * FR-VAULT-02: Danh sách tài liệu của Owner. Không trả plaintext,
 * chỉ trả metadata + ciphertext reference (title_ct, iv đã mã hoá ở trình duyệt).
 */
class DocumentResource extends JsonResource
{
    /**
     * @param  Document  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->getKey(),
            'doc_type' => $this->resource->doc_type,
            'title_ct' => $this->resource->title_ct,
            'iv' => $this->resource->iv,
            'sha256' => $this->resource->sha256,
            'size_bytes' => $this->resource->size_bytes,
            'enc_mode' => $this->resource->enc_mode,
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }
}
