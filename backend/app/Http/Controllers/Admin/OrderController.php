<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    public function index(Request $request)
    {
        $query = Order::with(['user', 'course.instructor']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Date range filter
        if ($request->has('from_date')) {
            $query->where('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('created_at', '<=', $request->input('to_date'));
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $orders = $query->paginate(20);

        // Calculate summary
        $summary = [
            'total_orders' => $query->count(),
            'total_revenue' => $query->where('status', 'succeeded')->sum('amount'),
            'pending_count' => $query->where('status', 'pending')->count(),
            'refund_requests' => $query->where('status', 'refund_requested')->count(),
        ];

        return response()->json([
            'orders' => $orders,
            'summary' => $summary,
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'user.profile',
            'course.instructor',
            'payments'
        ])->findOrFail($id);

        return response()->json($order);
    }

    public function processRefund(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        if (!in_array($order->status, ['succeeded', 'refund_requested'])) {
            return response()->json([
                'message' => 'Order cannot be refunded'
            ], 400);
        }

        DB::transaction(function () use ($order, $request) {
            // Update order
            $order->update([
                'status' => 'refunded',
                'refunded_at' => now(),
                'refund_amount' => $request->input('amount'),
                'refund_reason' => $request->input('reason'),
                'admin_notes' => $request->input('admin_notes'),
            ]);

            // Update enrollment
            $enrollment = \App\Models\Enrollment::where('user_id', $order->user_id)
                ->where('course_id', $order->course_id)
                ->first();

            if ($enrollment) {
                $enrollment->update(['refunded_at' => now()]);
            }

            // Decrement student count
            if ($order->course->total_students > 0) {
                $order->course->decrement('total_students');
            }

            // TODO: Process actual refund with payment provider
        });

        return response()->json([
            'message' => 'Refund processed successfully',
            'order' => $order->fresh(['user', 'course']),
        ]);
    }
}