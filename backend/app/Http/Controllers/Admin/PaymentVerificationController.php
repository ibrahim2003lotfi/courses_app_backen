<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Services\PaymentService;

class PaymentVerificationController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        
        // OPTION 1: Remove middleware from constructor completely
        // Rely on route middleware defined in routes/api.php
    }

    /**
     * List pending payments for verification
     */
    public function pendingPayments()
    {
        // Manual admin check since middleware might not be working in controller
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $pendingPayments = Order::with(['user', 'course'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'pending_payments' => $pendingPayments,
            'total_pending' => $pendingPayments->count()
        ]);
    }

    /**
     * Verify and confirm a payment
     */
    public function verifyPayment(Request $request, $orderId)
    {
        // Manual admin check
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        $order = Order::with(['user', 'course'])->findOrFail($orderId);

        try {
            $verifiedOrder = $this->paymentService->confirmManualPayment($orderId);
            
            // Simple logging
            Log::info("Payment verified for order {$orderId} by admin: " . $user->id);

            return response()->json([
                'message' => 'Payment verified and student enrolled successfully',
                'order' => $verifiedOrder->load('user', 'course')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a payment
     */
    public function rejectPayment(Request $request, $orderId)
    {
        // Manual admin check
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        $order = Order::findOrFail($orderId);
        $order->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason')
        ]);

        // Simple logging
        Log::info("Payment rejected for order {$orderId} by admin: " . $user->id . " - Reason: " . $request->input('reason'));

        return response()->json([
            'message' => 'Payment rejected successfully',
            'order' => $order->load('user', 'course')
        ]);
    }

    /**
     * Get payment statistics
     */
    public function paymentStats()
    {
        // Manual admin check
        $user = Auth::user();
        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized - Admin access required'], 403);
        }

        $stats = [
            'pending_verification' => Order::where('status', 'pending')->count(),
            'succeeded' => Order::where('status', 'succeeded')->count(),
            'rejected' => Order::where('status', 'rejected')->count(),
            'total_revenue' => Order::where('status', 'succeeded')->sum('amount'),
            'today_payments' => Order::where('status', 'succeeded')
                                ->whereDate('created_at', today())
                                ->count()
        ];

        return response()->json($stats);
    }
}