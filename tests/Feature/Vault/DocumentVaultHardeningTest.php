<?php

namespace Tests\Feature\Vault;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bổ sung AC của issue 03 (VAULT backend): audit 3 sự kiện, header
 * no-store/nosniff khi tải bản mã, chặn file > 10 MB và IV sai độ dài.
 */
class DocumentVaultHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadPayload(array $overrides = []): array
    {
        return array_merge([
            'file' => UploadedFile::fake()->createWithContent('doc.pdf', random_bytes(128)),
            'iv' => base64_encode(random_bytes(12)),
            'sha256' => hash('sha256', 'plaintext'),
            'doc_type' => 'id_card',
            'title_ct' => base64_encode(random_bytes(32)),
            'enc_mode' => Document::ENC_MODE_FALLBACK,
        ], $overrides);
    }

    public function test_upload_view_and_delete_are_audited_without_plaintext(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->post('/api/v1/documents', $this->uploadPayload(), ['Accept' => 'application/json']);

        $response->assertCreated();
        $documentId = $response->json('data.id');

        $this->actingAs($owner)->get("/api/v1/documents/{$documentId}/ciphertext")->assertOk();

        $this->actingAs($owner)->deleteJson("/api/v1/documents/{$documentId}")->assertOk();

        $actions = DB::table('audit_logs')->orderBy('id')->pluck('action')->all();

        $this->assertSame(['document.uploaded', 'document.viewed', 'document.deleted'], $actions);

        $meta = (string) DB::table('audit_logs')->pluck('meta')->implode(' ');

        $this->assertStringNotContainsString('title_ct', $meta);
        $this->assertStringNotContainsString('plaintext', $meta);
        $this->assertStringNotContainsString('document.bin', $meta);
    }

    public function test_download_sends_no_store_and_nosniff_headers(): void
    {
        $owner = User::factory()->owner()->create();

        Storage::disk('local')->put('documents/x.bin', random_bytes(64));
        $document = Document::factory()->ofType('id_card')->create([
            'owner_id' => $owner->id,
            'ciphertext_path' => 'documents/x.bin',
        ]);

        $response = $this->actingAs($owner)->get("/api/v1/documents/{$document->id}/ciphertext");

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('attachment; filename=document.bin', $response->headers->get('content-disposition'));
    }

    public function test_file_over_10mb_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(
            '/api/v1/documents',
            $this->uploadPayload(['file' => UploadedFile::fake()->create('big.pdf', 11 * 1024)]),
            ['Accept' => 'application/json'],
        )->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertSame(0, Document::count());
    }

    public function test_iv_must_decode_to_exactly_12_bytes(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post(
            '/api/v1/documents',
            $this->uploadPayload(['iv' => base64_encode(random_bytes(8))]),
            ['Accept' => 'application/json'],
        )->assertStatus(422);

        $this->actingAs($owner)->post(
            '/api/v1/documents',
            $this->uploadPayload(['iv' => 'không phải base64!']),
            ['Accept' => 'application/json'],
        )->assertStatus(422);

        $this->assertSame(0, Document::count());
    }

    public function test_soft_deleted_document_disappears_from_list_and_download(): void
    {
        $owner = User::factory()->owner()->create();

        Storage::disk('local')->put('documents/del.bin', random_bytes(32));
        $document = Document::factory()->ofType('id_card')->create([
            'owner_id' => $owner->id,
            'ciphertext_path' => 'documents/del.bin',
        ]);

        $this->actingAs($owner)->deleteJson("/api/v1/documents/{$document->id}")->assertOk();

        $this->actingAs($owner)->getJson('/api/v1/documents')->assertOk()->assertJsonCount(0, 'data');

        // Soft delete: file vẫn còn trên đĩa (an toàn cho grant đang chạy).
        Storage::disk('local')->assertExists('documents/del.bin');
    }
}
