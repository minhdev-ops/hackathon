<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * API error in the shape { "error": { "code": "...", "message": "...", "details": {...} } }.
 */
class ApiException extends HttpException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        int $statusCode,
        private readonly array $details = [],
    ) {
        parent::__construct($statusCode, $message);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function outOfScope(string $message, array $details = []): self
    {
        return new self('OUT_OF_SCOPE', $message, 422, $details);
    }

    /**
     * FR-AGENT-04: agent tự xin tài liệu ngoài phạm vi → 403 (khác với
     * 422 OUT_OF_SCOPE của validation grant ở FR-PB-03).
     *
     * @param  array<string, mixed>  $details
     */
    public static function forbiddenScope(string $message, array $details = []): self
    {
        return new self('OUT_OF_SCOPE', $message, 403, $details);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return $this->details;
    }

    public function render(Request $request): JsonResponse
    {
        $error = [
            'code' => $this->errorCode,
            'message' => $this->getMessage() ?: 'Yêu cầu không hợp lệ.',
        ];

        if ($this->details !== []) {
            $error['details'] = $this->details;
        }

        return response()->json(['error' => $error], $this->getStatusCode());
    }
}
