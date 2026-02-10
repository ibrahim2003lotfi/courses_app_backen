<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Enrollment;
use App\Models\Course;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    /**
     * Enroll the authenticated user in a course - ULTRA MINIMAL VERSION
     */
    public function enroll(Request $request, $courseId)
    {
        // Log at the very start
        error_log('>>> ENROLL START: courseId=' . $courseId);
        
        try {
            // Get user ID from request header (Flutter sends this)
            $userId = $request->header('X-User-Id');
            
            if (!$userId) {
                error_log('>>> NO USER ID PROVIDED');
                return response()->json([
                    'success' => false,
                    'message' => 'User ID required'
                ], 401);
            }
            
            error_log('>>> User ID: ' . $userId);

            // Simple DB check for course
            $course = DB::table('courses')->where('id', $courseId)->first();
            
            if (!$course) {
                error_log('>>> COURSE NOT FOUND: ' . $courseId);
                return response()->json([
                    'success' => false,
                    'message' => 'الدورة غير موجودة'
                ], 404);
            }
            
            error_log('>>> Course found: ' . $course->title);

            // Check if already enrolled
            $existing = DB::table('enrollments')
                ->where('user_id', $userId)
                ->where('course_id', $courseId)
                ->whereNull('refunded_at')
                ->first();

            if ($existing) {
                error_log('>>> ALREADY ENROLLED');
                return response()->json([
                    'success' => false,
                    'message' => 'أنت مسجل مسبقاً في هذه الدورة'
                ], 409);
            }

            // Create enrollment
            $enrollmentId = (string) Str::uuid();
            $now = now();
            
            DB::table('enrollments')->insert([
                'id' => $enrollmentId,
                'user_id' => $userId,
                'course_id' => $courseId,
                'purchased_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            error_log('>>> ENROLLMENT SUCCESS: ' . $enrollmentId);

            return response()->json([
                'success' => true,
                'message' => 'تم التسجيل بنجاح',
                'enrollment' => [
                    'id' => $enrollmentId,
                    'course_id' => $courseId,
                    'purchased_at' => $now->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            error_log('>>> ENROLL ERROR: ' . $e->getMessage());
            error_log('>>> ENROLL TRACE: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'فشل التسجيل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's enrolled courses - ULTRA MINIMAL VERSION
     */
    public function getEnrolledCourses(Request $request)
    {
        error_log('>>> GET ENROLLED COURSES START');
        
        try {
            // Get user ID from header (Flutter sends this)
            $userId = $request->header('X-User-Id');
            
            if (!$userId) {
                error_log('>>> NO USER ID PROVIDED');
                return response()->json([
                    'success' => false,
                    'courses' => [],
                    'total' => 0,
                    'message' => 'User ID required'
                ], 401);
            }
            
            error_log('>>> User ID: ' . $userId);

            // Simple query with minimal data
            $enrollments = DB::table('enrollments')
                ->where('user_id', $userId)
                ->whereNull('refunded_at')
                ->orderBy('purchased_at', 'desc')
                ->get();

            error_log('>>> Found enrollments: ' . $enrollments->count());

            $courses = collect();
            
            foreach ($enrollments as $enrollment) {
                try {
                    // Get course basic info only
                    $course = DB::table('courses')
                        ->where('id', $enrollment->course_id)
                        ->first();
                    
                    if (!$course) {
                        error_log('>>> Course not found: ' . $enrollment->course_id);
                        continue;
                    }

                    // Get instructor name
                    $instructor = DB::table('users')
                        ->where('id', $course->instructor_id)
                        ->first(['id', 'name']);
                    
                    // Get category name
                    $category = null;
                    if ($course->category_id) {
                        $category = DB::table('categories')
                            ->where('id', $course->category_id)
                            ->first(['id', 'name']);
                    }

                    $courses->push([
                        'id' => $course->id,
                        'slug' => $course->slug,
                        'title' => $course->title,
                        'description' => $course->description,
                        'image' => $course->course_image_url ? url('storage/' . $course->course_image_url) : null,
                        'price' => $course->price,
                        'level' => $course->level,
                        'rating' => $course->rating,
                        'instructor' => $instructor ? [
                            'id' => $instructor->id,
                            'name' => $instructor->name,
                        ] : null,
                        'category' => $category ? [
                            'id' => $category->id,
                            'name' => $category->name,
                        ] : null,
                        'sections' => [], // Empty for list view
                        'enrolled_at' => $enrollment->purchased_at,
                        'progress' => $enrollment->progress ?? 0,
                        'total_lessons' => 0,
                        'total_sections' => 0,
                    ]);
                } catch (\Exception $e) {
                    error_log('>>> Error processing enrollment: ' . $e->getMessage());
                    continue;
                }
            }

            $coursesArray = $courses->values();
            error_log('>>> Returning courses: ' . $coursesArray->count());

            return response()->json([
                'success' => true,
                'courses' => $coursesArray,
                'total' => $coursesArray->count()
            ]);

        } catch (\Exception $e) {
            error_log('>>> GET ENROLLED ERROR: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'courses' => [],
                'total' => 0,
                'message' => 'Failed to fetch courses: ' . $e->getMessage()
            ], 500);
        }
    }
}
