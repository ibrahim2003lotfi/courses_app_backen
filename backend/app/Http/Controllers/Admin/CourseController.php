<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use App\Models\Order;


class CourseController extends Controller
{
    public function index(Request $request)
    {
        $query = Course::with(['instructor', 'category']);

        // Search
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                  ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->has('status')) {
            // Add status filtering logic if you have a status column
        }

        // Filter by instructor
        if ($request->has('instructor_id')) {
            $query->where('instructor_id', $request->input('instructor_id'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $courses = $query->paginate(20);

        return response()->json($courses);
    }

    public function show($id)
    {
        $course = Course::with([
            'instructor',
            'category',
            'sections.lessons',
            'enrollments.user',
            'reviews.user'
        ])->findOrFail($id);

        // Get course statistics
        $stats = [
            'total_enrollments' => $course->enrollments()->count(),
            'active_students' => $course->enrollments()->whereNull('refunded_at')->count(),
            'total_revenue' => Order::where('course_id', $course->id)
                ->where('status', 'succeeded')
                ->sum('amount'),
            'average_rating' => $course->reviews()->avg('rating'),
            'total_reviews' => $course->reviews()->count(),
        ];

        return response()->json([
            'course' => $course,
            'stats' => $stats,
        ]);
    }

    public function toggleStatus($id)
    {
        $course = Course::findOrFail($id);
        
        // Toggle course visibility (you'll need to add 'is_published' column)
        // For now, we'll use deleted_at for soft delete
        if ($course->trashed()) {
            $course->restore();
            $message = 'Course published successfully';
        } else {
            $course->delete();
            $message = 'Course unpublished successfully';
        }

        return response()->json(['message' => $message]);
    }
}