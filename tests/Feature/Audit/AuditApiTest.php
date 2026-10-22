<?php

namespace Tests\Feature\Audit;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Document;
use App\Models\Grant;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherOwner;

    private User $admin;

    private User $agentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->owner()->create([
            'name' => 'Lan',
            'email' => 'lan@example.com',
        ]);
        $this->otherOwner = User::factory()->owner()->create([
            'name' => 'Người khác',
            'email' => 'other@example.com',
        ]);
        $this->admin = User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $this->agentUser = User::factory()->create([
            'name' => 'Agent',
            'email' => 'agent@example.com',
            'role' => 'agent',
        ]);
    }

    public function test_owner_only_sees_own_events(): void
    {
        $document = Document::factory()->for($this->owner, 'owner')->create();
        $otherDocument = Document::factory()->for($this->otherOwner, 'owner')->create();
        $task = AgentTask::factory()->for($this->owner, 'owner')->create();
        $grant = Grant::factory()->for($this->owner, 'owner')->for($task, 'task')->create();
        $agent = Agent::factory()->create();

        $service = app(AuditService::class);

        $service->record(
            'document.uploaded',
            AuditService::ACTOR_USER,
            $this->owner->id,
            'document',
            $document->id,
            ['doc_type' => 'id_card'],
        );
        $service->record(
            'grant.opened',
            AuditService::ACTOR_AGENT,
            $agent->id,
            'grant',
            $grant->id,
            ['doc_count' => 1],
        );
        $service->record(
            'document.uploaded',
            AuditService::ACTOR_USER,
            $this->otherOwner->id,
            'document',
            $otherDocument->id,
            ['doc_type' => 'medical_summary'],
        );

        $response = $this->actingAs($this->owner)->getJson('/api/v1/audit');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.action', 'grant.opened')
            ->assertJsonPath('data.1.action', 'document.uploaded');

        $subjectIds = collect($response->json('data'))->pluck('subject_id');

        $this->assertFalse($subjectIds->contains($otherDocument->id));
    }

    public function test_admin_sees_all_events(): void
    {
        $document = Document::factory()->for($this->owner, 'owner')->create();
        $otherDocument = Document::factory()->for($this->otherOwner, 'owner')->create();
        $service = app(AuditService::class);

        $service->record('document.uploaded', AuditService::ACTOR_USER, $this->owner->id, 'document', $document->id);
        $service->record('document.uploaded', AuditService::ACTOR_USER, $this->otherOwner->id, 'document', $otherDocument->id);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_guest_and_agent_are_rejected(): void
    {
        $this->getJson('/api/v1/audit')->assertStatus(401);

        $this->actingAs($this->agentUser)
            ->getJson('/api/v1/audit')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_ROLE');
    }

    public function test_verify_reports_valid_chain(): void
    {
        $service = app(AuditService::class);

        for ($i = 1; $i <= 3; $i++) {
            $service->record('test.event', AuditService::ACTOR_SYSTEM, null, 'system', (string) $i);
        }

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.checked', 3)
            ->assertJsonPath('data.broken_at', null);
    }

    public function test_verify_detects_tampered_meta(): void
    {
        $service = app(AuditService::class);
        $first = $service->record('test.first', AuditService::ACTOR_SYSTEM, null, 'system', '1');
        $tampered = $service->record('test.second', AuditService::ACTOR_SYSTEM, null, 'system', '2', ['reason' => 'ok']);
        $service->record('test.third', AuditService::ACTOR_SYSTEM, null, 'system', '3');

        DB::table('audit_logs')->where('id', $tampered->getKey())->update([
            'meta' => json_encode(['reason' => 'changed']),
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.broken_at', $tampered->getKey())
            ->assertJsonPath('data.reason', 'hash_mismatch');

        $this->assertNotSame($first->hash, $tampered->hash);
    }

    public function test_verify_detects_tampered_prev_hash(): void
    {
        $service = app(AuditService::class);
        $service->record('test.first', AuditService::ACTOR_SYSTEM, null, 'system', '1');
        $second = $service->record('test.second', AuditService::ACTOR_SYSTEM, null, 'system', '2');

        DB::table('audit_logs')->where('id', $second->getKey())->update([
            'prev_hash' => str_repeat('0', 64),
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.broken_at', $second->getKey())
            ->assertJsonPath('data.reason', 'prev_hash_mismatch');
    }

    public function test_verify_detects_deleted_row(): void
    {
        $service = app(AuditService::class);
        $service->record('test.first', AuditService::ACTOR_SYSTEM, null, 'system', '1');
        $second = $service->record('test.second', AuditService::ACTOR_SYSTEM, null, 'system', '2');
        $third = $service->record('test.third', AuditService::ACTOR_SYSTEM, null, 'system', '3');

        DB::table('audit_logs')->where('id', $second->getKey())->delete();

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.broken_at', $third->getKey())
            ->assertJsonPath('data.reason', 'prev_hash_mismatch');
    }

    public function test_owner_meta_is_redacted(): void
    {
        $document = Document::factory()->for($this->owner, 'owner')->create();

        app(AuditService::class)->record(
            'document.uploaded',
            AuditService::ACTOR_USER,
            $this->owner->id,
            'document',
            $document->id,
            [
                'doc_type' => 'id_card',
                'content_b64' => 'c2VjcmV0',
                'grant_key_b64' => 'c2VjcmV0',
                'title' => 'CMND',
            ],
        );

        $response = $this->actingAs($this->owner)->getJson('/api/v1/audit');

        $response->assertOk()
            ->assertJsonPath('data.0.meta.doc_type', 'id_card')
            ->assertJsonMissingPath('data.0.meta.content_b64')
            ->assertJsonMissingPath('data.0.meta.grant_key_b64')
            ->assertJsonMissingPath('data.0.meta.title');
    }

    public function test_owner_can_verify_global_chain(): void
    {
        $document = Document::factory()->for($this->owner, 'owner')->create();
        app(AuditService::class)->record(
            'document.uploaded',
            AuditService::ACTOR_USER,
            $this->owner->id,
            'document',
            $document->id,
        );

        $this->actingAs($this->owner)
            ->getJson('/api/v1/audit/verify')
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.checked', 1);
    }

    public function test_before_id_returns_only_older_events(): void
    {
        $service = app(AuditService::class);
        $first = $service->record('test.first', AuditService::ACTOR_SYSTEM, null, 'system', '1');
        $second = $service->record('test.second', AuditService::ACTOR_SYSTEM, null, 'system', '2');
        $service->record('test.third', AuditService::ACTOR_SYSTEM, null, 'system', '3');

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit?before_id='.$second->getKey())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->getKey());
    }

    public function test_audit_includes_actor_name_for_users_and_agents(): void
    {
        $agent = Agent::factory()->create(['name' => 'TrustGate Agent']);
        $document = Document::factory()->for($this->owner, 'owner')->create();
        $task = AgentTask::factory()->for($this->owner, 'owner')->create();
        $grant = Grant::factory()->for($this->owner, 'owner')->for($task, 'task')->for($agent, 'agent')->create();

        $service = app(AuditService::class);
        $service->record(
            'document.uploaded',
            AuditService::ACTOR_USER,
            $this->owner->id,
            'document',
            $document->id,
        );
        $service->record(
            'grant.opened',
            AuditService::ACTOR_AGENT,
            $agent->id,
            'grant',
            $grant->id,
            ['doc_count' => 3],
        );

        $this->actingAs($this->owner)
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonPath('data.0.actor_name', 'TrustGate Agent')
            ->assertJsonPath('data.0.action_label', 'Agent mở grant')
            ->assertJsonPath('data.1.actor_name', 'Lan');
    }

    public function test_audit_labels_quorum_actions_in_vietnamese(): void
    {
        app(AuditService::class)->record(
            'role.granted',
            AuditService::ACTOR_SYSTEM,
            null,
            'user',
            $this->owner->id,
            ['role' => 'admin'],
        );

        $this->actingAs($this->admin)
            ->getJson('/api/v1/audit')
            ->assertOk()
            ->assertJsonPath('data.0.action_label', 'Cấp vai trò');
    }
}
