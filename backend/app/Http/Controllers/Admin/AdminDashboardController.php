<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\Order;
use App\Models\InstructorApplication;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
{
    // Your existing stats code...
    $stats = [
        'totalUsers' => User::count(),
        'totalCourses' => Course::count(),
        'totalRevenue' => Order::where('status', 'succeeded')->sum('amount'),
        'pendingApplications' => InstructorApplication::where('status', 'pending')->count(),
    ];

    $recentOrders = Order::with(['user', 'course'])
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    // Fixed PostgreSQL-compatible query
    $revenueByMonth = Order::where('status', 'succeeded')
        ->where('created_at', '>=', now()->subMonths(12))
        ->select(
            DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
            DB::raw('SUM(amount) as revenue')
        )
        ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
        ->orderBy('month')
        ->get();

    $topCourses = Course::withCount('enrollments')
        ->orderBy('enrollments_count', 'desc')
        ->limit(5)
        ->get();

    return Inertia::render('Admin/Dashboard', [
        'stats' => $stats,
        'recentOrders' => $recentOrders,
        'revenueByMonth' => $revenueByMonth,
        'topCourses' => $topCourses,
    ]);
}
}