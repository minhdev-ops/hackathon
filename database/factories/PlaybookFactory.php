<?php

namespace Database\Factories;

use App\Models\Playbook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playbook>
 */
class PlaybookFactory extends Factory
{
    protected $model = Playbook::class;

    public function definition(): array
    {
        return [
            'slug' => 'rental-'.fake()->unique()->numerify('####'),
            'name' => 'Thuê nhà',
            'description' => 'Hồ sơ thuê nhà',
            'required_doc_types' => ['id_card', 'income_proof'],
            'optional_doc_types' => ['rental_history', 'reference_letter'],
            'forbidden_doc_types' => ['medical_summary', 'lab_result', 'insurance_card'],
            'recipient_label' => 'Chủ nhà',
            'default_ttl_minutes' => 60,
            'max_ttl_minutes' => 1440,
            'version' => 1,
            'active' => true,
        ];
    }
}
