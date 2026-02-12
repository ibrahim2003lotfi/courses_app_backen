<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Course;
use App\Models\Category;

class HomeController extends Controller
{
    /**
     * Get home page data
     */
    public function index(Request $request)
    {
        try {
            $user = null;
            $isAuthenticated = false;

            $token = $request->bearerToken();
            if ($token) {
                $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($accessToken && $accessToken->tokenable) {
                    $user = $accessToken->tokenable;
                    $isAuthenticated = true;
                }
            }

            // Cache categories for 10 minutes (rarely change)
            $categories = \Cache::remember('home_categories', 600, function () {
                return Category::query()
                    ->select(['id', 'name', 'slug'])
                    ->orderBy('name')
                    ->get();
            });

            $recommendedCourses = collect();
            if ($user) {
                $interests = is_array($user->interests) ? $user->interests : [];

                if (!empty($interests)) {
                    $interestNameMap = [
                        'programming' => ['برمجة', 'تطوير', 'Programming', 'Development'],
                        'design' => ['تصميم', 'جرافيك', 'Design', 'Graphic'],
                        'marketing' => ['تسويق', 'Marketing'],
                        'languages' => ['لغات', 'Languages', 'Language'],
                        'business' => ['أعمال', 'إدارة', 'Business', 'Management'],
                        'science' => ['علوم', 'تكنولوجيا', 'Science', 'Technology'],
                        'arts' => ['فنون', 'إبداع', 'Arts', 'Creative'],
                        'health' => ['صحة', 'لياقة', 'Health', 'Fitness'],
                    ];

                    $categoryIdsQuery = Category::query()->whereIn('slug', $interests);

                    foreach ($interests as $interest) {
                        $keywords = $interestNameMap[$interest] ?? [$interest];
                        $categoryIdsQuery->orWhere(function ($q) use ($keywords) {
                            foreach ($keywords as $kw) {
                                $q->orWhere('name', 'like', '%' . $kw . '%');
                            }
                        });
                    }

                    $categoryIds = $categoryIdsQuery->pluck('id');

                    if ($categoryIds->isNotEmpty()) {
                        $recommendedCourses = Course::query()
                            ->with(['instructor:id,name', 'category:id,name'])
                            ->whereIn('category_id', $categoryIds)
                            ->orderByRaw('rating IS NULL')
                            ->orderByDesc('rating')
                            ->orderByDesc('total_students')
                            ->limit(10)
                            ->get();
                    }
                }
            }

            // "Trending" - cache for 5 minutes
            $trendingCourses = \Cache::remember('home_trending_courses', 300, function () {
                return Course::query()
                    ->with(['instructor:id,name', 'category:id,name'])
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();
            });

            $sections = [
                [
                    'type' => 'recommended',
                    'title' => 'مقترح لك',
                    'courses' => $recommendedCourses->map(function (Course $course) {
                        return [
                            'id' => $course->id,
                            'title' => $course->title,
                            'rating' => $course->rating,
                            'total_students' => $course->total_students,
                            'course_image_url' => $course->course_image_url,
                            'instructor' => $course->instructor ? [
                                'id' => $course->instructor->id,
                                'name' => $course->instructor->name,
                            ] : null,
                            'category' => $course->category ? [
                                'id' => $course->category->id,
                                'name' => $course->category->name,
                            ] : null,
                            'category_id' => $course->category_id,
                        ];
                    })->values(),
                ],
                [
                    'type' => 'trending',
                    'title' => 'الأكثر شيوعًا',
                    'courses' => $trendingCourses->map(function (Course $course) {
                        return [
                            'id' => $course->id,
                            'title' => $course->title,
                            'rating' => $course->rating,
                            'total_students' => $course->total_students,
                            'course_image_url' => $course->course_image_url,
                            'instructor' => $course->instructor ? [
                                'id' => $course->instructor->id,
                                'name' => $course->instructor->name,
                            ] : null,
                            'category' => $course->category ? [
                                'id' => $course->category->id,
                                'name' => $course->category->name,
                            ] : null,
                            'category_id' => $course->category_id,
                        ];
                    })->values(),
                ],
            ];

            return response()->json([
                'message' => 'Home data loaded',
                'categories' => $categories,
                'sections' => $sections,
                'best_instructors' => [],
                'is_authenticated' => $isAuthenticated,
                'timestamp' => now()->toISOString(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('HomeController ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error in HomeController',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}