<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Jobs\GenerateProposalJob;
use App\Models\AgentTask;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * FR-TASK-01: Owner tạo task, GenerateProposalJob được đẩy vào queue.
 */
class TasksController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    /**
     * POST /tasks — tạo task mới (trạng thái proposing), đẩy GenerateProposalJob.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        Gate::authorize('create', AgentTask::class);

        $validated = $request->validated();

        $created = $this->tasks->create(
            $request->user(),
            $validated['playbook_slug'],
            $validated['instruction'],
        );

        /** @var AgentTask $task */
        $task = $created['task'];

        GenerateProposalJob::dispatch($task->getKey());

        // Sync queue (test/demo) đã chạy job: đọc lại proposal + grant vừa tạo.
        $task->refresh();

        return response()->json(['data' => new TaskResource($task)], 201);
    }

    /**
     * GET /tasks — danh sách task của Owner hiện tại.
     */
    public function index(): JsonResponse
    {
        $tasks = AgentTask::where('owner_id', auth()->id())
            ->with(['agent', 'playbook'])
            ->latest()
            ->get();

        return response()->json(['data' => TaskResource::collection($tasks)]);
    }

    /**
     * GET /tasks/{id} — chi tiết task.
     */
    public function show(AgentTask $task): JsonResponse
    {
        Gate::authorize('view', $task);

        return response()->json(['data' => new TaskResource($task)]);
    }
}
