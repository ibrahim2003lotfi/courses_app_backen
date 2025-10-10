<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Category;
use App\Models\Course;

class CourseFactory extends Factory
{
    // اربط هذه الفاكتوري بالموديل الصحيح
    protected $model = Course::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->sentence(3);
        return [
            'id' => (string) Str::uuid(),
            // عند استخدام User::factory()->instructor() Laravel سيُنشئ المستخدم ويضع الـ id
            'instructor_id' => User::factory()->instructor(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title) . '-' . Str::random(5),
            'description' => $this->faker->paragraph(3),
            'price' => $this->faker->randomFloat(2, 10, 150),
            'level' => $this->faker->randomElement(['beginner', 'intermediate', 'advanced']),
            'total_students' => 0,
            'rating' => $this->faker->randomFloat(2, 3, 5),
        ];
    }
}
