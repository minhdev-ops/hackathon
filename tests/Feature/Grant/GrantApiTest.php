<?php

namespace Tests\Feature\Grant;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use App\Models\User;
use App\Services\GrantBundleCipher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GrantApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $stranger;

    private User $agentUser;

    private Playbook $playbook;

    private Agent $agent;

    private AgentTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('trustgate.grant_bundle_disk'));

        $this->owner = User::factory()->create(['name' => 'Lan']);
        $this->stranger = User::factory()->create(['name' => 'Người lạ']);
        $this->agentUser = User::factory()->create(['name' => 'Agent service']);

        $this->playbook = Playbook::factory()->create([
            'slug' => 'rental',
            'name' => 'Thuê nhà',
            'required_doc_types' => ['id_card', 'income_proof'],
            'optional_doc_types' => ['rental_history', 'reference_letter'],
            'forbidden_doc_types' => ['medical_summary', 'lab_result', 'insurance_card'],
            'recipient_label' => 'Chủ nhà',
            'default_ttl_minutes' => 60,
            'max_ttl_minutes' => 1440,
        ]);

        $this->agent = Agent::factory()->create([
            'name' => 'TrustGate Agent',
            'allowed_playbooks' => ['rental', 'clinic'],
        ]);

        $this->task = AgentTask::factory()->create([
            'owner_id' => $this->owner->id,
            'agent_id' => $this->agent->id,
            'playbook_id' => $this->playbook->id,
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
            'status' => AgentTask::STATUS_AWAITING_APPROVAL,
            'proposal' => [
                'required' => ['id_card', 'income_proof'],
                'optional' => ['rental_history'],
                'rationale' => 'Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập.',
            ],
        ]);
    }

    public function test_owner_sees_review_screen_with_documents_and_ttl_choices(): void
    {
        $idCard = $this->document('id_card');
        $this->document('income_proof');
        $this->document('rental_history');

        $response = $this->actingAs($this->owner)
            ->getJson("/api/v1/grants/{$this->pendingGrant()->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', Grant::STATUS_PENDING)
            ->assertJsonPath('data.agent.name', 'TrustGate Agent')
            ->assertJsonPath('data.purpose', 'Nộp hồ sơ thuê nhà')
            ->assertJsonPath('data.review.default_ttl_minutes', 60);

        $this->assertSame([5, 15, 60, 1440], $response->json('data.review.ttl_options'));

        $documents = collect($response->json('data.review.documents'));
        $this->assertCount(3, $documents);

        $requiredByType = $documents->mapWithKeys(fn (array $doc) => [$doc['doc_type'] => $doc['required']]);
        $this->assertTrue($requiredByType['id_card']);
        $this->assertTrue($requiredByType['income_proof']);
        $this->assertFalse($requiredByType['rental_history']);
        $this->assertSame($idCard->id, $documents->firstWhere('doc_type', 'id_card')['id']);
        $this->assertArrayHasKey('iv', $documents->firstWhere('doc_type', 'id_card'));
        $this->assertArrayHasKey('title_ct', $documents->firstWhere('doc_type', 'id_card'));
        $this->assertArrayHasKey('sha256', $documents->firstWhere('doc_type', 'id_card'));
    }

    public function test_approve_activates_grant_and_puts_key_in_store_with_ttl(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->pendingGrant();

        $bundle = $this->bundleFor([$idCard, $incomeProof]);

        $response = $this->approveRequest($grant, [$idCard, $incomeProof], 15, $bundle);

        $response->assertOk()
            ->assertJsonPath('data.status', Grant::STATUS_ACTIVE)
            ->assertJsonPath('data.review.required_doc_types', ['id_card', 'income_proof']);

        $grant->refresh();

        $this->assertSame(Grant::STATUS_ACTIVE, $grant->status);
        $this->assertNotNull($grant->approved_at);
        $this->assertTrue(
            $grant->expires_at->between(now()->addMinutes(14), now()->addMinutes(16)),
            'expires_at phải bằng approved_at + ttl_minutes',
        );
        $this->assertSame([$idCard->id, $incomeProof->id], $grant->doc_ids);
        $this->assertGreaterThan(880, $grant->remainingSeconds());
        $this->assertLessThanOrEqual(900, $grant->remainingSeconds());

        $this->assertSame($bundle['key'], $this->grantKey($grant));
        Storage::disk(config('trustgate.grant_bundle_disk'))->assertExists($grant->bundle_path);

        $this->assertSame(AgentTask::STATUS_DONE, $this->task->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.approved',
            'subject_type' => 'grant',
            'subject_id' => $grant->id,
        ]);

        // NFR-SEC-01: K_g never reaches the audit trail.
        $auditDump = AuditLog::query()->get()
            ->map(fn (AuditLog $log) => json_encode([$log->meta, $log->prev_hash, $log->hash]))
            ->implode(' ');

        $this->assertStringNotContainsString($bundle['key'], $auditDump);
    }

    public function test_approve_rejects_when_required_document_is_missing(): void
    {
        $idCard = $this->document('id_card');
        $grant = $this->pendingGrant();

        $response = $this->approveRequest($grant, [$idCard], 15, $this->bundleFor([$idCard]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MISSING_REQUIRED_DOCS')
            ->assertJsonPath('error.details.missing_doc_types.0', 'income_proof');

        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
        $this->assertNull($this->grantKey($grant));
    }

    public function test_approve_still_requires_playbook_documents_when_proposal_omits_them(): void
    {
        $this->task->forceFill([
            'proposal' => [
                'required' => ['id_card'],
                'optional' => [],
                'rationale' => 'Đề xuất thiếu giấy tờ so với playbook.',
            ],
        ])->save();

        $idCard = $this->document('id_card');
        $grant = $this->pendingGrant();

        $response = $this->approveRequest($grant, [$idCard], 15, $this->bundleFor([$idCard]));

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MISSING_REQUIRED_DOCS')
            ->assertJsonPath('error.details.missing_doc_types.0', 'income_proof');
    }

    public function test_approve_rejects_document_outside_playbook_scope(): void
    {
        $idCard = $this->document('id_card');
        $medicalSummary = $this->document('medical_summary');
        $grant = $this->pendingGrant();

        $response = $this->approveRequest($grant, [$idCard, $medicalSummary], 15, $this->bundleFor([$idCard, $medicalSummary]));

        $response->assertStatus(422)->assertJsonPath('error.code', 'OUT_OF_SCOPE');

        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
        $this->assertNull($this->grantKey($grant));
    }

    public function test_approve_rejects_documents_owned_by_someone_else(): void
    {
        $idCard = $this->document('id_card');
        $foreignDocument = $this->document('income_proof', $this->stranger);
        $grant = $this->pendingGrant();

        $response = $this->approveRequest(
            $grant,
            [$idCard, $foreignDocument],
            15,
            $this->bundleFor([$idCard, $foreignDocument]),
        );

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'OUT_OF_SCOPE')
            ->assertJsonPath('error.details.violations.0.reason', 'NOT_AVAILABLE');
    }

    public function test_approve_rejects_ttl_above_playbook_max(): void
    {
        $this->playbook->forceFill(['max_ttl_minutes' => 60])->save();

        $idCard = $this->document('id_card');
        $grant = $this->pendingGrant();

        $response = $this->approveRequest($grant, [$idCard], 1440, $this->bundleFor([$idCard]));

        $response->assertStatus(422)->assertJsonPath('error.code', 'TTL_NOT_ALLOWED');
        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
    }

    public function test_approve_rejects_ttl_not_offered_on_the_screen(): void
    {
        $idCard = $this->document('id_card');
        $grant = $this->pendingGrant();

        $response = $this->approveRequest($grant, [$idCard], 7, $this->bundleFor([$idCard]));

        $response->assertStatus(422)->assertJsonPath('error.code', 'TTL_NOT_ALLOWED');
    }

    public function test_approve_rejects_bundle_containing_unapproved_documents(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $rentalHistory = $this->document('rental_history');
        $grant = $this->pendingGrant();

        // Owner duyệt đủ giấy tờ bắt buộc, nhưng bundle có thêm rental_history.
        $bundle = $this->bundleFor([$idCard, $incomeProof, $rentalHistory]);

        $response = $this->approveRequest($grant, [$idCard, $incomeProof], 15, $bundle);

        $response->assertStatus(422)->assertJsonPath('error.code', 'BUNDLE_INVALID');
        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
        $this->assertNull($this->grantKey($grant));
    }

    public function test_approve_rejects_invalid_grant_key(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->pendingGrant();
        $bundle = $this->bundleFor([$idCard, $incomeProof]);

        $response = $this->actingAs($this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/v1/grants/{$grant->id}/approve", [
                'doc_ids' => [$idCard->id, $incomeProof->id],
                'ttl_minutes' => 15,
                'grant_key_b64' => base64_encode(random_bytes(16)),
                'bundle_iv' => $bundle['iv'],
                'bundle' => UploadedFile::fake()->createWithContent('bundle.bin', $bundle['ciphertext']),
            ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'INVALID_GRANT_KEY');
    }

    public function test_other_owner_cannot_view_or_approve_a_grant(): void
    {
        $idCard = $this->document('id_card');
        $grant = $this->pendingGrant();

        $this->actingAs($this->stranger)
            ->getJson("/api/v1/grants/{$grant->id}")
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $this->approveRequest($grant, [$idCard], 15, $this->bundleFor([$idCard]), $this->stranger)
            ->assertStatus(403);

        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
    }

    public function test_guest_is_rejected(): void
    {
        $grant = $this->pendingGrant();

        $this->getJson("/api/v1/grants/{$grant->id}")
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_deny_blocks_agent_and_audits(): void
    {
        $grant = $this->pendingGrant();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/grants/{$grant->id}/deny", ['reason' => 'Không cần chia sẻ'])
            ->assertOk()
            ->assertJsonPath('data.status', Grant::STATUS_DENIED);

        $this->assertSame(AgentTask::STATUS_DENIED, $this->task->refresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.denied',
            'subject_id' => $grant->id,
        ]);

        $this->openGrant($grant)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'GRANT_UNAVAILABLE');
    }

    public function test_revoke_shreds_key_and_fails_running_task(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->approvedGrant([$idCard, $incomeProof]);

        $this->assertNotNull($this->grantKey($grant));

        // RunAgentJob đã chạy sau approve (sync queue); reset task về RUNNING
        // để kiểm tra FR-AGENT-03: thu hồi giữa chừng làm hỏng task đang chạy.
        $this->task->forceFill(['status' => AgentTask::STATUS_RUNNING])->save();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/grants/{$grant->id}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', Grant::STATUS_REVOKED);

        $grant->refresh();

        $this->assertSame(Grant::STATUS_REVOKED, $grant->status);
        $this->assertNotNull($grant->revoked_at);
        $this->assertNull($this->grantKey($grant), 'K_g phải bị xoá khi thu hồi');
        $this->assertSame(AgentTask::STATUS_FAILED_REVOKED, $this->task->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.revoked',
            'subject_id' => $grant->id,
        ]);

        $this->openGrant($grant)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'GRANT_UNAVAILABLE');
    }

    public function test_revoke_pending_grant_returns_conflict(): void
    {
        $grant = $this->pendingGrant();

        $this->actingAs($this->owner)
            ->postJson("/api/v1/grants/{$grant->id}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'GRANT_NOT_ACTIVE');

        $this->assertSame(Grant::STATUS_PENDING, $grant->refresh()->status);
    }

    public function test_agent_opens_active_grant_and_receives_only_approved_documents(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->approvedGrant([$idCard, $incomeProof]);

        $response = $this->openGrant($grant);

        $response->assertOk()
            ->assertJsonPath('data.grant_id', $grant->id)
            ->assertJsonCount(2, 'data.documents');

        $documentIds = collect($response->json('data.documents'))->pluck('doc_id')->all();

        $this->assertEqualsCanonicalizing([$idCard->id, $incomeProof->id], $documentIds);
        $this->assertSame(
            'plaintext-'.$idCard->id,
            base64_decode(collect($response->json('data.documents'))->firstWhere('doc_id', $idCard->id)['content_b64']),
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.opened',
            'actor_type' => 'agent',
            'subject_id' => $grant->id,
        ]);
    }

    public function test_open_pending_grant_returns_410(): void
    {
        $grant = $this->pendingGrant();

        $this->openGrant($grant)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'GRANT_UNAVAILABLE');
    }

    public function test_open_after_expiry_returns_410_and_marks_grant_expired(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->approvedGrant([$idCard, $incomeProof]);

        $this->travel(16)->minutes();

        $this->openGrant($grant)
            ->assertStatus(410)
            ->assertJsonPath('error.code', 'GRANT_UNAVAILABLE');

        $this->assertSame(Grant::STATUS_EXPIRED, $grant->refresh()->status);
        $this->assertNull($this->grantKey($grant));
    }

    public function test_scheduled_command_expires_overdue_grants(): void
    {
        $idCard = $this->document('id_card');
        $incomeProof = $this->document('income_proof');
        $grant = $this->approvedGrant([$idCard, $incomeProof]);

        $this->travel(16)->minutes();

        $this->artisan('grants:expire')->assertSuccessful();

        $this->assertSame(Grant::STATUS_EXPIRED, $grant->refresh()->status);
        $this->assertNull($this->grantKey($grant));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'grant.expired',
            'subject_id' => $grant->id,
        ]);
    }

    private function document(string $docType, ?User $owner = null): Document
    {
        return Document::factory()->ofType($docType)->create([
            'owner_id' => ($owner ?? $this->owner)->id,
        ]);
    }

    private function pendingGrant(): Grant
    {
        return Grant::factory()->create([
            'task_id' => $this->task->id,
            'owner_id' => $this->owner->id,
            'agent_id' => $this->agent->id,
            'purpose' => 'Nộp hồ sơ thuê nhà',
            'doc_ids' => [],
            'status' => Grant::STATUS_PENDING,
        ]);
    }

    /**
     * @param  list<Document>  $documents
     */
    private function approvedGrant(array $documents, int $ttlMinutes = 15): Grant
    {
        $grant = $this->pendingGrant();

        $this->approveRequest($grant, $documents, $ttlMinutes, $this->bundleFor($documents))
            ->assertOk();

        return $grant->refresh();
    }

    /**
     * @param  list<Document>  $documents
     */
    private function approveRequest(
        Grant $grant,
        array $documents,
        int $ttlMinutes,
        array $bundle,
        ?User $actor = null,
    ) {
        return $this->actingAs($actor ?? $this->owner)
            ->withHeaders(['Accept' => 'application/json'])
            ->post("/api/v1/grants/{$grant->id}/approve", [
                'doc_ids' => array_map(fn (Document $document) => $document->id, $documents),
                'ttl_minutes' => $ttlMinutes,
                'grant_key_b64' => $bundle['key'],
                'bundle_iv' => $bundle['iv'],
                'bundle' => UploadedFile::fake()->createWithContent('bundle.bin', $bundle['ciphertext']),
            ]);
    }

    /**
     * @param  list<Document>  $documents
     * @return array{ciphertext: string, iv: string, key: string}
     */
    private function bundleFor(array $documents): array
    {
        return app(GrantBundleCipher::class)->encrypt([
            'documents' => array_map(fn (Document $document) => [
                'doc_id' => $document->id,
                'doc_type' => $document->doc_type,
                'name' => 'Giấy tờ '.$document->doc_type,
                'content_b64' => base64_encode('plaintext-'.$document->id),
            ], $documents),
        ]);
    }

    private function openGrant(Grant $grant)
    {
        return $this->actingAs($this->agentUser)
            ->postJson("/api/v1/agent/grants/{$grant->id}/open");
    }

    private function grantKey(Grant $grant): ?string
    {
        return Cache::store(config('trustgate.grant_key_store'))
            ->get(config('trustgate.grant_key_prefix').$grant->id);
    }
}
