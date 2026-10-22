<?php

namespace Tests\Feature\Infra;

use App\Models\Document;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * NFR-SEC-01 (issue 11): plaintext/key không được xuất hiện trong audit,
 * response API hay cột dữ liệu của Vault.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const FORBIDDEN = [
        'content_b64',
        'grant_key_b64',
        'grant_key',
        'passphrase',
        'password',
        'BEGIN RSA PRIVATE KEY',
        'CMND Lan',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_audit_log_never_contains_secrets_or_plaintext(): void
    {
        $owner = User::where('email', 'lan@example.com')->firstOrFail();

        // Chạy một luồng thật để sinh audit (tạo task → proposal → grant pending).
        $this->actingAs($owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
        ])->assertCreated();

        $rows = DB::table('audit_logs')
            ->get(['action', 'actor_type', 'subject_type', 'subject_id', 'meta']);

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $haystack = json_encode((array) $row, JSON_UNESCAPED_UNICODE);

            foreach (self::FORBIDDEN as $needle) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $needle,
                    (string) $haystack,
                    "audit_logs chứa chuỗi nhạy cảm «{$needle}».",
                );
            }
        }
    }

    public function test_api_responses_do_not_leak_plaintext_or_internal_paths(): void
    {
        $owner = User::where('email', 'lan@example.com')->firstOrFail();

        $this->actingAs($owner)->postJson('/api/v1/tasks', [
            'playbook_slug' => 'rental',
            'instruction' => 'Nộp hồ sơ thuê căn hộ A',
        ])->assertCreated();

        $body = (string) $this->actingAs($owner)->getJson('/api/v1/tasks')->getContent();

        foreach (self::FORBIDDEN as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $body);
        }

        $this->assertStringNotContainsString('ciphertext_path', $body);
    }

    public function test_vault_columns_hold_ciphertext_not_plaintext_titles(): void
    {
        $documents = Document::all();

        $this->assertNotEmpty($documents);

        foreach ($documents as $document) {
            $this->assertStringNotContainsString('CMND Lan', $document->title_ct);
            $this->assertNotSame('CMND Lan', $document->title_ct);
            $this->assertNotEmpty($document->iv);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/i', $document->sha256);
        }
    }
}
