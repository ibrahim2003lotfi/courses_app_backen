<?php

namespace App\Services;

use App\Payment\Providers\SyrianManualPayment;
use App\Models\Order;
use App\Models\Enrollment;

class PaymentService
{
    protected $providers = [];

    public function __construct()
    {
        $this->providers = [
            'syrian_manual' => new SyrianManualPayment(),
            // Add more providers later: 'stripe', 'syriatel_api', 'mtn_api'
        ];
    }

    public function createPayment($provider, $amount, $orderId, $metadata = [])
    {
        if (!isset($this->providers[$provider])) {
            throw new \Exception("Payment provider not supported");
        }

        return $this->providers[$provider]->createPayment($amount, $orderId, $metadata);
    }

    public function verifyPayment($provider, $paymentId)
    {
        return $this->providers[$provider]->verifyPayment($paymentId);
    }

    public function confirmManualPayment($orderId)
{
    $order = Order::findOrFail($orderId);
    
    // Update order status to 'succeeded' (valid status)
    $order->update(['status' => 'succeeded']);
    
    // Check if enrollment already exists (idempotent)
    $enrollmentExists = Enrollment::where('user_id', $order->user_id)
        ->where('course_id', $order->course_id)
        ->whereNull('refunded_at')
        ->exists();

    if (!$enrollmentExists) {
        // Create enrollment
        Enrollment::create([
            'user_id' => $order->user_id,
            'course_id' => $order->course_id,
            'purchased_at' => now(),
        ]);

        // Update course student count
        $order->course->increment('total_students');
    }

    return $order;
}
}