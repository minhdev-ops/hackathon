<?php

namespace Tests\Feature\Infra;

use App\Models\Agent;
use App\Models\Document;
use App\Models\Playbook;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NFR-REL-01 (issue 11): seeder idempotent — chạy lại không nhân đôi dữ liệu.
 */
class SeederIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_can_run_twice_without_duplicating_rows(): void
    {
        $this->seed(DemoSeeder::class);
        $first = $this->counts();

        $this->seed(DemoSeeder::class);
        $second = $this->counts();

        $this->assertSame($first, $second);
    }

    public function test_full_database_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $first = $this->counts();

        $this->seed(DatabaseSeeder::class);
        $second = $this->counts();

        $this->assertSame($first, $second);
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'users' => User::query()->count(),
            'playbooks' => Playbook::query()->count(),
            'agents' => Agent::query()->count(),
            'documents' => Document::query()->count(),
        ];
    }
}
