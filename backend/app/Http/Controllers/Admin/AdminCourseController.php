<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Category;
use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminCourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['instructor', 'category']);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('instructor', function($iq) use ($search) {
                      $iq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter by status (published/unpublished)
        if ($request->has('status')) {
            if ($request->input('status') === 'published') {
                $query->whereNull('deleted_at');
            } else {
                $query->onlyTrashed();
            }
        }

        // Filter by level
        if ($request->has('level')) {
            $query->where('level', $request->input('level'));
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }

        // Filter by instructor
        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->input('instructor_id'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        
        if ($sortBy === 'revenue') {
            $query->withSum(['orders' => function($q) {
                $q->where('status', 'succeeded');
            }], 'amount')->orderBy('orders_sum_amount', $sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $courses = $query->withCount(['enrollments', 'sections', 'reviews'])
            ->paginate(15)
            ->withQueryString();

        // Get categories for filter
        $categories = Category::all();

        // Get statistics
        // Get statistics
// Get statistics
// Get statistics - use columns that actually exist
$stats = [
    'total_courses' => Course::count(),
    'published' => Course::count(), // All courses are considered published
    'unpublished' => 0, // No unpublished courses
    'total_revenue' => Order::where('status', 'succeeded')->sum('amount'),
];

        return Inertia::render('Admin/Courses/Index', [
            'courses' => $courses,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $request->only(['search', 'category_id', 'status', 'level', 'instructor_id', 'min_price', 'max_price']),
        ]);
    }

    public function show($id)
    {
        $course = Course::withTrashed()
            ->with([
                'instructor.profile',
                'category',
                'sections.lessons',
                'enrollments.user',
                'reviews.user'
            ])
            ->withCount(['enrollments', 'sections', 'reviews'])
            ->findOrFail($id);

        // Get course statistics
        $stats = [
            'total_enrollments' => $course->enrollments()->count(),
            'active_students' => $course->enrollments()->whereNull('refunded_at')->count(),
            'total_revenue' => Order::where('course_id', $course->id)
                ->where('status', 'succeeded')
                ->sum('amount'),
            'refunded_amount' => Order::where('course_id', $course->id)
                ->where('status', 'refunded')
                ->sum('refund_amount'),
            'average_rating' => $course->reviews()->avg('rating'),
            'total_reviews' => $course->reviews()->count(),
            'completion_rate' => $this->calculateCompletionRate($course->id),
            'total_watch_time' => $this->calculateTotalWatchTime($course->id),
        ];

        // Revenue by month
        $revenueByMonth = Order::where('course_id', $course->id)
            ->where('status', 'succeeded')
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as sales')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Recent enrollments
        $recentEnrollments = $course->enrollments()
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        // Top reviews
        $topReviews = $course->reviews()
            ->with('user')
            ->orderBy('rating', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Courses/Show', [
            'course' => $course,
            'stats' => $stats,
            'revenueByMonth' => $revenueByMonth,
            'recentEnrollments' => $recentEnrollments,
            'topReviews' => $topReviews,
        ]);
    }

    public function edit($id)
    {
        $course = Course::withTrashed()
            ->with(['sections.lessons', 'category'])
            ->findOrFail($id);

        $categories = Category::all();

        return Inertia::render('Admin/Courses/Edit', [
            'course' => $course,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, $id)
    {
        $course = Course::withTrashed()->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric|min:0',
            'level' => 'sometimes|in:beginner,intermediate,advanced',
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'is_featured' => 'sometimes|boolean',
        ]);

        // Update slug if title changed
        if (isset($validated['title']) && $validated['title'] !== $course->title) {
            $validated['slug'] = \Str::slug($validated['title']);
            
            // Ensure unique slug
            $count = 1;
            while (Course::where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = \Str::slug($validated['title']) . '-' . $count;
                $count++;
            }
        }

        $course->update($validated);

        return redirect()->route('admin.courses.show', $course->id)
            ->with('success', 'Course updated successfully');
    }

    public function toggleStatus($id)
    {
        $course = Course::withTrashed()->findOrFail($id);
        
        if ($course->trashed()) {
            $course->restore();
            $message = 'Course published successfully';
            $status = 'published';
        } else {
            $course->delete();
            $message = 'Course unpublished successfully';
            $status = 'unpublished';
        }

        return redirect()->back()
            ->with('success', $message)
            ->with('courseStatus', $status);
    }

    public function destroy($id)
    {
        $course = Course::withTrashed()->findOrFail($id);

        // Check if course has active enrollments
        if ($course->enrollments()->whereNull('refunded_at')->exists()) {
            return back()->with('error', 'Cannot delete course with active enrollments');
        }

        // Permanently delete
        $course->forceDelete();

        return redirect()->route('admin.courses.index')
            ->with('success', 'Course permanently deleted');
    }

    public function bulkAction(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:publish,unpublish,delete',
            'course_ids' => 'required|array',
            'course_ids.*' => 'exists:courses,id',
        ]);

        $courses = Course::withTrashed()->whereIn('id', $validated['course_ids'])->get();

        foreach ($courses as $course) {
            switch ($validated['action']) {
                case 'publish':
                    $course->restore();
                    break;
                case 'unpublish':
                    $course->delete();
                    break;
                case 'delete':
                    if (!$course->enrollments()->whereNull('refunded_at')->exists()) {
                        $course->forceDelete();
                    }
                    break;
            }
        }

        return redirect()->back()
            ->with('success', 'Bulk action completed successfully');
    }

    public function statistics()
    {
        $stats = [
            'by_category' => Course::select('category_id', DB::raw('count(*) as count'))
                ->with('category:id,name')
                ->groupBy('category_id')
                ->get(),
            
            'by_level' => Course::select('level', DB::raw('count(*) as count'))
                ->groupBy('level')
                ->get(),
            
            'by_price_range' => [
                'free' => Course::where('price', 0)->count(),
                'under_50' => Course::whereBetween('price', [0.01, 50])->count(),
                'under_100' => Course::whereBetween('price', [50.01, 100])->count(),
                'over_100' => Course::where('price', '>', 100)->count(),
            ],
            
            'top_instructors' => Course::select('instructor_id', DB::raw('count(*) as course_count'))
                ->with('instructor:id,name')
                ->groupBy('instructor_id')
                ->orderBy('course_count', 'desc')
                ->limit(10)
                ->get(),
            
            'revenue_by_course' => Course::withSum(['orders' => function($q) {
                    $q->where('status', 'succeeded');
                }], 'amount')
                ->orderBy('orders_sum_amount', 'desc')
                ->limit(10)
                ->get(['id', 'title', 'orders_sum_amount']),
        ];

        return response()->json($stats);
    }

    public function reviews($courseId)
    {
        $course = Course::findOrFail($courseId);
        
        $reviews = Review::where('course_id', $courseId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Admin/Courses/Reviews', [
            'course' => $course,
            'reviews' => $reviews,
        ]);
    }

    public function deleteReview($courseId, $reviewId)
    {
        $review = Review::where('course_id', $courseId)->findOrFail($reviewId);
        $review->delete();

        // Recalculate course rating
        $this->recalculateCourseRating($courseId);

        return redirect()->back()
            ->with('success', 'Review deleted successfully');
    }

    private function calculateCompletionRate($courseId)
    {
        // TODO: Implement based on your progress tracking system
        // For now, return a placeholder
        return 65.5; // percentage
    }

    private function calculateTotalWatchTime($courseId)
    {
        // TODO: Implement based on your video tracking system
        // For now, return a placeholder
        return 1250; // minutes
    }

    private function recalculateCourseRating($courseId)
    {
        $course = Course::findOrFail($courseId);
        $averageRating = Review::where('course_id', $courseId)->avg('rating');
        $course->update(['rating' => $averageRating ?: null]);
    }
}