<?php

namespace App\Llm;

use App\Models\Playbook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Anthropic LLM client, bật bằng LLM_DRIVER=anthropic.
 * Gọi Anthropic Messages API để sinh đề xuất, sau đó cắt về phạm vi playbook.
 */
class AnthropicLlmClient implements LlmClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'claude-3-5-sonnet-latest',
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
                : 'Đề xuất do Anthropic sinh cho playbook '.$playbook->name.'.',
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
            throw new \RuntimeException('Thiếu ANTHROPIC_API_KEY khi LLM_DRIVER=anthropic.');
        }

        $response = Http::timeout(30)->withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post(self::API_URL, [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API error ('.$response->status().').');
        }

        $text = $response->json('content.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Anthropic trả về nội dung rỗng.');
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
