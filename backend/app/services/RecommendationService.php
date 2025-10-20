<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use App\Models\Enrollment;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RecommendationService
{
    /**
     * Get personalized recommendations for a user
     */
    public function getRecommendations(?User $user, int $limit = 10)
    {
        if (!$user) {
            return $this->getGuestRecommendations($limit);
        }

        $cacheKey = "recommendations:user:{$user->id}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user, $limit) {
            $recommendations = collect();
            
            // Get user's enrolled courses to exclude
            $enrolledCourseIds = $user->enrollments()
                ->whereNull('refunded_at')
                ->pluck('course_id')
                ->toArray();
            
            // 1. Category-based recommendations
            $categoryRecommendations = $this->getCategoryBasedRecommendations($user, $enrolledCourseIds);
            $recommendations = $recommendations->merge($categoryRecommendations);
            
            // 2. Similar instructor recommendations
            $instructorRecommendations = $this->getInstructorBasedRecommendations($user, $enrolledCourseIds);
            $recommendations = $recommendations->merge($instructorRecommendations);
            
            // 3. Level-based recommendations
            $levelRecommendations = $this->getLevelBasedRecommendations($user, $enrolledCourseIds);
            $recommendations = $recommendations->merge($levelRecommendations);
            
            // Remove duplicates and limit
            return $recommendations
                ->unique('id')
                ->take($limit)
                ->values();
        });
    }

    /**
     * Get recommendations for guest users
     */
    protected function getGuestRecommendations(int $limit)
    {
        return Cache::remember("recommendations:guest", 3600, function () use ($limit) {
            return Course::with(['instructor', 'category'])
                ->where('total_students', '>', 0)
                ->orderByRaw('
                    CASE 
                        WHEN rating >= 4.5 THEN 1
                        WHEN rating >= 4.0 THEN 2
                        ELSE 3
                    END,
                    total_students DESC
                ')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get most popular courses overall
     */
    public function getMostPopular(int $limit = 10)
    {
        return Cache::remember("recommendations:popular:{$limit}", 3600, function () use ($limit) {
            return Course::with(['instructor', 'category'])
                ->where('total_students', '>', 0)
                ->orderByRaw('total_students DESC, rating DESC NULLS LAST')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Get trending courses (most enrollments in last 7 days)
     */
    public function getTrending(int $limit = 10)
    {
        return Cache::remember("recommendations:trending:{$limit}", 1800, function () use ($limit) {
            $sevenDaysAgo = now()->subDays(7);
            
            // Use a completely different approach for PostgreSQL
            $trendingCourseIds = DB::table('enrollments')
                ->select('course_id')
                ->selectRaw('COUNT(*) as enrollment_count')
                ->where('created_at', '>=', $sevenDaysAgo)
                ->whereNull('refunded_at')
                ->groupBy('course_id')
                ->orderBy('enrollment_count', 'desc')
                ->limit($limit)
                ->pluck('course_id');

            if ($trendingCourseIds->isEmpty()) {
                // If no trending courses, return most popular instead
                return Course::with(['instructor', 'category'])
                    ->orderBy('total_students', 'desc')
                    ->limit($limit)
                    ->get();
            }

            // Get the courses with proper ordering
            return Course::with(['instructor', 'category'])
                ->whereIn('id', $trendingCourseIds)
                ->orderByRaw('ARRAY_POSITION(ARRAY[' . $trendingCourseIds->map(fn($id) => "'{$id}'")->implode(',') . '], id::text)')
                ->get();
        });
    }

   /**
 * Get best instructors based on ratings and course count
 */
public function getBestInstructors(int $limit = 5)
{
    return Cache::remember("recommendations:instructors:{$limit}", 3600, function () use ($limit) {
        // First get instructor IDs with their stats - WITHOUT using having clause
        $instructorStats = DB::table('courses')
            ->select('instructor_id')
            ->selectRaw('COUNT(*) as course_count')
            ->selectRaw('AVG(rating) as avg_rating')
            ->where('total_students', '>', 0)
            ->groupBy('instructor_id')
            ->orderByRaw('AVG(rating) DESC NULLS LAST')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->get();

        if ($instructorStats->isEmpty()) {
            return collect();
        }

        $instructorIds = $instructorStats->pluck('instructor_id');

        // Get the actual user models
        $instructors = User::role('instructor')
            ->with(['profile'])
            ->whereIn('id', $instructorIds)
            ->get()
            ->keyBy('id');

        // Map stats to users and maintain order
        return $instructorStats->map(function ($stat) use ($instructors) {
            $instructor = $instructors->get($stat->instructor_id);
            if ($instructor) {
                $instructor->courses_count = $stat->course_count;
                $instructor->courses_avg_rating = $stat->avg_rating;
                return $instructor;
            }
            return null;
        })->filter()->values();
    });
}

    /**
     * Get category-based recommendations
     */
    protected function getCategoryBasedRecommendations(User $user, array $excludeIds)
    {
        // Get user's preferred categories from enrollments
        $userCategories = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->where('enrollments.user_id', $user->id)
            ->whereNull('enrollments.refunded_at')
            ->whereNotNull('courses.category_id')
            ->groupBy('courses.category_id')
            ->pluck('courses.category_id')
            ->toArray();

        if (empty($userCategories)) {
            return collect();
        }

        $query = Course::with(['instructor', 'category'])
            ->whereIn('category_id', $userCategories)
            ->where('total_students', '>', 0)
            ->orderByRaw('rating DESC NULLS LAST, total_students DESC')
            ->limit(5);

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get();
    }

    /**
     * Get instructor-based recommendations
     */
    protected function getInstructorBasedRecommendations(User $user, array $excludeIds)
    {
        // Get instructors from user's enrolled courses
        $favoriteInstructors = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->where('enrollments.user_id', $user->id)
            ->whereNull('enrollments.refunded_at')
            ->groupBy('courses.instructor_id')
            ->pluck('courses.instructor_id')
            ->toArray();

        if (empty($favoriteInstructors)) {
            return collect();
        }

        $query = Course::with(['instructor', 'category'])
            ->whereIn('instructor_id', $favoriteInstructors)
            ->orderByRaw('rating DESC NULLS LAST, created_at DESC')
            ->limit(5);

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get();
    }

    /**
     * Get level-based recommendations
     */
    protected function getLevelBasedRecommendations(User $user, array $excludeIds)
    {
        // Get user's most common course level
        $userLevel = DB::table('enrollments')
            ->join('courses', 'enrollments.course_id', '=', 'courses.id')
            ->where('enrollments.user_id', $user->id)
            ->whereNull('enrollments.refunded_at')
            ->select('courses.level', DB::raw('COUNT(*) as count'))
            ->groupBy('courses.level')
            ->orderBy('count', 'desc')
            ->value('level');

        if (!$userLevel) {
            return collect();
        }

        $query = Course::with(['instructor', 'category'])
            ->where('level', $userLevel)
            ->where('total_students', '>', 0)
            ->orderByRaw('rating DESC NULLS LAST, total_students DESC')
            ->limit(5);

        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->get();
    }

    /**
     * Clear recommendation cache for a user
     */
    public function clearUserCache(?User $user): void
    {
        if ($user) {
            Cache::forget("recommendations:user:{$user->id}");
        }
    }

    /**
     * Clear all recommendation caches
     */
    public function clearAllCaches(): void
    {
        Cache::forget("recommendations:guest");
        Cache::forget("recommendations:popular:10");
        Cache::forget("recommendations:trending:10");
        Cache::forget("recommendations:instructors:5");
    }
}