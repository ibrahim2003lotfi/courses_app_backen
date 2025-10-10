<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Section;
use App\Models\Lesson;

class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'section_id' => Section::factory(),
            'title' => 'Lesson ' . $this->faker->word(),
            'description' => $this->faker->sentence(),
            's3_key' => 'placeholder/' . Str::random(10) . '.mp4',
            'duration_seconds' => $this->faker->numberBetween(60, 600),
            'is_preview' => $this->faker->boolean(20),
            'position' => $this->faker->numberBetween(1, 10),
        ];
    }
}
