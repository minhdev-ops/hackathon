<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Jobs\SlowRunAgentJob;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\ChangeRequest;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GrantKeyStore;
use App\Services\QuorumService;
use App\Support\ChangeRequestType;
use Database\Seeders\DemoSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Demo «Làm admin xấu» (FR-DEMO-01..05). Chỉ bật khi APP_DEMO=true.
 * Mọi endpoint yêu cầu đăng nhập và vai trò admin.
 */
class DemoController extends Controller
{
    public function __construct(
        private readonly QuorumService $quorum,
        private readonly GrantKeyStore $keys,
        private readonly AuditService $audit,
    ) {}

    /**
     * Đảm bảo người gọi là admin.
     */
    private function requireAdmin(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Chỉ admin mới được sử dụng tính năng demo này.');
        }
    }

    /**
     * FR-DEMO-01: Đổ database. Trả hàng thô của documents, grants (ciphertext,
     * iv, title_ct đã là base64; không có plaintext).
     */
    public function dumpDb(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $documents = DB::table('documents')->orderBy('created_at')->get()
            ->map(function (object $document): array {
                $row = (array) $document;

                $row['ciphertext_b64'] = null;

                if (is_string($document->ciphertext_path)
                    && Storage::disk('local')->exists($document->ciphertext_path)) {
                    $row['ciphertext_b64'] = base64_encode(
                        substr(Storage::disk('local')->get($document->ciphertext_path), 0, 512),
                    );
                }

                return $row;
            })
            ->values();

        $grants = DB::table('grants')->orderBy('created_at')->get();

        return response()->json([
            'data' => [
                'documents' => $documents,
                'grants' => $grants,
                'note' => 'ciphertext / iv / title_ct / sha256 đều là bản mã base64, không có plaintext.',
            ],
        ]);
    }

    /**
     * FR-DEMO-03: endpoint demo dành cho admin, mô phỏng agent xin tài liệu
     * ngoài phạm vi. Route agent thật vẫn chỉ dành cho role agent.
     */
    public function outOfScope(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $this->audit->record(
            'agent.document_request.out_of_scope',
            AuditService::ACTOR_USER,
            $request->user()->getKey(),
            'demo',
            null,
            [
                'doc_type' => 'medical_summary',
                'reason' => 'Admin demo giả lập agent xin tài liệu ngoài phạm vi.',
                'demo' => true,
            ],
        );

        throw ApiException::forbiddenScope(
            'Agent không được yêu cầu tài liệu ngoài phạm vi đã duyệt.',
            ['scope' => 'OUT_OF_SCOPE'],
        );
    }

    /**
     * FR-DEMO-02: Tự cấp quyền. Tạo CR role_grant cho chính admin này qua
     * QuorumService (có audit, kiểm tra quorum). Quyền chưa đổi vì đang chờ
     * duyệt (mặc định 1/2).
     */
    public function selfElevate(Request $request): JsonResponse
    {
        $this->requireAdmin($request);
        $admin = $request->user();

        $cr = $this->quorum->request(
            $admin,
            ChangeRequest::TYPE_ROLE_GRANT,
            [
                'email' => $admin->email,
                'role' => 'admin',
                'reason' => 'Demo tự cấp quyền (admin xấu).',
            ]
        );

        return response()->json([
            'data' => [
                'change_request_id' => $cr->getKey(),
                'type' => $cr->type,
                'type_label' => ChangeRequestType::label($cr->type),
                'status' => $cr->status,
                'approvals' => "{$cr->approvedCount()}/{$cr->required_approvals}",
                'message' => 'CR đã tạo. Chờ quorum duyệt, quyền chưa đổi.',
            ],
        ]);
    }

    /**
     * FR-DEMO-04: Thu hồi giữa chừng. Khởi chạy agent chậm (mỗi bước 2 giây),
     * ghi log từng bước để frontend poll theo thời gian thực.
     */
    public function slowRun(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $owner = User::where('role', 'owner')->first()
            ?? User::factory()->create(['role' => 'owner', 'name' => 'Lan']);

        $agent = Agent::where('status', Agent::STATUS_ACTIVE)->first()
            ?? Agent::factory()->create(['status' => Agent::STATUS_ACTIVE]);

        $playbook = Playbook::where('slug', 'rental')->first()
            ?? Playbook::factory()->create(['slug' => 'rental']);

        $task = AgentTask::create([
            'owner_id' => $owner->getKey(),
            'agent_id' => $agent->getKey(),
            'playbook_id' => $playbook->getKey(),
            'instruction' => 'Demo agent chậm để minh họa thu hồi giữa chừng.',
            'status' => AgentTask::STATUS_RUNNING,
            'proposal' => ['required' => [], 'optional' => [], 'rationale' => 'Demo slow run.'],
            'log' => [],
        ]);

        $expiresAt = now()->addHour();
        $grant = Grant::create([
            'task_id' => $task->getKey(),
            'owner_id' => $owner->getKey(),
            'agent_id' => $agent->getKey(),
            'purpose' => 'Demo slow run grant',
            'doc_ids' => [],
            'status' => Grant::STATUS_ACTIVE,
            'approved_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        // Crypto-shredding demo: K_g phải có trước khi job chạy.
        $this->keys->put(
            $grant,
            base64_encode(random_bytes(32)),
            max(1, $expiresAt->getTimestamp() - now()->getTimestamp()),
        );

        $sleepSeconds = max(0, (int) config('trustgate.demo_slow_run_sleep_seconds', 2));
        SlowRunAgentJob::dispatch($task->getKey(), 6, $sleepSeconds);

        return response()->json([
            'data' => [
                'task_id' => $task->getKey(),
                'grant_id' => $grant->getKey(),
                'status' => $task->status,
                'message' => "Agent đang chạy chậm ({$sleepSeconds} giây/bước). Owner có thể bấm Thu hồi.",
            ],
        ]);
    }

    /**
     * Poll log của slow-run (dùng cho FR-DEMO-04, polling 1 giây).
     */
    public function slowRunLog(Request $request, string $taskId): JsonResponse
    {
        $task = AgentTask::where('id', $taskId)->first();

        if (! $task) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'Không tìm thấy task.']], 404);
        }

        if ($task->owner_id !== $request->user()->getKey() && ! $request->user()->isAdmin()) {
            return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => 'Không được xem log task này.']], 403);
        }

        return response()->json([
            'data' => [
                'task_id' => $task->getKey(),
                'status' => $task->status,
                'log' => $task->log ?? [],
            ],
        ]);
    }

    /**
     * FR-DEMO-05: Đặt lại dữ liệu demo chạy seeder.
     */
    public function reset(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $lan = User::where('email', 'lan@example.com')->first();

        if ($lan !== null) {
            $grants = Grant::query()->where('owner_id', $lan->getKey())->get();

            foreach ($grants as $grant) {
                $this->keys->forget($grant);

                if (is_string($grant->bundle_path)) {
                    Storage::disk(config('trustgate.grant_bundle_disk'))->delete($grant->bundle_path);
                }
            }

            Grant::query()->where('owner_id', $lan->getKey())->delete();
            AgentTask::query()->where('owner_id', $lan->getKey())->delete();

            Document::withTrashed()
                ->where('owner_id', $lan->getKey())
                ->get()
                ->each(fn (Document $document) => $document->forceDelete());

            Storage::disk('local')->deleteDirectory('documents/demo/'.$lan->getKey());
        }

        $adminIds = User::query()->where('role', 'admin')->pluck('id');

        if ($adminIds->isNotEmpty()) {
            ChangeRequest::query()->whereIn('requested_by', $adminIds)->delete();
        }

        Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $lan = User::where('email', 'lan@example.com')->first();

        return response()->json([
            'data' => [
                'message' => 'Đã đặt lại dữ liệu demo.',
                'seeder' => 'DemoSeeder',
                'documents' => $lan === null
                    ? 0
                    : Document::query()->where('owner_id', $lan->getKey())->count(),
            ],
        ]);
    }

    /**
     * Cấp token agent demo (dự phòng khi V6 không đạt, xem SRS mục 2.3/V6).
     */
    public function agentToken(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        return response()->json([
            'error' => [
                'code' => 'NOT_IMPLEMENTED',
                'message' => 'Agent token demo chưa được triển khai; dùng session auth cho demo.',
            ],
        ], 501);
    }
}
