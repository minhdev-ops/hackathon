<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlaybookSeeder::class);
        $this->call(QuorumSeeder::class);

        if (env('APP_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
