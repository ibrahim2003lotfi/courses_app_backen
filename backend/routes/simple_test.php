<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use App\Models\Course;
use App\Models\University;
use App\Models\Faculty;
use App\Models\Category;
use Illuminate\Support\Str;

// Simple course creation without validation
Route::post('/api/simple-course-create', function (Request $request) {
    try {
        Log::info('SIMPLE CREATE - Input: ' . json_encode($request->all()));
        
        $title = $request->input('title', 'Untitled');
        $description = $request->input('description', '');
        $price = $request->input('price', 0);
        $universityId = $request->input('university_id');
        $universityName = $request->input('university_name');
        $facultyId = $request->input('faculty_id');
        $facultyName = $request->input('faculty_name');
        
        // Get first user as instructor
        $instructorId = \App\Models\User::query()->value('id');
        
        if (!$instructorId) {
            return response()->json([
                'success' => false,
                'message' => 'No users found',
            ], 500);
        }
        
        // Create course
        $course = Course::create([
            'instructor_id' => $instructorId,
            'title' => $title,
            'slug' => Str::slug($title) . '-' . time(),
            'description' => $description,
            'price' => is_numeric($price) ? $price : 0,
            'is_university_course' => true,
            'university_id' => $universityId,
            'faculty_id' => $facultyId,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Course created!',
            'course_id' => $course->id,
        ]);
        
    } catch (\Exception $e) {
        Log::error('SIMPLE CREATE ERROR: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
        ], 500);
    }
});
