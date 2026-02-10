<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    /**
     * Submit or update a rating for a course (1-5 stars)
     */
    public function store(Request $request, $courseId)
    {
        error_log(">>> REVIEW STORE START: courseId=$courseId");
        
        try {
            // Get user from header (bypass auth:sanctum to prevent memory crashes)
            $userId = $request->header('X-User-Id');
            
            if (!$userId) {
                // Fallback to auth if header not provided
                $user = auth('sanctum')->user();
                if (!$user) {
                    return response()->json(['message' => 'Not authenticated'], 401);
                }
                $userId = $user->id;
            }
            
            error_log(">>> UserId: $userId");
            
            // Check if input is UUID or slug
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $courseId);
            error_log(">>> isUuid: " . ($isUuid ? 'yes' : 'no'));
            
            if ($isUuid) {
                $course = DB::table('courses')->where('id', $courseId)->first();
            } else {
                $course = DB::table('courses')->where('slug', $courseId)->first();
            }
            
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            
            $actualCourseId = $course->id;
            error_log(">>> Actual course ID: $actualCourseId");

            // Check if user is enrolled
            $enrollment = DB::table('enrollments')
                ->where('user_id', $userId)
                ->where('course_id', $actualCourseId)
                ->whereNull('refunded_at')
                ->first();
                
            if (!$enrollment) {
                return response()->json([
                    'message' => 'You must purchase this course before rating it.'
                ], 403);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
            ]);

            // Insert or update rating using DB
            DB::table('reviews')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'course_id' => $actualCourseId,
                ],
                [
                    'rating' => $validated['rating'],
                    'updated_at' => now(),
                ]
            );
            
            // Update course average rating
            $this->updateCourseRating($actualCourseId);

            error_log(">>> REVIEW STORE SUCCESS");
            return response()->json([
                'message' => 'Rating submitted successfully',
                'rating' => $validated['rating'],
            ]);
        } catch (\Exception $e) {
            error_log('>>> REVIEW STORE ERROR: ' . $e->getMessage());
            error_log('>>> TRACE: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error submitting rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update course average rating
     */
    private function updateCourseRating($courseId)
    {
        $stats = DB::table('reviews')
            ->where('course_id', $courseId)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw('AVG(rating) as average')
            )
            ->first();
            
        if ($stats) {
            DB::table('courses')
                ->where('id', $courseId)
                ->update([
                    'rating' => round($stats->average, 1),
                    'total_ratings' => $stats->total,
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Get user's rating for a course
     */
    public function show(Request $request, $courseId)
    {
        error_log(">>> REVIEW SHOW START: courseId=$courseId");
        
        try {
            // Get user from header (bypass auth:sanctum to prevent memory crashes)
            $userId = $request->header('X-User-Id');
            
            if (!$userId) {
                // Fallback to auth if header not provided
                $user = auth('sanctum')->user();
                if (!$user) {
                    return response()->json(['message' => 'Not authenticated'], 401);
                }
                $userId = $user->id;
            }
            
            error_log(">>> UserId: $userId");
            
            // Check if course looks like UUID or slug
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $courseId);
            error_log(">>> isUuid: " . ($isUuid ? 'yes' : 'no'));
            
            if ($isUuid) {
                $courseData = DB::table('courses')->where('id', $courseId)->first();
            } else {
                $courseData = DB::table('courses')->where('slug', $courseId)->first();
            }
            
            error_log(">>> Course found: " . ($courseData ? 'yes' : 'no'));
            
            if (!$courseData) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            
            $actualCourseId = $courseData->id;
            
            // Get user's review using DB query
            $review = DB::table('reviews')
                ->where('course_id', $actualCourseId)
                ->where('user_id', $userId)
                ->first();
            
            error_log(">>> Review found: " . ($review ? 'yes' : 'no'));
            
            // Check if user can rate (has enrollment)
            $enrollment = DB::table('enrollments')
                ->where('course_id', $actualCourseId)
                ->where('user_id', $userId)
                ->whereNull('refunded_at')
                ->first();
            
            error_log(">>> Can rate: " . ($enrollment ? 'yes' : 'no'));
            error_log(">>> REVIEW SHOW SUCCESS");
            
            return response()->json([
                'rating' => $review ? $review->rating : null,
                'can_rate' => $enrollment !== null,
            ]);
        } catch (\Exception $e) {
            error_log('>>> REVIEW SHOW ERROR: ' . $e->getMessage());
            error_log('>>> TRACE: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error fetching rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user's rating
     */
    public function destroy(Request $request, $courseId)
    {
        error_log(">>> REVIEW DESTROY START: courseId=$courseId");
        
        try {
            $user = auth('sanctum')->user();
            
            // Check if input is UUID or slug
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $courseId);
            
            if ($isUuid) {
                $course = DB::table('courses')->where('id', $courseId)->first();
            } else {
                $course = DB::table('courses')->where('slug', $courseId)->first();
            }
            
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            
            $actualCourseId = $course->id;

            // Find and delete review using DB
            $review = DB::table('reviews')
                ->where('user_id', $user->id)
                ->where('course_id', $actualCourseId)
                ->first();

            if (!$review) {
                return response()->json([
                    'message' => 'No rating found to delete.'
                ], 404);
            }

            DB::table('reviews')
                ->where('id', $review->id)
                ->delete();

            // Update course rating
            $this->updateCourseRating($actualCourseId);

            error_log(">>> REVIEW DESTROY SUCCESS");
            return response()->json([
                'message' => 'Rating deleted successfully',
            ]);
        } catch (\Exception $e) {
            error_log('>>> REVIEW DESTROY ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get course rating statistics
     */
    public function getCourseRating($courseId)
    {
        error_log(">>> GET COURSE RATING START: courseId=$courseId");
        
        try {
            // Check if input is UUID or slug
            $isUuid = preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $courseId);
            
            if ($isUuid) {
                $course = DB::table('courses')->where('id', $courseId)->first();
            } else {
                $course = DB::table('courses')->where('slug', $courseId)->first();
            }
            
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }
            
            $actualCourseId = $course->id;

            // Get rating stats using DB
            $stats = DB::table('reviews')
                ->where('course_id', $actualCourseId)
                ->select(
                    DB::raw('COUNT(*) as total_ratings'),
                    DB::raw('AVG(rating) as average_rating'),
                    DB::raw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star'),
                    DB::raw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star'),
                    DB::raw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star'),
                    DB::raw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star'),
                    DB::raw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star')
                )
                ->first();

            $ratingInfo = [
                'average_rating' => $stats ? round($stats->average_rating, 1) : 0,
                'total_ratings' => $stats ? (int) $stats->total_ratings : 0,
                'distribution' => $stats ? [
                    (int) $stats->five_star,
                    (int) $stats->four_star,
                    (int) $stats->three_star,
                    (int) $stats->two_star,
                    (int) $stats->one_star
                ] : [0, 0, 0, 0, 0]
            ];

            error_log(">>> GET COURSE RATING SUCCESS");
            return response()->json([
                'rating_info' => $ratingInfo,
            ]);
        } catch (\Exception $e) {
            error_log('>>> GET COURSE RATING ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's course ratings (all courses rated by user)
     */
    public function getUserRatings()
    {
        error_log(">>> GET USER RATINGS START");
        
        try {
            $user = auth('sanctum')->user();
            
            // Use DB query with join instead of Eloquent with()
            $ratings = DB::table('reviews')
                ->leftJoin('courses', 'reviews.course_id', '=', 'courses.id')
                ->where('reviews.user_id', $user->id)
                ->orderBy('reviews.created_at', 'desc')
                ->select([
                    'reviews.*',
                    'courses.title as course_title'
                ])
                ->get()
                ->map(function ($review) {
                    return [
                        'course_id' => $review->course_id,
                        'course_title' => $review->course_title,
                        'rating' => $review->rating,
                        'rated_at' => $review->created_at,
                    ];
                });

            error_log(">>> GET USER RATINGS SUCCESS: " . $ratings->count() . " ratings");
            return response()->json([
                'total_ratings' => $ratings->count(),
                'ratings' => $ratings,
            ]);
        } catch (\Exception $e) {
            error_log('>>> GET USER RATINGS ERROR: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}