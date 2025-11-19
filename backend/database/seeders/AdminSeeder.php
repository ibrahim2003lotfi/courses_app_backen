<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
                'role' => 'admin',
                'phone' => '+10000000000',
                'age' => 30,
                'gender' => 'other',
                'verification_method' => 'email',
                'is_verified' => true,
            ]
        );

        $admin->assignRole('admin');
    }
}

