<?php

namespace App\Llm;

use App\Models\Playbook;

/**
 * Ngôn ngữ mô hình trừu tượng để tạo đề xuất grant và tóm tắt hồ sơ nộp.
 *
 * Implementations:
 *   - MockLlmClient (mặc định): suy ra từ playbook, không gọi mạng.
 *   - AnthropicLlmClient: gọi Anthropic khi LLM_DRIVER=anthropic.
 *   - GeminiLlmClient: gọi Google Gemini khi LLM_DRIVER=gemini.
 */
interface LlmClient
{
    /**
     * @return array{required: list<string>, optional: list<string>, rationale: string}
     */
    public function propose(Playbook $playbook, string $instruction): array;

    /**
     * FR-AGENT-02: tóm tắt «hồ sơ nộp» từ đúng các tài liệu đã được duyệt.
     *
     * @param  list<array<string, mixed>>  $documents
     */
    public function summarize(array $documents): string;
}
