<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Course;
use App\Models\Order;
use App\Models\Enrollment;
use App\Services\PaymentService;

class PaymentController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Initiate payment for a course
     */
    public function initiatePayment(Request $request, $courseSlug)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Find course by slug
            $course = Course::where('slug', $courseSlug)->first();
            
            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $paymentMethod = $request->input('payment_method', 'syrian_manual');

            // Check if already enrolled
            $alreadyEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereNull('refunded_at')
                ->exists();

            if ($alreadyEnrolled) {
                return response()->json([
                    'message' => 'You are already enrolled in this course'
                ], 400);
            }

            // Create order
            $order = Order::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'provider' => $paymentMethod,
                'amount' => $course->price,
                'status' => 'pending',
            ]);

            // Create payment
            $paymentResult = $this->paymentService->createPayment(
                $paymentMethod,
                $course->price,
                $order->id,
                ['course_title' => $course->title]
            );

            // Update order with payment reference
            $order->update([
                'provider_payment_id' => $paymentResult['reference_id'] ?? null
            ]);

            return response()->json([
                'order_id' => $order->id,
                'payment_instructions' => $paymentResult['instructions'],
                'reference_id' => $paymentResult['reference_id'],
                'status' => 'pending'
            ]);

        } catch (\Exception $e) {
            Log::error('Payment initiation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

/**
 * Confirm manual payment (user submits receipt) - WITH AUTO APPROVAL
 */
public function confirmPayment(Request $request)
{
    try {
        $request->validate([
            'order_id' => 'required|uuid|exists:orders,id',
            'receipt_image' => 'nullable|image|max:2048',
            'confirmation_method' => 'required|in:whatsapp,upload,admin',
        ]);

        $order = Order::findOrFail($request->order_id);
        
        // Verify order belongs to user
        if ($order->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Handle receipt image upload
        if ($request->hasFile('receipt_image')) {
            $path = $request->file('receipt_image')->store('payment-receipts', 'public');
            $order->update(['receipt_image' => $path]);
        }

        // 🚀 AUTO APPROVAL: Immediately verify payment and enroll student
        $verifiedOrder = app(PaymentService::class)->confirmManualPayment($order->id);
        
        return response()->json([
            'message' => 'Payment confirmed! You now have access to the course.',
            'order_status' => 'succeeded',
            'order_id' => $order->id,
            'enrolled' => true
        ]);

    } catch (\Exception $e) {
        Log::error('Payment confirmation failed: ' . $e->getMessage());
        return response()->json([
            'message' => 'Payment confirmation failed',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Get payment status
     */
    public function getPaymentStatus($orderId)
    {
        try {
            $order = Order::with('course')->findOrFail($orderId);
            
            // Verify order belongs to user
            if ($order->user_id !== Auth::id()) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }

            return response()->json([
                'order_id' => $order->id,
                'status' => $order->status,
                'course_title' => $order->course->title,
                'amount' => $order->amount,
                'created_at' => $order->created_at
            ]);

        } catch (\Exception $e) {
            Log::error('Payment status check failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Payment status check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's payment history
     */
    public function paymentHistory()
    {
        try {
            $user = Auth::user();
            $orders = Order::with('course')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'orders' => $orders,
                'total_orders' => $orders->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Payment history failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}