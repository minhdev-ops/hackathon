<?php

namespace Tests\Feature\Quorum;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\ChangeRequestApproval;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuorumTest extends TestCase
{
    use RefreshDatabase;

    private User $admin1;

    private User $admin2;

    private User $admin3;

    private User $owner;

    private User $target;

    private Playbook $playbook;

    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin1 = User::factory()->admin()->create([
            'name' => 'Admin 1',
            'email' => 'admin1@example.com',
        ]);
        $this->admin2 = User::factory()->admin()->create([
            'name' => 'Admin 2',
            'email' => 'admin2@example.com',
        ]);
        $this->admin3 = User::factory()->admin()->create([
            'name' => 'Admin 3',
            'email' => 'admin3@example.com',
        ]);
        $this->owner = User::factory()->owner()->create([
            'name' => 'Lan',
            'email' => 'lan@example.com',
        ]);
        $this->target = User::factory()->owner()->create([
            'name' => 'Người được cấp quyền',
            'email' => 'target@example.com',
        ]);

        $this->playbook = Playbook::factory()->create([
            'slug' => 'rental',
            'name' => 'Thuê nhà',
            'max_ttl_minutes' => 1440,
            'default_ttl_minutes' => 60,
            'version' => 1,
        ]);

        $this->agent = Agent::factory()->create([
            'client_id' => 'trustgate-agent',
            'allowed_playbooks' => ['rental', 'clinic'],
        ]);

        Playbook::factory()->create([
            'slug' => 'clinic',
            'name' => 'Khám bệnh',
            'max_ttl_minutes' => 60,
        ]);
    }

    public function test_non_admin_cannot_access_quorum_api(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/v1/admin/change-requests')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_ROLE');
    }

    public function test_admin_creates_pending_change_request_with_configured_threshold(): void
    {
        $response = $this->createCrResponse(ChangeRequest::TYPE_ROLE_GRANT, [
            'email' => 'target@example.com',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_PENDING)
            ->assertJsonPath('data.required_approvals', 2)
            ->assertJsonPath('data.approvals_count', 0)
            ->assertJsonPath('data.remaining_approvals', 2)
            ->assertJsonPath('data.requester.email', 'admin1@example.com')
            ->assertJsonPath('data.can_vote', true);

        $this->assertDatabaseHas('change_requests', [
            'type' => ChangeRequest::TYPE_ROLE_GRANT,
            'status' => ChangeRequest::STATUS_PENDING,
            'required_approvals' => 2,
            'requested_by' => $this->admin1->id,
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'change_request.created']);
    }

    public function test_invalid_payloads_are_rejected_before_storage(): void
    {
        $this->createCrResponse(ChangeRequest::TYPE_ROLE_GRANT, [
            'email' => 'missing@example.com',
            'role' => 'admin',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->createCrResponse(ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE, [
            'required_approvals' => 99,
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->createCrResponse(ChangeRequest::TYPE_PLAYBOOK_UPDATE, [
            'slug' => 'rental',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($this->admin1)
            ->postJson('/api/v1/admin/change-requests', [
                'type' => 'not_a_type',
                'payload' => [],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, ChangeRequest::count());
    }

    public function test_approve_requires_fresh_reauthentication(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->actingAs($this->admin1)
            ->postJson($this->approveUrl($changeRequest))
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'REAUTH_REQUIRED');

        $this->assertSame(0, ChangeRequestApproval::count());
    }

    public function test_reauth_checks_password_then_allows_approval(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->actingAs($this->admin1)
            ->postJson('/api/v1/admin/reauth', ['password' => 'wrong-password'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'REAUTH_FAILED');

        $this->postJson('/api/v1/admin/reauth', ['password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.reauth_minutes', 5);

        $this->postJson($this->approveUrl($changeRequest))
            ->assertOk()
            ->assertJsonPath('data.approvals_count', 1);
    }

    public function test_creator_can_approve_own_change_request(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->approveAs($changeRequest, $this->admin1)
            ->assertOk()
            ->assertJsonPath('data.approvals_count', 1)
            ->assertJsonPath('data.status', ChangeRequest::STATUS_PENDING)
            ->assertJsonPath('data.my_decision', 'approve');
    }

    public function test_second_admin_approval_executes_payload_once(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->approveAs($changeRequest, $this->admin1)->assertOk();

        $this->assertSame('owner', $this->target->refresh()->role);
        $this->assertNull($changeRequest->refresh()->executed_at);

        $this->approveAs($changeRequest, $this->admin2)
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_EXECUTED)
            ->assertJsonPath('data.approvals_count', 2);

        $changeRequest->refresh();

        $this->assertNotNull($changeRequest->executed_at);
        $this->assertSame('admin', $this->target->refresh()->role);
        $this->assertSame(1, AuditLog::query()->where('action', 'change_request.executed')->count());

        $this->approveAs($changeRequest, $this->admin3)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHANGE_REQUEST_CLOSED');

        $this->assertSame(1, AuditLog::query()->where('action', 'change_request.executed')->count());
        $this->assertSame(2, ChangeRequestApproval::query()
            ->where('change_request_id', $changeRequest->id)
            ->count());
    }

    public function test_guest_cannot_access_quorum_api(): void
    {
        $this->getJson('/api/v1/admin/change-requests')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_admin_cannot_vote_twice(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->approveAs($changeRequest, $this->admin1)->assertOk();

        $this->approveAs($changeRequest, $this->admin1)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ALREADY_VOTED');

        $this->assertSame(1, ChangeRequestApproval::query()
            ->where('change_request_id', $changeRequest->id)
            ->count());
    }

    public function test_unique_index_blocks_duplicate_votes_at_database_level(): void
    {
        $changeRequest = $this->createChangeRequest();

        ChangeRequestApproval::create([
            'change_request_id' => $changeRequest->id,
            'admin_id' => $this->admin1->id,
            'decision' => 'approve',
        ]);

        $this->expectException(QueryException::class);

        ChangeRequestApproval::create([
            'change_request_id' => $changeRequest->id,
            'admin_id' => $this->admin1->id,
            'decision' => 'reject',
        ]);
    }

    public function test_rejections_reach_threshold_and_do_not_execute(): void
    {
        $changeRequest = $this->createChangeRequest();

        $this->actingAs($this->admin2)
            ->postJson($this->rejectUrl($changeRequest))
            ->assertOk()
            ->assertJsonPath('data.rejections_count', 1)
            ->assertJsonPath('data.status', ChangeRequest::STATUS_PENDING);

        $this->actingAs($this->admin3)
            ->postJson($this->rejectUrl($changeRequest))
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_REJECTED)
            ->assertJsonPath('data.rejections_count', 2);

        $this->assertSame('owner', $this->target->refresh()->role);
        $this->assertNull($changeRequest->refresh()->executed_at);

        $this->approveAs($changeRequest, $this->admin1)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CHANGE_REQUEST_CLOSED');

        $this->assertDatabaseHas('audit_logs', ['action' => 'change_request.rejected']);
    }

    public function test_playbook_update_executes_and_bumps_version(): void
    {
        $changeRequest = $this->createChangeRequest(
            ChangeRequest::TYPE_PLAYBOOK_UPDATE,
            ['slug' => 'rental', 'max_ttl_minutes' => 120],
        );

        $this->approveAs($changeRequest, $this->admin1)->assertOk();
        $this->approveAs($changeRequest, $this->admin2)
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_EXECUTED);

        $playbook = $this->playbook->refresh();

        $this->assertSame(120, $playbook->max_ttl_minutes);
        $this->assertSame(60, $playbook->default_ttl_minutes);
        $this->assertSame(2, $playbook->version);
    }

    public function test_agent_allowlist_update_executes(): void
    {
        $changeRequest = $this->createChangeRequest(
            ChangeRequest::TYPE_AGENT_ALLOWLIST_UPDATE,
            ['client_id' => 'trustgate-agent', 'allowed_playbooks' => ['clinic']],
        );

        $this->approveAs($changeRequest, $this->admin1)->assertOk();
        $this->approveAs($changeRequest, $this->admin2)->assertOk();

        $this->assertSame(['clinic'], $this->agent->refresh()->allowed_playbooks);
    }

    public function test_role_revoke_falls_back_to_owner(): void
    {
        $this->target->forceFill(['role' => 'admin'])->save();

        $changeRequest = $this->createChangeRequest(
            ChangeRequest::TYPE_ROLE_REVOKE,
            ['email' => 'target@example.com', 'role' => 'admin'],
        );

        $this->approveAs($changeRequest, $this->admin1)->assertOk();
        $this->approveAs($changeRequest, $this->admin2)->assertOk();

        $this->assertSame('owner', $this->target->refresh()->role);
    }

    public function test_threshold_update_applies_to_future_requests(): void
    {
        $thresholdRequest = $this->createChangeRequest(
            ChangeRequest::TYPE_QUORUM_THRESHOLD_UPDATE,
            ['required_approvals' => 3],
        );

        $this->approveAs($thresholdRequest, $this->admin1)->assertOk();
        $this->approveAs($thresholdRequest, $this->admin2)
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_EXECUTED);

        $nextRequest = $this->createChangeRequest();

        $this->assertSame(3, $nextRequest->refresh()->required_approvals);

        $this->approveAs($nextRequest, $this->admin1)->assertOk();
        $this->approveAs($nextRequest, $this->admin2)
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_PENDING)
            ->assertJsonPath('data.approvals_count', 2);

        $this->approveAs($nextRequest, $this->admin3)
            ->assertOk()
            ->assertJsonPath('data.status', ChangeRequest::STATUS_EXECUTED);
    }

    public function test_direct_change_endpoints_return_quorum_required(): void
    {
        $this->actingAs($this->admin1)
            ->postJson('/api/v1/admin/direct/roles/grant', ['email' => 'target@example.com'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUORUM_REQUIRED')
            ->assertJsonPath('error.details.change_request_type', ChangeRequest::TYPE_ROLE_GRANT);

        $this->actingAs($this->admin1)
            ->patchJson('/api/v1/admin/direct/playbooks/rental', ['max_ttl_minutes' => 5])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUORUM_REQUIRED');

        $this->actingAs($this->admin1)
            ->patchJson('/api/v1/admin/direct/quorum-threshold', ['required_approvals' => 1])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'QUORUM_REQUIRED');

        $this->assertSame(1440, $this->playbook->refresh()->max_ttl_minutes);
    }

    public function test_quorum_meta_exposes_threshold_reauth_and_qea_status(): void
    {
        $this->actingAs($this->admin1)
            ->getJson('/api/v1/admin/quorum/meta')
            ->assertOk()
            ->assertJsonPath('data.threshold', 2)
            ->assertJsonPath('data.qea.enabled', false)
            ->assertJsonPath('data.reauth.required', true)
            ->assertJsonPath('data.reauth.minutes', 5)
            ->assertJsonPath('data.reauth.is_fresh', false)
            ->assertJsonCount(5, 'data.types')
            ->assertJsonCount(2, 'data.playbooks')
            ->assertJsonCount(1, 'data.agents')
            ->assertJsonCount(5, 'data.users');

        config(['quorum.qea_enabled' => true]);

        $this->actingAs($this->admin1)
            ->withSession(['admin_reauth_at' => now()->getTimestamp()])
            ->getJson('/api/v1/admin/quorum/meta')
            ->assertOk()
            ->assertJsonPath('data.qea.enabled', true)
            ->assertJsonPath('data.reauth.is_fresh', true);
    }

    public function test_quorum_page_and_login_page_render(): void
    {
        $this->withoutVite();

        $this->get('/login')->assertOk()->assertSee('Đăng nhập', false);

        $this->actingAs($this->admin1)->get('/admin/quorum')
            ->assertOk()
            ->assertSee('quorum-root');

        $this->actingAs($this->owner)->get('/admin/quorum')->assertForbidden();
    }

    public function test_login_and_logout_flow(): void
    {
        $this->post('/login', [
            'email' => 'admin1@example.com',
            'password' => 'password',
        ])->assertRedirect('/admin/quorum');

        $this->assertAuthenticatedAs($this->admin1);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function createCrResponse(string $type, array $payload)
    {
        return $this->actingAs($this->admin1)
            ->postJson('/api/v1/admin/change-requests', [
                'type' => $type,
                'payload' => $payload,
            ]);
    }

    private function createChangeRequest(
        string $type = ChangeRequest::TYPE_ROLE_GRANT,
        array $payload = ['email' => 'target@example.com', 'role' => 'admin'],
    ): ChangeRequest {
        $response = $this->createCrResponse($type, $payload)->assertCreated();

        return ChangeRequest::query()->findOrFail($response->json('data.id'));
    }

    private function approveAs(ChangeRequest $changeRequest, User $admin)
    {
        return $this->actingAs($admin)
            ->withSession(['admin_reauth_at' => now()->getTimestamp()])
            ->postJson($this->approveUrl($changeRequest));
    }

    private function approveUrl(ChangeRequest $changeRequest): string
    {
        return "/api/v1/admin/change-requests/{$changeRequest->id}/approve";
    }

    private function rejectUrl(ChangeRequest $changeRequest): string
    {
        return "/api/v1/admin/change-requests/{$changeRequest->id}/reject";
    }
}
