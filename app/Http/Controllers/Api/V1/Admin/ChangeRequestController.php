<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChangeRequestRequest;
use App\Http\Resources\ChangeRequestResource;
use App\Models\ChangeRequest;
use App\Services\QuorumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChangeRequestController extends Controller
{
    public function __construct(private readonly QuorumService $quorum) {}

    /**
     * GET /admin/change-requests — danh sách CR (lọc ?status=).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,executed,rejected'],
        ]);

        $query = ChangeRequest::query()
            ->with(['requester', 'approvals.admin'])
            ->latest();

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        return ChangeRequestResource::collection($query->limit(100)->get());
    }

    /**
     * POST /admin/change-requests — FR-QRM-02.
     */
    public function store(StoreChangeRequestRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $changeRequest = $this->quorum->request(
            $request->user(),
            $validated['type'],
            $validated['payload'],
        );

        return (new ChangeRequestResource($changeRequest))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /admin/change-requests/{id}/approve — FR-QRM-03 + FR-QRM-04 fallback.
     */
    public function approve(Request $request, ChangeRequest $changeRequest): ChangeRequestResource
    {
        $this->assertReauthenticated($request);

        $validated = $request->validate([
            'signature' => ['nullable', 'string', 'max:4096'],
        ]);

        $approved = $this->quorum->approve(
            $changeRequest,
            $request->user(),
            $validated['signature'] ?? null,
        );

        return new ChangeRequestResource($approved);
    }

    /**
     * POST /admin/change-requests/{id}/reject — đủ ngưỡng từ chối thì rejected.
     */
    public function reject(Request $request, ChangeRequest $changeRequest): ChangeRequestResource
    {
        return new ChangeRequestResource(
            $this->quorum->reject($changeRequest, $request->user()),
        );
    }

    /**
     * FR-QRM-04 fallback: bắt buộc xác thực lại trong 5 phút trước khi duyệt.
     */
    private function assertReauthenticated(Request $request): void
    {
        $minutes = max(1, (int) config('quorum.reauth_minutes', 5));
        $reauthAt = $request->session()->get('admin_reauth_at');

        if (! is_int($reauthAt) || (now()->getTimestamp() - $reauthAt) > $minutes * 60) {
            throw new ApiException(
                'REAUTH_REQUIRED',
                'Vui lòng xác thực lại trước khi duyệt Change Request.',
                403,
                ['reauth_minutes' => $minutes],
            );
        }
    }
}
