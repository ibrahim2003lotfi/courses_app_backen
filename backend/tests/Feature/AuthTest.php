<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the roles
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Test that a user can register successfully
     */
    public function test_user_can_register_successfully()
    {
        // Arrange: Prepare test data
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student'
        ];

        // Act: Make the API request
        $response = $this->postJson('/api/register', $userData);

        // Assert: Verify the response
        $response->assertStatus(201) // HTTP 201 Created
                ->assertJsonStructure([
                    'message',
                    'user' => ['id', 'name', 'email'],
                    'token'
                ]);

        // Additional assertion: Check user was created in database
        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User'
        ]);

        // Check that user has student role
        $user = User::where('email', 'test@example.com')->first();
        $this->assertTrue($user->hasRole('student'));
    }

    /**
     * Test registration validation errors
     */
    public function test_registration_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/register', [
            'name' => '', // Empty name
            'email' => 'invalid-email', // Invalid email
            'password' => 'short', // Too short
            'password_confirmation' => 'mismatch', // Doesn't match
            'role' => 'invalid-role' // Invalid role
        ]);

        $response->assertStatus(422) // HTTP 422 Unprocessable Entity
                ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    /**
     * Test user login functionality
     */
    public function test_user_can_login_with_valid_credentials()
    {
        // Create a user first with student role
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);
        $user->assignRole('student');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'user',
                    'token'
                ]);
    }

    /**
     * Test login fails with invalid credentials
     */
    public function test_login_fails_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123')
        ]);
        $user->assignRole('student');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password'
        ]);

        $response->assertStatus(422); // Or whatever your API returns
    }
}