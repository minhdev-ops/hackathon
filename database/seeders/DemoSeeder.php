<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AgentTask;
use App\Models\Document;
use App\Models\Grant;
use App\Models\Playbook;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Dữ liệu seed cho demo «Làm admin xấu» (FR-DEMO-05).
 * Idempotent: chạy lại không trùng dữ liệu.
 */
class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Salt cố định cho tài khoản demo Lan so browser luôn derive đúng key
     * từ passphrase «password» (chỉ dùng cho dữ liệu demo).
     */
    private const DEMO_SALT_B64 = 'dHJ1c3RnYXRlLWRlbW8tdmF1bHQtc2FsdC0wMDAx';

    public function run(): void
    {
        $this->seedUsers();
        // Một nguồn dữ liệu playbook duy nhất (issue 05): PlaybookSeeder.
        $this->call(PlaybookSeeder::class);
        $this->seedDocuments();
        $this->seedSampleTask();
    }

    private function seedUsers(): void
    {
        $users = [
            ['name' => 'lan', 'role' => 'owner', 'email' => 'lan@example.com'],
            ['name' => 'admin1', 'role' => 'admin', 'email' => 'admin1@example.com'],
            ['name' => 'admin2', 'role' => 'admin', 'email' => 'admin2@example.com'],
            ['name' => 'admin3', 'role' => 'admin', 'email' => 'admin3@example.com'],
            ['name' => 'trustgate-agent', 'role' => 'agent', 'email' => 'agent@example.com'],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => bcrypt('password'), 'email_verified_at' => now()])
            );

            if ($user->role === 'owner' && blank($user->vault_kdf_salt)) {
                $user->forceFill(['vault_kdf_salt' => self::DEMO_SALT_B64])->save();
            }
        }
    }

    private function seedDocuments(): void
    {
        $lan = User::where('email', 'lan@example.com')->first();

        if (! $lan) {
            return;
        }

        $docs = [
            ['doc_type' => 'id_card', 'title' => 'CMND Lan'],
            ['doc_type' => 'income_proof', 'title' => 'Xác nhận thu nhập Lan'],
            ['doc_type' => 'rental_history', 'title' => 'Lịch sử thuê nhà Lan'],
            ['doc_type' => 'insurance_card', 'title' => 'Thẻ bảo hiểm Lan'],
            ['doc_type' => 'medical_summary', 'title' => 'Tóm tắt bệnh án Lan'],
        ];

        $vaultKey = hash_pbkdf2(
            'sha256',
            'password',
            base64_decode(self::DEMO_SALT_B64),
            210_000,
            32,
            true,
        );

        foreach ($docs as $doc) {
            $plaintext = $this->demoFile($doc['title'], $doc['doc_type']);

            $iv = random_bytes(12);
            $ciphertext = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $vaultKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                16,
            );

            $titleIv = random_bytes(12);
            $titleCiphertext = openssl_encrypt(
                $doc['title'],
                'aes-256-gcm',
                $vaultKey,
                OPENSSL_RAW_DATA,
                $titleIv,
                $titleTag,
                '',
                16,
            );

            if ($ciphertext === false || $titleCiphertext === false) {
                throw new \RuntimeException('Không mã hoá được dữ liệu demo.');
            }

            $path = "documents/demo/{$lan->getKey()}/{$doc['doc_type']}.bin";
            Storage::disk('local')->put($path, $ciphertext.$tag);

            $document = Document::withTrashed()->firstOrNew([
                'owner_id' => $lan->getKey(),
                'doc_type' => $doc['doc_type'],
            ]);

            $document->forceFill([
                'title_ct' => base64_encode($titleIv.$titleCiphertext.$titleTag),
                'ciphertext_path' => $path,
                'iv' => base64_encode($iv),
                'sha256' => hash('sha256', $plaintext),
                'size_bytes' => strlen($plaintext),
                'enc_mode' => Document::ENC_MODE_FALLBACK,
                'deleted_at' => null,
            ])->save();
        }
    }

    private function demoFile(string $title, string $docType): string
    {
        return "%PDF-1.4\n"
            ."% TrustGate demo document\n"
            ."1 0 obj\n<< /Title ({$title}) /Subject ({$docType}) >>\nendobj\n"
            ."%%EOF\n";
    }

    /**
     * Một task + grant pending để màn hình chủ và màn hình consent có dữ liệu ngay.
     */
    private function seedSampleTask(): void
    {
        $lan = User::where('email', 'lan@example.com')->first();
        $agent = Agent::where('client_id', 'trustgate-agent')->first();
        $playbook = Playbook::where('slug', 'rental')->first();

        if (! $lan || ! $agent || ! $playbook) {
            return;
        }

        $instruction = 'Nộp hồ sơ thuê căn hộ demo';

        $task = AgentTask::firstOrCreate(
            ['owner_id' => $lan->getKey(), 'instruction' => $instruction],
            [
                'agent_id' => $agent->getKey(),
                'playbook_id' => $playbook->getKey(),
                'status' => AgentTask::STATUS_AWAITING_APPROVAL,
                'proposal' => [
                    'required' => ['id_card', 'income_proof'],
                    'optional' => ['rental_history', 'reference_letter'],
                    'rationale' => 'Chủ nhà cần giấy tờ tuỳ thân và chứng minh thu nhập trước khi ký hợp đồng.',
                ],
                'log' => [],
            ],
        );

        Grant::firstOrCreate(
            ['task_id' => $task->getKey()],
            [
                'owner_id' => $lan->getKey(),
                'agent_id' => $agent->getKey(),
                'purpose' => 'Nộp hồ sơ thuê nhà',
                'status' => Grant::STATUS_PENDING,
            ],
        );
    }
}
