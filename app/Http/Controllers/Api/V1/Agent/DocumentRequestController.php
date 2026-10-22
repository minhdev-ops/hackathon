<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRequestController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * FR-AGENT-04: Agent xin tài liệu ngoài phạm vi → 403 OUT_OF_SCOPE và ghi audit.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['nullable', 'uuid'],
            'doc_type' => ['nullable', 'string', 'max:64'],
        ]);

        $taskId = $validated['task_id'] ?? null;

        $this->audit->record(
            'agent.document_request.out_of_scope',
            AuditService::ACTOR_AGENT,
            null,
            'agent_task',
            $taskId,
            [
                'doc_type' => $validated['doc_type'] ?? null,
                'reason' => 'Yêu cầu tài liệu ngoài phạm vi grant/playbook.',
            ],
        );

        throw ApiException::forbiddenScope(
            'Agent không được yêu cầu tài liệu ngoài phạm vi đã duyệt.',
            ['scope' => 'OUT_OF_SCOPE'],
        );
    }
}
