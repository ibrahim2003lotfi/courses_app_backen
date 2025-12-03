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
        // High-level stats
        $stats = [
            'totalUsers' => User::count(),
            'totalCourses' => Course::count(),
            'totalRevenue' => Order::where('status', 'succeeded')->sum('amount'),
            'pendingApplications' => InstructorApplication::where('status', 'pending')->count(),
        ];

        // Latest orders
        $recentOrders = Order::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // A few most recent pending instructor applications for quick actions on the dashboard
        $recentApplications = InstructorApplication::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Revenue by month (PostgreSQL compatible)
        $revenueByMonth = Order::where('status', 'succeeded')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        // Top selling courses
        $topCourses = Course::withCount('enrollments')
            ->orderBy('enrollments_count', 'desc')
            ->limit(5)
            ->get();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'revenueByMonth' => $revenueByMonth,
            'topCourses' => $topCourses,
            'recentApplications' => $recentApplications,
        ]);
    }
}