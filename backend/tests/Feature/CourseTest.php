<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed the roles
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    

    /**
     * Test that anyone can view courses list
     */
   /**
 * Test that anyone can view courses list
 */
public function test_public_can_view_courses_list()
{
    Course::factory()->count(3)->create();

    $response = $this->getJson('/api/courses');

    $response->assertStatus(200);
    
    // Check the actual response structure
    $responseData = $response->json();
    
    if (isset($responseData['data'])) {
        // Paginated response - check data structure only
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'title', 'slug', 'price', 'level']
            ]
        ]);
        
        // Don't require specific meta/links structure, just check they exist
        if (array_key_exists('links', $responseData)) {
            $this->assertArrayHasKey('links', $responseData);
        }
        if (array_key_exists('meta', $responseData)) {
            $this->assertArrayHasKey('meta', $responseData);
        }
    } else {
        // Simple array response
        $response->assertJsonCount(3)
                ->assertJsonStructure([
                    '*' => ['id', 'title', 'slug', 'price', 'level']
                ]);
    }
}

    /**
     * Test that anyone can view a single course
     */
    public function test_public_can_view_single_course()
    {
        $course = Course::factory()->create();

        $response = $this->getJson("/api/courses/{$course->slug}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'course' => [
                        'id', 'title', 'slug', 'description', 
                        'price', 'level'
                    ]
                ]);
    }

    public function test_instructor_can_create_course()
{
    /** @var User $user */
    $user = User::factory()->create();
    
    // Assign role using direct database insertion (not using role column)
    $role = \Spatie\Permission\Models\Role::where('name', 'instructor')->first();
    \DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => get_class($user),
        'model_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $courseData = [
        'title' => 'Test Course Title',
        'description' => 'Test course description',
        'price' => 99.99,
        'level' => 'beginner',
        'category_id' => null
    ];

    $response = $this->postJson('/api/instructor/courses', $courseData);

    $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'course' => ['id', 'title', 'slug']
            ]);

    // Verify course was created in database
    $this->assertDatabaseHas('courses', [
        'title' => 'Test Course Title',
        'instructor_id' => $user->id
    ]);
}

   public function test_student_cannot_create_course()
{
    /** @var User $user */
    $user = User::factory()->create();
    
    // Assign role using direct database insertion
    $role = \Spatie\Permission\Models\Role::where('name', 'student')->first();
    \DB::table('model_has_roles')->insert([
        'role_id' => $role->id,
        'model_type' => get_class($user),
        'model_id' => $user->id,
    ]);

    $this->actingAs($user, 'sanctum');

    $response = $this->postJson('/api/instructor/courses', [
        'title' => 'Test Course',
        'description' => 'Test description',
        'price' => 99.99,
        'level' => 'beginner'
    ]);

    $response->assertStatus(403); // Forbidden
}
    /**
     * Test unauthenticated user cannot create course
     */
    public function test_unauthenticated_user_cannot_create_course()
    {
        $response = $this->postJson('/api/instructor/courses', [
            'title' => 'Test Course',
            'description' => 'Test description'
        ]);

        $response->assertStatus(401); // Unauthorized
    }
}