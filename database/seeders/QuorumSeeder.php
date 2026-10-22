<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class QuorumSeeder extends Seeder
{
    /**
     * Tài khoản demo (Phụ lục A): lan (owner) + admin1..admin3 (admin).
     * Mật khẩu demo: "password".
     */
    public function run(): void
    {
        $accounts = [
            ['name' => 'Lan', 'email' => 'lan@example.com', 'role' => 'owner'],
            ['name' => 'Admin 1', 'email' => 'admin1@example.com', 'role' => 'admin'],
            ['name' => 'Admin 2', 'email' => 'admin2@example.com', 'role' => 'admin'],
            ['name' => 'Admin 3', 'email' => 'admin3@example.com', 'role' => 'admin'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'role' => $account['role'],
                    'email_verified_at' => now(),
                    'password' => 'password',
                ],
            );
        }
    }
}
