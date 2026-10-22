<?php

namespace App\Llm;

use App\Models\Playbook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Gemini LLM client, bật bằng LLM_DRIVER=gemini.
 * Gọi Google Gemini 1.5 Flash/Pro để sinh đề xuất, sau đó cắt về phạm vi playbook.
 * Cần GEMINI_API_KEY (đặt trong .env, không commit).
 */
class GeminiLlmClient implements LlmClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-1.5-flash-latest',
    ) {}

    /**
     * @return array{required: list<string>, optional: list<string>, rationale: string}
     */
    public function propose(Playbook $playbook, string $instruction): array
    {
        $allowed = array_values(array_unique([
            ...($playbook->required_doc_types ?? []),
            ...($playbook->optional_doc_types ?? []),
        ]));

        $text = $this->send($this->buildProposalPrompt($playbook, $instruction, $allowed));
        $parsed = $this->decodeJson($text);

        $required = $this->onlyAllowed((array) ($parsed['required'] ?? []), $allowed);
        $optional = $this->onlyAllowed((array) ($parsed['optional'] ?? []), $allowed);

        return [
            'required' => $required,
            'optional' => array_values(array_diff($optional, $required)),
            'rationale' => is_string($parsed['rationale'] ?? null) && trim($parsed['rationale']) !== ''
                ? $parsed['rationale']
                : 'Đề xuất do Gemini sinh cho playbook '.$playbook->name.'.',
        ];
    }

    public function summarize(array $documents): string
    {
        $lines = [];

        foreach ($documents as $document) {
            $type = is_string($document['doc_type'] ?? null) ? $document['doc_type'] : 'unknown';
            $name = is_string($document['name'] ?? null) ? $document['name'] : $type;
            $content = is_string($document['content_b64'] ?? null)
                ? (string) base64_decode($document['content_b64'], true)
                : '';

            $lines[] = "- {$type} ({$name}): ".Str::limit($content, 1000);
        }

        $text = $this->send(
            "Bạn là trợ lý soạn hồ sơ nộp thay người dùng. Dưới đây là các tài liệu đã được Owner duyệt.\n"
            ."Hãy tóm tắt ngắn gọn thành «hồ sơ nộp» bằng tiếng Việt, chỉ dùng dữ liệu trong các tài liệu này.\n\n"
            .implode("\n", $lines),
        );

        return trim($text);
    }

    private function send(string $prompt): string
    {
        if (trim($this->apiKey) === '') {
            throw new \RuntimeException('Thiếu GEMINI_API_KEY khi LLM_DRIVER=gemini.');
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$this->model.':generateContent?key='.$this->apiKey;

        $response = Http::timeout(30)->withHeaders([
            'content-type' => 'application/json',
        ])->post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => 1024,
                'temperature' => 0.2,
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API error ('.$response->status().').');
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Gemini trả về nội dung rỗng.');
        }

        return $text;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $text = trim($text);

        if (preg_match('/\{.*\}/s', $text, $matches) === 1) {
            $text = $matches[0];
        }

        $parsed = json_decode($text, true);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param  array<int, mixed>  $types
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function onlyAllowed(array $types, array $allowed): array
    {
        return array_values(array_unique(array_filter(
            $types,
            fn ($type) => is_string($type) && in_array($type, $allowed, true),
        )));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function buildProposalPrompt(Playbook $playbook, string $instruction, array $allowed): string
    {
        $allowedList = implode(', ', $allowed);
        $requiredList = implode(', ', $playbook->required_doc_types ?? []);
        $optionalList = implode(', ', $playbook->optional_doc_types ?? []);

        return <<<PROMPT
Bạn là trợ lý soạn đề xuất cấp quyền cho TrustGate.

Playbook: {$playbook->name} ({$playbook->slug})
Loại tài liệu cho phép: {$allowedList}
Bắt buộc: {$requiredList}
Tuỳ chọn: {$optionalList}
Chỉ dẫn của Owner: {$instruction}

Chỉ trả về JSON duy nhất với các khóa: required (danh sách loại tài liệu bắt buộc), optional (danh sách loại tài liệu tuỳ chọn), rationale (giải thích ngắn). Chỉ dùng các loại tài liệu trong danh sách cho phép.
PROMPT;
    }
}
