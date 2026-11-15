<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Change the email/password in .env (ADMIN_EMAIL / ADMIN_PASSWORD) before going to production.
        User::updateOrCreate(
            ['email' => mb_strtolower(trim(env('ADMIN_EMAIL', 'admin@example.com')))],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => env('ADMIN_PASSWORD', 'ChangeMe123!'),
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
