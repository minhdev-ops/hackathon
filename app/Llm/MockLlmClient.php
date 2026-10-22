<?php

namespace App\Llm;

use App\Models\Playbook;
use Illuminate\Support\Str;

/**
 * Mock LLM: suy ra đề xuất từ playbook, không gọi mạng (mặc định).
 * Đề xuất bị cắt về phạm vi playbook trước khi lưu.
 */
class MockLlmClient implements LlmClient
{
    /**
     * @return array{required: list<string>, optional: list<string>, rationale: string}
     */
    public function propose(Playbook $playbook, string $instruction): array
    {
        $required = array_values(array_unique($playbook->required_doc_types ?? []));
        $optional = array_values(array_unique($playbook->optional_doc_types ?? []));

        $rationale = match ($playbook->slug) {
            'rental' => 'Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập để xử lý hồ sơ thuê nhà.',
            'clinic' => 'Phòng khám cần thẻ bảo hiểm và tóm tắt bệnh án để khám, điều trị.',
            default => 'Dựa trên chỉ dẫn của Owner và yêu cầu của playbook.',
        };

        return [
            'required' => $required,
            'optional' => $optional,
            'rationale' => $rationale.' Chỉ dẫn: '.$instruction,
        ];
    }

    public function summarize(array $documents): string
    {
        $types = array_values(array_filter(array_map(
            fn (array $document) => is_string($document['doc_type'] ?? null) ? $document['doc_type'] : null,
            $documents,
        )));

        $fields = [];

        foreach ($documents as $document) {
            $type = $document['doc_type'] ?? 'unknown';
            $content = $this->decodeContent($document['content_b64'] ?? null);

            if ($content !== null) {
                $fields[$type] = Str::limit($content, 120);
            }
        }

        $fieldText = $fields === []
            ? 'chưa trích được trường dữ liệu'
            : collect($fields)
                ->map(fn (string $value, string $type) => "{$type}: {$value}")
                ->implode('; ');

        return 'Hồ sơ nộp gồm '.count($documents).' tài liệu ('.implode(', ', $types).'). '
            .'Trường đã trích — '.$fieldText.'.';
    }

    private function decodeContent(mixed $contentB64): ?string
    {
        if (! is_string($contentB64)) {
            return null;
        }

        $decoded = base64_decode($contentB64, true);

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }
}
