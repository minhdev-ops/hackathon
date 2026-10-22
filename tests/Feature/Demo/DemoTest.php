<?php

namespace Tests\Feature\Demo;

use App\Jobs\SlowRunAgentJob;
use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use App\Models\User;
use App\Services\GrantKeyStore;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['trustgate.app_demo' => true]);
        Storage::fake('local');
    }

    public function test_admin_evil_screen_is_only_available_when_demo_is_enabled(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();

        $this->actingAs($admin)->get('/admin/evil')->assertOk();

        $this->actingAs($owner)->get('/admin/evil')->assertStatus(403);

        config(['trustgate.app_demo' => false]);

        $this->actingAs($admin)->get('/admin/evil')->assertStatus(404);
    }

    public function test_demo_out_of_scope_returns_403_and_writes_audit(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/demo/evil/out-of-scope')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'OUT_OF_SCOPE');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agent.document_request.out_of_scope',
            'actor_id' => $admin->id,
            'subject_type' => 'demo',
        ]);

        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_slow_run_creates_active_grant_with_key_and_dispatches_job(): void
    {
        Queue::fake();

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        Agent::factory()->create(['status' => Agent::STATUS_ACTIVE]);
        Playbook::factory()->create(['slug' => 'rental']);

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/demo/evil/slow-run')
            ->assertOk();

        $taskId = $response->json('data.task_id');
        $grantId = $response->json('data.grant_id');

        $task = AgentTask::findOrFail($taskId);
        $grant = Grant::findOrFail($grantId);

        $this->assertNotNull($task->playbook_id);
        $this->assertSame(AgentTask::STATUS_RUNNING, $task->status);
        $this->assertSame(Grant::STATUS_ACTIVE, $grant->status);
        $this->assertSame($owner->id, $grant->owner_id);
        $this->assertNotNull(
            Cache::store(config('trustgate.grant_key_store'))
                ->get(config('trustgate.grant_key_prefix').$grant->id),
        );

        Queue::assertPushed(SlowRunAgentJob::class);
    }

    public function test_reset_removes_old_demo_data_and_reseeds_five_documents(): void
    {
        $admin = User::factory()->admin()->create();
        $this->seed(DemoSeeder::class);

        $lan = User::where('email', 'lan@example.com')->firstOrFail();
        $oldTask = AgentTask::factory()->for($lan, 'owner')->create();
        $oldGrant = Grant::factory()->for($lan, 'owner')->for($oldTask, 'task')->create();

        $this->actingAs($admin)
            ->postJson('/api/v1/demo/reset')
            ->assertOk()
            ->assertJsonPath('data.documents', 5);

        $this->assertDatabaseMissing('agent_tasks', ['id' => $oldTask->id]);
        $this->assertDatabaseMissing('grants', ['id' => $oldGrant->id]);
        $this->assertSame(5, Document::where('owner_id', $lan->id)->count());

        foreach (Document::where('owner_id', $lan->id)->get() as $document) {
            Storage::disk('local')->assertExists($document->ciphertext_path);
        }
    }

    public function test_demo_seeder_writes_ciphertext_that_decrypts_with_demo_passphrase(): void
    {
        $this->seed(DemoSeeder::class);

        $lan = User::where('email', 'lan@example.com')->firstOrFail();
        $this->assertNotNull($lan->vault_kdf_salt);

        $document = Document::where('owner_id', $lan->id)->where('doc_type', 'id_card')->firstOrFail();

        $this->assertNotSame('CMND Lan', $document->title_ct);
        $this->assertNotSame(base64_encode('CMND Lan'), $document->title_ct);

        $key = hash_pbkdf2(
            'sha256',
            'password',
            base64_decode($lan->vault_kdf_salt),
            210_000,
            32,
            true,
        );

        $titleRaw = base64_decode($document->title_ct);
        $titleIv = substr($titleRaw, 0, 12);
        $titleTag = substr($titleRaw, -16);
        $titleCiphertext = substr($titleRaw, 12, -16);

        $this->assertSame('CMND Lan', openssl_decrypt(
            $titleCiphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $titleIv,
            $titleTag,
        ));

        $fileCiphertext = Storage::disk('local')->get($document->ciphertext_path);
        $fileTag = substr($fileCiphertext, -16);
        $fileCiphertext = substr($fileCiphertext, 0, -16);

        $plaintext = openssl_decrypt(
            $fileCiphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($document->iv),
            $fileTag,
        );

        $this->assertNotFalse($plaintext);
        $this->assertSame(hash('sha256', $plaintext), $document->sha256);
        $this->assertStringStartsWith('%PDF-1.4', $plaintext);
    }

    public function test_slow_run_job_stops_when_grant_key_is_gone(): void
    {
        $owner = User::factory()->owner()->create();
        $agent = Agent::factory()->create();
        $task = AgentTask::factory()->for($owner, 'owner')->for($agent, 'agent')->running()->create();
        Grant::factory()->active()->for($task, 'task')->for($owner, 'owner')->for($agent, 'agent')->create();

        (new SlowRunAgentJob($task->id, 2, 0))->handle(app(GrantKeyStore::class));

        $this->assertSame(AgentTask::STATUS_FAILED_REVOKED, $task->refresh()->status);
        $this->assertSame('slow_run.stopped', $task->log[0]['step']);
        $this->assertSame('grant_key_shredded', $task->log[0]['reason']);
    }
}
