<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RefundController extends Controller
{
    private $refundPolicy = [
        'full_refund_within_hours' => 48, // 2 days
        'partial_refund_within_days' => 7,
        'no_refund_after_watched_percentage' => 50
    ];

    private $refundRules = [
        'never_watched' => 'auto_approve',
        'watched_under_25_percent' => 'review_required',
        'watched_over_50_percent' => 'no_refund'
    ];

    /**
     * Check refund eligibility for an order
     */
    public function checkEligibility($orderId)
    {
        $order = Order::with(['user', 'course', 'course.sections.lessons'])->findOrFail($orderId);
        
        $eligibility = $this->calculateEligibility($order);
        
        return response()->json([
            'order_id' => $order->id,
            'course_title' => $order->course->title,
            'purchased_at' => $order->created_at,
            'amount' => $order->amount,
            'eligibility' => $eligibility
        ]);
    }

    /**
     * Process refund request
     */
    public function refundOrder(Request $request, $orderId)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'admin_notes' => 'nullable|string|max:500'
        ]);

        $order = Order::with(['user', 'course'])->findOrFail($orderId);
        
        // Check eligibility
        $eligibility = $this->calculateEligibility($order);
        
        if (!$eligibility['can_refund']) {
            return response()->json([
                'message' => 'Refund not allowed',
                'reason' => $eligibility['reason']
            ], 400);
        }

        try {
            // Determine refund amount based on policy
            $refundAmount = $this->calculateRefundAmount($order, $eligibility);
            
            // Process refund based on approval type
            if ($eligibility['approval_type'] === 'auto_approve') {
                return $this->processAutoRefund($order, $request, $refundAmount);
            } else {
                return $this->processManualRefund($order, $request, $refundAmount);
            }

        } catch (\Exception $e) {
            Log::error("Refund failed for order {$orderId}: " . $e->getMessage());
            return response()->json([
                'message' => 'Refund processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate refund eligibility
     */
    private function calculateEligibility($order)
{
    $purchaseTime = Carbon::parse($order->created_at);
    $now = Carbon::now();
    
    $hoursSincePurchase = $purchaseTime->diffInHours($now);
    $daysSincePurchase = $purchaseTime->diffInDays($now);
    
    $progress = $this->getStudentProgress($order->user_id, $order->course_id);
    $watchedPercentage = $progress['watched_percentage'];
    
    // Apply refund rules:
    
    // 1. First check if no refund allowed
    if ($watchedPercentage >= $this->refundPolicy['no_refund_after_watched_percentage']) {
        return [
            'can_refund' => false,
            'reason' => 'Watched over ' . $this->refundPolicy['no_refund_after_watched_percentage'] . '% of course content',
            'approval_type' => 'no_refund',
            'watched_percentage' => $watchedPercentage
        ];
    }
    
    // 2. Check time-based auto-approve (2 days)
    if ($hoursSincePurchase <= $this->refundPolicy['full_refund_within_hours']) {
        return [
            'can_refund' => true,
            'reason' => 'Within 2-day full refund period',
            'approval_type' => 'auto_approve',
            'watched_percentage' => $watchedPercentage
        ];
    }
    
    // 3. FIXED: Unwatched content between 2-7 days = REVIEW REQUIRED
    if ($watchedPercentage == 0 && $daysSincePurchase <= $this->refundPolicy['partial_refund_within_days']) {
        return [
            'can_refund' => true,
            'reason' => 'Never watched any content - requires manual review',
            'approval_type' => 'review_required', // ← CHANGED from auto_approve
            'watched_percentage' => $watchedPercentage
        ];
    }
    
    // 4. Then check partial refund scenario (watched some content)
    if ($watchedPercentage < 25 && $daysSincePurchase <= $this->refundPolicy['partial_refund_within_days']) {
        return [
            'can_refund' => true,
            'reason' => 'Watched under 25% within refund period',
            'approval_type' => 'review_required',
            'watched_percentage' => $watchedPercentage
        ];
    }
    
    return [
        'can_refund' => false,
        'reason' => 'Outside refund policy period',
        'approval_type' => 'no_refund',
        'watched_percentage' => $watchedPercentage
    ];
}
    /**
     * Calculate refund amount based on policy
     */
    private function calculateRefundAmount($order, $eligibility)
{
    $hoursSincePurchase = Carbon::parse($order->created_at)->diffInHours(Carbon::now());
    $daysSincePurchase = Carbon::parse($order->created_at)->diffInDays(Carbon::now());
    
    // Full refund within 2 days
    if ($hoursSincePurchase <= $this->refundPolicy['full_refund_within_hours']) {
        return $order->amount;
    }
    
    // Partial refund (50%) for orders between 2-7 days
    if ($daysSincePurchase <= $this->refundPolicy['partial_refund_within_days']) {
        return $order->amount * 0.5; // 50% refund
    }
    
    return $order->amount; // Default (shouldn't reach here for eligible refunds)
}

    /**
     * Get student progress in course (we'll implement this properly next)
     */
    private function getStudentProgress($userId, $courseId)
    {
        // TODO: Implement proper progress tracking
        // For now, return mock data
        return [
            'watched_percentage' => 0, // Will implement real tracking
            'total_lessons' => 10,
            'watched_lessons' => 0
        ];
    }

    /**
     * Process auto-approve refund
     */
    /**
 * Process auto-approve refund
 */
private function processAutoRefund($order, $request, $refundAmount)
{
    $order->update([
        'status' => 'refunded',
        'refunded_at' => now(),
        'refund_reason' => $request->input('reason'),
        'refund_amount' => $refundAmount,
        'refund_type' => 'auto_approved'
    ]);

    // Update enrollment
    $enrollment = Enrollment::where('user_id', $order->user_id)
        ->where('course_id', $order->course_id)
        ->whereNull('refunded_at')
        ->first();

    if ($enrollment) {
        $enrollment->update(['refunded_at' => now()]);
    }

    // 🔧 ADD THIS CHECK: Prevent negative student count
    if ($order->course->total_students > 0) {
        $order->course->decrement('total_students');
    } else {
        Log::warning("Cannot decrement student count - already at zero for course: " . $order->course->id);
    }

    Log::info("Auto-refund processed: Order {$order->id}, Amount: {$refundAmount}");

    return response()->json([
        'message' => 'Refund processed automatically',
        'refund_amount' => $refundAmount,
        'refund_type' => 'auto_approved',
        'order' => $order->load('user', 'course')
    ]);
}

    /**
     * Process manual review refund
     */
    private function processManualRefund($order, $request, $refundAmount)
    {
        $order->update([
            'status' => 'refund_requested',
            'refund_reason' => $request->input('reason'),
            'requested_refund_amount' => $refundAmount,
            'admin_notes' => $request->input('admin_notes', 'Pending review')
        ]);

        Log::info("Refund requested for review: Order {$order->id}, Requested amount: {$refundAmount}");

        return response()->json([
            'message' => 'Refund request submitted for admin review',
            'requested_amount' => $refundAmount,
            'approval_type' => 'manual_review',
            'order' => $order->load('user', 'course')
        ]);
    }

    /**
     * Get refund history
     */
    public function refundHistory()
    {
        $refundedOrders = Order::with(['user', 'course'])
            ->where('status', 'refunded')
            ->orderBy('refunded_at', 'desc')
            ->get();

        $pendingRefunds = Order::with(['user', 'course'])
            ->where('status', 'refund_requested')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'refunded_orders' => $refundedOrders,
            'pending_refunds' => $pendingRefunds,
            'stats' => [
                'total_refunded' => $refundedOrders->count(),
                'total_refund_amount' => $refundedOrders->sum('refund_amount'),
                'pending_reviews' => $pendingRefunds->count()
            ]
        ]);
    }

    /**
 * Get pending refund requests
 */
public function pendingRefunds()
{
    $pendingRefunds = Order::with(['user', 'course'])
        ->where('status', 'refund_requested')
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'pending_refunds' => $pendingRefunds,
        'total_pending' => $pendingRefunds->count()
    ]);
}





/**
 * Approve a pending refund request
 */

//----------optional-----------//

/*
public function approveRefund(Request $request, $orderId)
{
    $request->validate([
        'approved_amount' => 'required|numeric|min:0',
        'admin_notes' => 'nullable|string|max:500'
    ]);

    $order = Order::with(['user', 'course'])->findOrFail($orderId);
    
    if ($order->status !== 'refund_requested') {
        return response()->json(['message' => 'Only pending refunds can be approved'], 400);
    }

    $order->update([
        'status' => 'refunded',
        'refunded_at' => now(),
        'refund_amount' => $request->approved_amount,
        'refund_type' => 'manual_approved',
        'admin_notes' => $request->input('admin_notes', 'Approved by admin')
    ]);

    // Update enrollment and student count
    $enrollment = Enrollment::where('user_id', $order->user_id)
        ->where('course_id', $order->course_id)
        ->whereNull('refunded_at')
        ->first();

    if ($enrollment) {
        $enrollment->update(['refunded_at' => now()]);
    }

    $order->course->decrement('total_students');

    Log::info("Manual refund approved: Order {$order->id}, Amount: {$request->approved_amount}");

    return response()->json([
        'message' => 'Refund approved successfully',
        'order' => $order->load('user', 'course')
    ]);
}


*/

}