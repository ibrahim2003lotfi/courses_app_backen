<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\RecommendationService;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    protected $recommendationService;

    public function __construct(RecommendationService $recommendationService)
    {
        $this->recommendationService = $recommendationService;
    }

    /**
     * Get home page data
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get categories with course count - Fixed for PostgreSQL
        $categories = Category::select('categories.*')
            ->selectRaw('(SELECT COUNT(*) FROM courses WHERE courses.category_id = categories.id) as courses_count')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('courses')
                    ->whereColumn('courses.category_id', 'categories.id');
            })
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM courses WHERE courses.category_id = categories.id)'))
            ->get();

        // Get personalized recommendations
        $recommended = $this->recommendationService->getRecommendations($user, 10);

        // Get most popular courses
        $mostPopular = $this->recommendationService->getMostPopular(10);

        // Get trending courses
        $trending = $this->recommendationService->getTrending(10);

        // Get best instructors
        $bestInstructors = $this->recommendationService->getBestInstructors(5);

        // Get recent courses
        $recentCourses = Course::with(['instructor', 'category'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'categories' => $categories,
            'sections' => [
                [
                    'title' => $user ? 'Recommended for You' : 'Featured Courses',
                    'courses' => $recommended,
                    'type' => 'recommended'
                ],
                [
                    'title' => 'Most Popular',
                    'courses' => $mostPopular,
                    'type' => 'popular'
                ],
                [
                    'title' => 'Trending This Week',
                    'courses' => $trending,
                    'type' => 'trending'
                ],
                [
                    'title' => 'New Courses',
                    'courses' => $recentCourses,
                    'type' => 'recent'
                ]
            ],
            'best_instructors' => $bestInstructors,
            'is_authenticated' => $user !== null,
        ]);
    }

    /**
     * Get courses by category
     */
    public function categoryDetail(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        
        // Build query
        $query = Course::with(['instructor', 'category'])
            ->where('category_id', $category->id);

        // Apply filters
        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        if ($request->has('rating')) {
            $query->where('rating', '>=', $request->input('rating'));
        }

        // Apply sorting
        $sortBy = $request->input('sort', 'popular');
        switch ($sortBy) {
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('rating DESC NULLS LAST');
                break;
            case 'popular':
            default:
                $query->orderBy('total_students', 'desc');
                break;
        }

        $courses = $query->paginate($request->input('per_page', 12));

        // Get related categories - Fixed for PostgreSQL
        $relatedCategories = Category::select('categories.*')
            ->selectRaw('(SELECT COUNT(*) FROM courses WHERE courses.category_id = categories.id) as courses_count')
            ->where('id', '!=', $category->id)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('courses')
                    ->whereColumn('courses.category_id', 'categories.id');
            })
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM courses WHERE courses.category_id = categories.id)'))
            ->limit(5)
            ->get();

        return response()->json([
            'category' => $category,
            'courses' => $courses,
            'related_categories' => $relatedCategories,
            'available_filters' => [
                'levels' => ['beginner', 'intermediate', 'advanced'],
                'sort_options' => [
                    ['value' => 'popular', 'label' => 'Most Popular'],
                    ['value' => 'newest', 'label' => 'Newest'],
                    ['value' => 'rating', 'label' => 'Highest Rated'],
                    ['value' => 'price_low', 'label' => 'Price: Low to High'],
                    ['value' => 'price_high', 'label' => 'Price: High to Low'],
                ]
            ]
        ]);
    }
}