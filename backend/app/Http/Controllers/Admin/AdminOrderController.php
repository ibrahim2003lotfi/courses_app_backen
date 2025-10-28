<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user', 'course']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function show($id)
    {
        $order = Order::with(['user', 'course', 'payments'])
            ->findOrFail($id);

        return Inertia::render('Admin/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function refund(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($id);

        $order->update([
            'status' => 'refunded',
            'refunded_at' => now(),
            'refund_amount' => $request->amount,
            'refund_reason' => $request->reason,
        ]);

        // Update enrollment
        Enrollment::where('user_id', $order->user_id)
            ->where('course_id', $order->course_id)
            ->update(['refunded_at' => now()]);

        if ($order->course->total_students > 0) {
            $order->course->decrement('total_students');
        }

        return redirect()->back()->with('success', 'Refund processed successfully');
    }
}