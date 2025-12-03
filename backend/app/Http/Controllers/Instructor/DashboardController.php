<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Basic instructor stats
        $totalCourses = Course::where('instructor_id', $user->id)->count();

        $totalStudents = Enrollment::whereHas('course', function ($q) use ($user) {
            $q->where('instructor_id', $user->id);
        })->distinct('user_id')->count('user_id');

        $recentCourses = Course::where('instructor_id', $user->id)
            ->withCount('enrollments')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Instructor/Dashboard', [
            'stats' => [
                'totalCourses' => $totalCourses,
                'totalStudents' => $totalStudents,
            ],
            'recentCourses' => $recentCourses,
        ]);
    }
}


