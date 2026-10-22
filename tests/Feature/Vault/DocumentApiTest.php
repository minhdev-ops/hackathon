<?php

namespace Tests\Feature\Vault;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_owner_uploads_encrypted_document_and_it_is_not_stored_under_the_original_name(): void
    {
        $owner = User::factory()->owner()->create();
        $ciphertext = random_bytes(256);

        $response = $this->actingAs($owner)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('id-card.pdf', $ciphertext),
            'iv' => base64_encode(random_bytes(12)),
            'sha256' => hash('sha256', 'plaintext'),
            'doc_type' => 'id_card',
            'title_ct' => base64_encode(random_bytes(32)),
            'enc_mode' => Document::ENC_MODE_FALLBACK,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.doc_type', 'id_card')
            ->assertJsonMissingPath('data.ciphertext_path');

        $document = Document::firstOrFail();
        Storage::disk('local')->assertExists($document->ciphertext_path);
        $this->assertStringNotContainsString('id-card', $document->ciphertext_path);
        $this->assertSame(256, $document->size_bytes);
    }

    public function test_owner_downloads_raw_ciphertext_and_other_owner_gets_403(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->owner()->create();
        $ciphertext = random_bytes(128);

        Storage::disk('local')->put('documents/demo.bin', $ciphertext);
        $document = Document::factory()->ofType('id_card')->create([
            'owner_id' => $owner->id,
            'ciphertext_path' => 'documents/demo.bin',
        ]);

        $this->actingAs($owner)
            ->get("/api/v1/documents/{$document->id}/ciphertext")
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->actingAs($other)
            ->getJson("/api/v1/documents/{$document->id}/ciphertext")
            ->assertForbidden();

        $this->actingAs($other)
            ->deleteJson("/api/v1/documents/{$document->id}")
            ->assertForbidden();
    }

    public function test_owner_lists_only_own_documents_and_guest_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $other = User::factory()->owner()->create();
        Document::factory()->create(['owner_id' => $owner->id]);
        Document::factory()->create(['owner_id' => $other->id]);

        $response = $this->actingAs($owner)->getJson('/api/v1/documents');

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonMissingPath('data.0.owner_id');

        Auth::logout();

        $this->getJson('/api/v1/documents')->assertUnauthorized();
    }

    public function test_invalid_metadata_is_rejected_and_delete_is_soft(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->post('/api/v1/documents', [
            'file' => UploadedFile::fake()->createWithContent('doc.pdf', random_bytes(64)),
            'iv' => base64_encode(random_bytes(12)),
            'sha256' => 'not-a-hash',
            'doc_type' => 'passport',
            'title_ct' => base64_encode(random_bytes(32)),
            'enc_mode' => 'nope',
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $document = Document::factory()->create(['owner_id' => $owner->id]);
        Storage::disk('local')->put($document->ciphertext_path, random_bytes(32));

        $this->actingAs($owner)
            ->deleteJson("/api/v1/documents/{$document->id}")
            ->assertOk();

        $this->assertSoftDeleted($document);
    }
}
