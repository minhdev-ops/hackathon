<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Playbook;
use Illuminate\Database\Seeder;

class PlaybookSeeder extends Seeder
{
    /**
     * Idempotent seed for the two demo playbooks and the demo agent (Phụ lục A).
     */
    public function run(): void
    {
        Playbook::updateOrCreate(
            ['slug' => 'rental'],
            [
                'name' => 'Thuê nhà',
                'description' => 'Hồ sơ thuê nhà gửi chủ nhà.',
                'required_doc_types' => ['id_card', 'income_proof'],
                'optional_doc_types' => ['rental_history', 'reference_letter'],
                'forbidden_doc_types' => ['medical_summary', 'lab_result', 'insurance_card'],
                'recipient_label' => 'Chủ nhà',
                'default_ttl_minutes' => 60,
                'max_ttl_minutes' => 1440,
                'version' => 1,
                'active' => true,
            ],
        );

        Playbook::updateOrCreate(
            ['slug' => 'clinic'],
            [
                'name' => 'Khám bệnh',
                'description' => 'Hồ sơ khám bệnh gửi phòng khám.',
                'required_doc_types' => ['insurance_card', 'medical_summary'],
                'optional_doc_types' => ['lab_result'],
                'forbidden_doc_types' => ['income_proof', 'rental_history'],
                'recipient_label' => 'Phòng khám',
                'default_ttl_minutes' => 15,
                'max_ttl_minutes' => 60,
                'version' => 1,
                'active' => true,
            ],
        );

        Agent::updateOrCreate(
            ['client_id' => 'trustgate-agent'],
            [
                'name' => 'TrustGate Agent',
                'allowed_playbooks' => ['rental', 'clinic'],
                'status' => Agent::STATUS_ACTIVE,
            ],
        );
    }
}
