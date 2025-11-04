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
        $user = auth('sanctum')->user();
        $course = Course::findOrFail($courseId);

        // Check if user has purchased the course
        if (!$course->canUserRate($user->id)) {
            return response()->json([
                'message' => 'You must purchase this course before rating it.'
            ], 403);
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        DB::transaction(function () use ($user, $course, $validated) {
            // Update or create rating
            Review::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ],
                [
                    'rating' => $validated['rating'],
                ]
            );

            // Course rating is automatically updated via model events
        });

        // Reload course with updated rating
        $course->refresh();

        return response()->json([
            'message' => 'Rating submitted successfully',
            'rating' => $validated['rating'],
            'course_rating' => $course->getRatingInfo(),
        ]);
    }

    /**
     * Get user's rating for a course
     */
    public function show($courseId)
    {
        $user = auth('sanctum')->user();
        $course = Course::findOrFail($courseId);

        $review = $course->getUserRating($user->id);

        return response()->json([
            'user_rating' => $review ? $review->rating : null,
            'can_rate' => $course->canUserRate($user->id),
        ]);
    }

    /**
     * Delete user's rating
     */
    public function destroy($courseId)
    {
        $user = auth('sanctum')->user();
        $course = Course::findOrFail($courseId);

        $review = $course->getUserRating($user->id);

        if (!$review) {
            return response()->json([
                'message' => 'No rating found to delete.'
            ], 404);
        }

        $review->delete();

        $course->refresh();

        return response()->json([
            'message' => 'Rating deleted successfully',
            'course_rating' => $course->getRatingInfo(),
        ]);
    }

    /**
     * Get course rating statistics
     */
    public function getCourseRating($courseId)
    {
        $course = Course::findOrFail($courseId);

        return response()->json([
            'rating_info' => $course->getRatingInfo(),
        ]);
    }

    /**
     * Get user's course ratings (all courses rated by user)
     */
    public function getUserRatings()
    {
        $user = auth('sanctum')->user();
        
        $ratings = Review::with('course')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($review) {
                return [
                    'course_id' => $review->course_id,
                    'course_title' => $review->course->title,
                    'rating' => $review->rating,
                    'rated_at' => $review->created_at,
                ];
            });

        return response()->json([
            'total_ratings' => $ratings->count(),
            'ratings' => $ratings,
        ]);
    }
}