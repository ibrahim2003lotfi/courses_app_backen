<?php

// Final working solution with manual auth
Route::get('/instructor/my-courses', function (Request $request) {
    // Get token from header
    $authHeader = $request->header('Authorization');
    $userId = null;
    $userName = null;
    
    if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
        $token = substr($authHeader, 7);
        
        // Lookup token in database directly
        $tokenRecord = \DB::table('personal_access_tokens')
            ->where('token', hash('sha256', $token))
            ->first();
        
        if ($tokenRecord) {
            $userId = $tokenRecord->tokenable_id;
            // Get user name from users table
            $user = \DB::table('users')->where('id', $userId)->first();
            if ($user) {
                $userName = $user->name;
            }
        }
    }
    
    // Check if user is authenticated
    if (!$userId) {
        return response()->json([
            'success' => false,
            'error' => 'no_user',
            'message' => 'يجب تسجيل الدخول أولاً',
            'courses' => [],
        ], 401);
    }
    
    // Get courses for this instructor
    $courses = \DB::table('courses')
        ->where('instructor_id', $userId)
        ->orderBy('created_at', 'desc')
        ->get();
    
    // Map courses to response format
    $mappedCourses = [];
    foreach ($courses as $course) {
        $mappedCourses[] = [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'description' => $course->description,
            'price' => $course->price,
            'level' => $course->level,
            'course_image_url' => $course->course_image_url,
            'total_students' => $course->total_students ?? 0,
            'rating' => $course->rating ?? 0,
            'created_at' => $course->created_at,
            'instructor' => [
                'id' => $userId,
                'name' => $userName,
            ],
            'category' => $course->category_id ? [
                'id' => $course->category_id,
                'name' => 'Category',
            ] : null,
        ];
    }
    
    return response()->json([
        'success' => true,
        'instructor' => $userName,
        'total_courses' => count($mappedCourses),
        'courses' => $mappedCourses,
    ]);
});
