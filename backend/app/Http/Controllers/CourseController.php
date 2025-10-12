<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /**
     * Instructor creates a new course.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'level' => 'in:beginner,intermediate,advanced',
            'category_id' => 'nullable|uuid|exists:categories,id',
        ]);

        // إنشاء slug تلقائي بناءً على العنوان
        $slug = Str::slug($validated['title']);

        // تأكد أن الـ slug فريد
        $count = Course::where('slug', 'LIKE', "{$slug}%")->count();
        if ($count > 0) {
            $slug .= '-' . ($count + 1);
        }

        $course = Course::create([
            'instructor_id' => auth('sanctum')->id(),

            'category_id' => $validated['category_id'] ?? null,
            'title' => $validated['title'],
            'slug' => $slug,
            'description' => $validated['description'] ?? '',
            'price' => $validated['price'] ?? 0,
            'level' => $validated['level'] ?? 'beginner',
        ]);

        return response()->json([
            'message' => 'Course created successfully',
            'course' => $course,
        ], 201);
    }
}

