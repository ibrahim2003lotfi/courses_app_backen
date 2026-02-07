<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InstructorCourseController extends Controller
{
    public function myCourses(Request $request)
    {
        // TEST: Just return hardcoded response
        return response()->json([
            'success' => true,
            'instructor' => 'Test Instructor',
            'total_courses' => 0,
            'courses' => [],
            'message' => 'Test without auth',
        ]);
    }
}
