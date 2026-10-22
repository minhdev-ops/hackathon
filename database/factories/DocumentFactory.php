<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'doc_type' => 'id_card',
            'title_ct' => base64_encode(fake()->word()),
            'ciphertext_path' => 'documents/'.fake()->uuid().'.bin',
            'iv' => base64_encode(random_bytes(12)),
            'sha256' => hash('sha256', fake()->uuid()),
            'size_bytes' => fake()->numberBetween(1024, 5_000_000),
            'enc_mode' => Document::ENC_MODE_FALLBACK,
        ];
    }

    public function ofType(string $docType): static
    {
        return $this->state(fn () => ['doc_type' => $docType]);
    }
}
