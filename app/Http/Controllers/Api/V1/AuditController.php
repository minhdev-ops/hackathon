<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Grant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        $query = AuditLog::query()->orderByDesc('id');

        if ($user->isOwner()) {
            $this->scopeToOwner($query, $user->getKey());
        }

        if (is_string($action = $request->query('action')) && $action !== '') {
            $query->where('action', $action);
        }

        if (is_string($subjectType = $request->query('subject_type')) && $subjectType !== '') {
            $query->where('subject_type', $subjectType);
        }

        if (is_numeric($beforeId = $request->query('before_id'))) {
            $query->where('id', '<', (int) $beforeId);
        }

        $logs = $query->limit($limit)->get();
        $this->attachActorNames($logs);

        return response()->json([
            'data' => AuditResource::collection($logs)->resolve(),
        ]);
    }

    public function verify(): JsonResponse
    {
        return response()->json(['data' => $this->audit->verifyChain()]);
    }

    /**
     * Resolve a display name once per distinct actor so the timeline can show
     * «Agent TrustGate…» without an N+1 query per row.
     */
    private function attachActorNames(Collection $logs): void
    {
        $userIds = $logs->where('actor_type', AuditService::ACTOR_USER)
            ->pluck('actor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $agentIds = $logs->where('actor_type', AuditService::ACTOR_AGENT)
            ->pluck('actor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $userNames = $userIds === []
            ? []
            : User::query()->whereIn('id', $userIds)->pluck('name', 'id')->all();

        $agentNames = $agentIds === []
            ? []
            : Agent::query()->whereIn('id', $agentIds)->pluck('name', 'id')->all();

        foreach ($logs as $log) {
            $actorId = (string) $log->actor_id;

            $log->setAttribute('actor_name', match ($log->actor_type) {
                AuditService::ACTOR_USER => $userNames[$actorId] ?? null,
                AuditService::ACTOR_AGENT => $agentNames[$actorId] ?? null,
                AuditService::ACTOR_SYSTEM => 'Hệ thống',
                default => null,
            });
        }
    }

    private function scopeToOwner(Builder $query, int $ownerId): void
    {
        $documentIds = Document::query()->where('owner_id', $ownerId)->select('id');
        $taskIds = AgentTask::query()->where('owner_id', $ownerId)->select('id');
        $grantIds = Grant::query()->where('owner_id', $ownerId)->select('id');

        $query->where(function (Builder $query) use ($ownerId, $documentIds, $taskIds, $grantIds) {
            $query->where(function (Builder $query) use ($ownerId) {
                $query->where('actor_type', AuditService::ACTOR_USER)
                    ->where('actor_id', $ownerId);
            })->orWhere(function (Builder $query) use ($documentIds) {
                $query->where('subject_type', 'document')->whereIn('subject_id', $documentIds);
            })->orWhere(function (Builder $query) use ($taskIds) {
                $query->where('subject_type', 'agent_task')->whereIn('subject_id', $taskIds);
            })->orWhere(function (Builder $query) use ($grantIds) {
                $query->where('subject_type', 'grant')->whereIn('subject_id', $grantIds);
            });
        });
    }
}
