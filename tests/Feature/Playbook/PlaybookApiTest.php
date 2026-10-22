<?php

namespace Tests\Feature\Playbook;

use App\Models\Playbook;
use App\Models\User;
use Database\Seeders\PlaybookSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FR-PB-01..02 (issue 05): GET /api/v1/playbooks cho owner/admin.
 */
class PlaybookApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlaybookSeeder::class);
    }

    public function test_owner_sees_both_active_playbooks_with_scope_and_ttl_options(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->getJson('/api/v1/playbooks');

        $response->assertOk()->assertJsonCount(2, 'data');

        $slugs = collect($response->json('data'))->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(['rental', 'clinic'], $slugs);

        $clinic = collect($response->json('data'))->firstWhere('slug', 'clinic');

        $this->assertSame(['insurance_card', 'medical_summary'], $clinic['required_doc_types']);
        $this->assertSame(['lab_result'], $clinic['optional_doc_types']);
        $this->assertContains('income_proof', $clinic['forbidden_doc_types']);
        $this->assertSame('Phòng khám', $clinic['recipient_label']);
        $this->assertSame(15, $clinic['default_ttl_minutes']);
        $this->assertSame(60, $clinic['max_ttl_minutes']);
        // TTL options phải được lọc theo max_ttl_minutes của playbook.
        $this->assertSame([5, 15, 60], $clinic['ttl_options']);
        $this->assertTrue($clinic['active']);
    }

    public function test_rental_ttl_options_stay_within_24h(): void
    {
        $owner = User::factory()->owner()->create();

        $rental = collect($this->actingAs($owner)->getJson('/api/v1/playbooks')->json('data'))
            ->firstWhere('slug', 'rental');

        $this->assertSame([5, 15, 60, 1440], $rental['ttl_options']);
        $this->assertSame(1440, $rental['max_ttl_minutes']);
    }

    public function test_admin_also_sees_playbooks_but_agent_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($admin)->getJson('/api/v1/playbooks')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($agent)->getJson('/api/v1/playbooks')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'FORBIDDEN_ROLE');
    }

    public function test_inactive_playbook_is_hidden_from_owner(): void
    {
        $owner = User::factory()->owner()->create();
        Playbook::where('slug', 'clinic')->update(['active' => false]);

        $response = $this->actingAs($owner)->getJson('/api/v1/playbooks');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('rental', $response->json('data.0.slug'));
    }

    public function test_guest_cannot_list_playbooks(): void
    {
        $this->getJson('/api/v1/playbooks')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_seed_rules_forbid_doc_types_that_appear_in_scope(): void
    {
        // Quy tắc cứng FR-PB-03: forbidden không bao giờ trùng required/optional.
        foreach ([Playbook::where('slug', 'rental')->first(), Playbook::where('slug', 'clinic')->first()] as $playbook) {
            $overlap = array_intersect(
                $playbook->forbidden_doc_types ?? [],
                array_merge($playbook->required_doc_types ?? [], $playbook->optional_doc_types ?? []),
            );

            $this->assertSame([], array_values($overlap), "Playbook {$playbook->slug} có forbidden trùng phạm vi.");
        }
    }
}
