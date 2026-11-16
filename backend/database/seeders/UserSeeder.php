<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Two fixed accounts so a fresh install can be signed into once auth lands.
     * Every one of them uses the password "password".
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin / Tata Usaha',
                'email' => 'admin@khairulummah.test',
                'role' => UserRole::Admin,
            ],
            [
                'name' => 'Ahmad Fauzi',
                'email' => 'wali.ahmad@example.com',
                'role' => UserRole::Wali,
            ],
            [
                'name' => 'Siti Aminah',
                'email' => 'wali.siti@example.com',
                'role' => UserRole::Wali,
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
