<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use Illuminate\Http\JsonResponse;

/**
 * FR-QRM-01: đổi trực tiếp quyền/playbook/allowlist/ngưỡng KHÔNG được phép.
 * Mọi endpoint ở đây luôn trả 409 QUORUM_REQUIRED kèm loại CR cần tạo.
 */
class DirectChangeController extends Controller
{
    public function grantRole(): JsonResponse
    {
        return $this->blocked(ChangeRequest::TYPE_ROLE_GRANT);
    }

    public function revokeRole(): JsonResponse
    {
        return $this->blocked(ChangeRequest::TYPE_ROLE_REVOKE);
    }

    public function updatePlaybook(): JsonResponse
    {
        return $this->blocked(ChangeRequest::TYPE_PLAYBOOK_UPDATE);
    }

    public function updateAgentAllowlist(): JsonResponse
    {
        return $this->blocked(ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE);
    }

    public function updateQuorumThreshold(): JsonResponse
    {
        return $this->blocked(ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE);
    }

    private function blocked(string $type): never
    {
        throw new ApiException(
            'QUORUM_REQUIRED',
            'Thay đổi này phải được duyệt qua Change Request (quorum admin).',
            409,
            [
                'change_request_type' => $type,
                'create_endpoint' => '/api/v1/admin/change-requests',
            ],
        );
    }
}
