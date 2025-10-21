<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{

    
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            // REMOVED: 'role' => 'student', - using Spatie permissions instead
        ];
    }

    public function instructor(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('instructor');
        });
    }

    public function student(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('student');
        });
    }

    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $user->assignRole('admin');
        });
    }

    
}