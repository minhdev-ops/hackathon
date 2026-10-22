<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTimelinePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/timeline')->assertRedirect('/login');
    }

    public function test_agents_cannot_open_the_timeline(): void
    {
        $agent = User::factory()->create(['role' => 'agent']);

        $this->actingAs($agent)->get('/timeline')->assertForbidden();
    }

    public function test_owner_and_admin_can_open_the_timeline(): void
    {
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($owner)
            ->get('/timeline')
            ->assertOk()
            ->assertSee('id="root"', false);

        $this->actingAs($admin)
            ->get('/timeline')
            ->assertOk()
            ->assertSee('id="root"', false);
    }
}
