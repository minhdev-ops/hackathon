<?php

namespace Tests\Feature\Agent;

use App\Jobs\GenerateProposalJob;
use App\Jobs\RunAgentJob;
use App\Llm\LlmClient;
use App\Llm\MockLlmClient;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use App\Models\User;
use App\Services\GrantBundleCipher;
use App\Services\GrantKeyStore;
use App\Services\GrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskAgentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private User $agentUser;

    private Playbook $playbook;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('trustgate.grant_bundle_disk'));

        $this->owner = User::factory()->owner()->create([
            'name' => 'Lan',
            'email' => 'lan@example.com',
        ]);
        $this->otherOwner = User::factory()->owner()->create([
            'name' => 'Người lạ',
            'email' => 'stranger@example.com',
        ]);
        $this->agentUser = User::factory()->create([
            'name' => 'Agent service',
            'email' => 'agent@example.com',
            'role' => 'agent',
        ]);

        $this->playbook = Playbook::factory()->create([
            'slug' => 'rental',
            'name' => 'Thuê nhà',
            'required_doc_types' => ['id_card', 'income_proof'],
            'optional_doc_types' => ['rental_history', 'reference_letter'],
            'forbidden_doc_types' => ['medical_summary', 'lab_result', 'insurance_card'],
            'default_ttl_minutes' => 60,
            'max_ttl_minutes' => 1440,
        ]);

        $this->agent = Agent::factory()->create([
            'name' => 'TrustGate Agent',
            'client_id' => 'trustgate-agent',
            'allowed_playbooks' => ['rental'],
        ]);
    }

    public function test_owner_creates_task_in_proposing_and_job_is_queued(): void
    {
        Queue::fake();

        $response = $this->actingAs($this->owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', AgentTask::STATUS_PROPOSING)
            ->assertJsonPath('data.playbook.slug', 'rental')
            ->assertJsonPath('data.instruction', 'Nộp hồ sơ thuê căn hộ A');

        Queue::assertPushed(
            GenerateProposalJob::class,
            fn (GenerateProposalJob $job) => $job->taskId === $response->json('data.id'),
        );

        $this->assertDatabaseHas('agent_tasks', [
            'id' => $response->json('data.id'),
            'owner_id' => $this->owner->id,
            'status' => AgentTask::STATUS_PROPOSING,
        ]);
    }

    public function test_mock_llm_generates_proposal_and_pending_grant(): void
    {
        $this->assertInstanceOf(MockLlmClient::class, app(LlmClient::class));

        $response = $this->createTaskResponse();

        $response->assertCreated()
            ->assertJsonPath('data.status', AgentTask::STATUS_AWAITING_APPROVAL)
            ->assertJsonPath('data.proposal.required', ['id_card', 'income_proof'])
            ->assertJsonPath('data.proposal.optional', ['rental_history', 'reference_letter']);

        $task = AgentTask::query()->findOrFail($response->json('data.id'));

        $this->assertIsString($task->proposal['rationale']);
        $this->assertStringContainsString('Nộp hồ sơ thuê căn hộ A', $task->proposal['rationale']);

        $grant = $task->grants()->first();

        $this->assertInstanceOf(Grant::class, $grant);
        $this->assertSame(Grant::STATUS_PENDING, $grant->status);
        $this->assertSame($this->owner->id, $grant->owner_id);
        $this->assertSame($grant->id, $response->json('data.grant.id'));

        $steps = collect($task->log ?? [])->pluck('step');

        $this->assertContains('task_created', $steps);
        $this->assertContains('proposal.generated', $steps);
        $this->assertContains('awaiting_approval', $steps);
    }

    public function test_proposal_is_clamped_to_playbook_scope_even_if_llm_returns_other_types(): void
    {
        $this->app->instance(LlmClient::class, new StubLlmClient(
            required: ['id_card', 'medical_summary', 'lab_result', 'not_a_type'],
            optional: ['rental_history', 'income_proof', 'insurance_card'],
            rationale: 'LLM cố tình trả loại ngoài phạm vi.',
        ));

        $response = $this->createTaskResponse();

        $proposal = $response->json('data.proposal');

        $this->assertSame(['id_card'], $proposal['required']);
        $this->assertSame(['rental_history', 'income_proof'], $proposal['optional']);
        $this->assertNotContains('medical_summary', $proposal['required']);
        $this->assertNotContains('lab_result', $proposal['required']);
        $this->assertNotContains('insurance_card', $proposal['optional']);
        $this->assertNotContains('not_a_type', $proposal['required']);

        $task = AgentTask::query()->findOrFail($response->json('data.id'));

        $this->assertSame(['id_card'], $task->proposal['required']);
        $this->assertSame(['rental_history', 'income_proof'], $task->proposal['optional']);
    }

    public function test_task_validation_and_owner_isolation(): void
    {
        $this->actingAs($this->owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'missing',
            'instruction' => 'Nộp hồ sơ',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($this->owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => '',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $taskId = $this->createTaskResponse()->json('data.id');

        $this->actingAs($this->otherOwner)
            ->getJson("/api/v1/tasks/{$taskId}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->actingAs($this->otherOwner)
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($this->owner)
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $taskId);
    }

    public function test_admin_cannot_create_task(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => 'Nộp hồ sơ thuê nhà',
        ])->assertStatus(403);
    }

    public function test_agent_run_composes_submission_and_logs_each_step(): void
    {
        $task = AgentTask::query()->findOrFail($this->createTaskResponse()->json('data.id'));
        $grant = $task->grants()->firstOrFail();

        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $rentalHistory = $this->document('rental_history');

        $this->approveGrant($grant, [$idCard, $incomeProof, $rentalHistory])->assertOk();

        $task->refresh();

        $this->assertSame(AgentTask::STATUS_DONE, $task->status);
        $this->assertSame(3, $task->result['document_count']);
        $this->assertSame('mock', $task->result['mode']);
        $this->assertStringContainsString('Hồ sơ nộp gồm 3 tài liệu', $task->result['submission']);
        $this->assertSame('plaintext-'.$idCard->id, $task->result['extracted_fields']['id_card']);
        $this->assertSame($grant->id, $task->result['grant_id']);

        $steps = collect($task->log ?? [])->pluck('step');

        $this->assertContains('run.started', $steps);
        $this->assertContains('run.grant_opened', $steps);
        $this->assertContains('run.completed', $steps);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.opened',
            'subject_id' => $grant->id,
        ]);
    }

    public function test_agent_stops_with_failed_revoked_when_grant_is_revoked_before_next_step(): void
    {
        Queue::fake([RunAgentJob::class]);

        $task = AgentTask::query()->findOrFail($this->createTaskResponse()->json('data.id'));
        $grant = $task->grants()->firstOrFail();
        // FR-GRANT-02: grant phải có đủ mọi doc_type bắt buộc của playbook.
        $documents = [$this->document('id_card'), $this->document('income_proof')];

        $this->approveGrant($grant, $documents)->assertOk();

        $task->refresh();
        $this->assertSame(AgentTask::STATUS_RUNNING, $task->status);

        // Grant bị thu hồi giữa chừng (job bị fake nên chưa chạy).
        $grant->forceFill([
            'status' => Grant::STATUS_REVOKED,
            'revoked_at' => now(),
        ])->save();

        (new RunAgentJob($task->getKey()))->handle(
            app(GrantService::class),
            app(GrantKeyStore::class),
            app(LlmClient::class),
        );

        $task->refresh();

        $this->assertSame(AgentTask::STATUS_FAILED_REVOKED, $task->status);
        $this->assertNull($task->result);

        $lastLog = collect($task->log ?? [])->last();

        $this->assertSame('run.stopped', $lastLog['step']);
        $this->assertSame('grant_revoked', $lastLog['reason']);
    }

    public function test_agent_out_of_scope_document_request_is_403_and_audited(): void
    {
        $task = AgentTask::query()->findOrFail($this->createTaskResponse()->json('data.id'));

        $this->actingAs($this->agentUser)
            ->postJson('/api/v1/agent/documents/request', [
                'task_id' => $task->id,
                'doc_type' => 'medical_summary',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'OUT_OF_SCOPE');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent.document_request.out_of_scope',
            'actor_type' => 'agent',
            'subject_type' => 'agent_task',
            'subject_id' => $task->id,
        ]);

        $this->actingAs($this->owner)
            ->postJson('/api/v1/agent/documents/request', [
                'task_id' => $task->id,
                'doc_type' => 'medical_summary',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_ROLE');
    }

    private function createTaskResponse()
    {
        return $this->actingAs($this->owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
        ]);
    }

    private function document(string $docType): Document
    {
        return Document::factory()->ofType($docType)->create([
            'owner_id' => $this->owner->id,
        ]);
    }

    /**
     * @param  list<Document>  $documents
     */
    private function approveGrant(Grant $grant, array $documents)
    {
        $bundle = app(GrantBundleCipher::class)->encrypt([
            'documents' => array_map(fn (Document $document) => [
                'doc_id' => $document->id,
                'doc_type' => $document->doc_type,
                'name' => 'Giấy tờ '.$document->doc_type,
                'content_b64' => base64_encode('plaintext-'.$document->id),
            ], $documents),
        ]);

        return $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/v1/grants/{$grant->id}/approve", [
                'doc_ids' => array_map(fn (Document $document) => $document->id, $documents),
                'ttl_minutes' => 15,
                'grant_key_b64' => $bundle['key'],
                'bundle_iv' => $bundle['iv'],
                'bundle' => UploadedFile::fake()->createWithContent('bundle.bin', $bundle['ciphertext']),
            ]);
    }
}

class StubLlmClient implements LlmClient
{
    /**
     * @param  list<string>  $required
     * @param  list<string>  $optional
     */
    public function __construct(
        private readonly array $required,
        private readonly array $optional,
        private readonly string $rationale,
    ) {}

    public function propose(Playbook $playbook, string $instruction): array
    {
        return [
            'required' => $this->required,
            'optional' => $this->optional,
            'rationale' => $this->rationale,
        ];
    }

    public function summarize(array $documents): string
    {
        return 'Stub summary with '.count($documents).' documents.';
    }
}
