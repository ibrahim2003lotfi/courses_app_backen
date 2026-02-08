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
     * Enroll the authenticated user in a course
     */
    public function enroll(Request $request, $courseId)
    {
        Log::info('🎓 Enrollment attempt started', ['course_id' => $courseId]);
        
        try {
            $user = auth('sanctum')->user();
            
            if (!$user) {
                Log::warning('❌ Enrollment failed: No authenticated user');
                return response()->json([
                    'success' => false,
                    'message' => 'يجب تسجيل الدخول أولاً'
                ], 401);
            }

            Log::info('👤 User authenticated', ['user_id' => $user->id]);

            // Check if course exists
            $course = Course::find($courseId);
            if (!$course) {
                Log::warning('❌ Course not found', ['course_id' => $courseId]);
                return response()->json([
                    'success' => false,
                    'message' => 'الدورة غير موجودة'
                ], 404);
            }

            Log::info('📚 Course found', ['course_id' => $course->id, 'title' => $course->title]);

            // Check if already enrolled
            $existingEnrollment = Enrollment::where('user_id', $user->id)
                ->where('course_id', $courseId)
                ->whereNull('refunded_at')
                ->first();

            if ($existingEnrollment) {
                Log::info('⚠️ Already enrolled', ['user_id' => $user->id, 'course_id' => $courseId]);
                return response()->json([
                    'success' => false,
                    'message' => 'أنت مسجل مسبقاً في هذه الدورة'
                ], 409);
            }

            // Create enrollment with explicit UUID
            $enrollmentId = (string) Str::uuid();
            
            Log::info('📝 Creating enrollment record', [
                'enrollment_id' => $enrollmentId,
                'user_id' => $user->id,
                'course_id' => $courseId
            ]);

            // Use raw query to avoid any model issues
            DB::table('enrollments')->insert([
                'id' => $enrollmentId,
                'user_id' => $user->id,
                'course_id' => $courseId,
                'purchased_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Fetch the created enrollment
            $enrollment = Enrollment::find($enrollmentId);

            Log::info('✅ Enrollment successful', [
                'enrollment_id' => $enrollmentId,
                'user_id' => $user->id,
                'course_id' => $courseId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم التسجيل بنجاح',
                'enrollment' => [
                    'id' => $enrollmentId,
                    'course_id' => $courseId,
                    'purchased_at' => now()->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('💥 Enrollment error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'course_id' => $courseId
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'فشل التسجيل: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's enrolled courses
     */
    public function getEnrolledCourses(Request $request)
    {
        Log::info('📚 Fetching enrolled courses - SIMPLIFIED');
        
        try {
            $user = auth('sanctum')->user();
            
            if (!$user) {
                Log::warning('❌ Fetch failed: No authenticated user');
                return response()->json([
                    'success' => false,
                    'courses' => [],
                    'total' => 0,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            Log::info('👤 User authenticated - returning empty for test', ['user_id' => $user->id]);

            // Return empty for now to test if endpoint works
            return response()->json([
                'success' => true,
                'courses' => [],
                'total' => 0
            ]);

        } catch (\Exception $e) {
            Log::error('💥 Fetch enrolled courses error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'courses' => [],
                'total' => 0,
                'message' => 'Failed to fetch courses: ' . $e->getMessage()
            ], 500);
        }
    }
}
