<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Course;
use App\Models\Order;
use App\Models\InstructorApplication;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Get statistics
        $stats = [
            'total_users' => User::count(),
            'total_students' => User::role('student')->count(),
            'total_instructors' => User::role('instructor')->count(),
            'total_courses' => Course::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('status', 'succeeded')->sum('amount'),
            'pending_applications' => InstructorApplication::where('status', 'pending')->count(),
            'pending_refunds' => Order::where('status', 'refund_requested')->count(),
        ];

        // Get recent activity
        $recentOrders = Order::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $recentApplications = InstructorApplication::with('user')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Revenue by month (last 6 months)
        $revenueByMonth = Order::where('status', 'succeeded')
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_TRUNC(\'month\', created_at) as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as order_count')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_orders' => $recentOrders,
            'recent_applications' => $recentApplications,
            'revenue_by_month' => $revenueByMonth,
        ]);
    }
}