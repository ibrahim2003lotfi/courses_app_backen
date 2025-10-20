<?php

namespace App\Payment\Providers;

use App\Payment\Contracts\PaymentProvider;
use App\Models\Order;

class SyrianManualPayment implements PaymentProvider
{
    public function createPayment($amount, $orderId, $metadata = [])
    {
        // Generate unique reference for manual payment
        $referenceId = 'SY-' . time() . '-' . rand(1000, 9999);
        
        return [
            'reference_id' => $referenceId,
            'status' => 'pending',
            'instructions' => $this->getPaymentInstructions($orderId),
            'type' => 'manual'
        ];
    }

    public function verifyPayment($paymentId)
    {
        // For manual payments, verification happens via admin
        return ['status' => 'requires_manual_verification'];
    }

    public function getPaymentInstructions($orderId)
    {
        $order = Order::with('course')->find($orderId);
        
        return [
            'amount' => number_format($order->amount, 0) . ' SYP',
            'reference_id' => 'COURSE-' . $order->id,
            'methods' => [
                'syriatel_cash' => [
                    'name' => 'Syriatel Cash',
                    'instructions' => 'Send to 0950XXXXX with reference: COURSE-' . $order->id
                ],
                'mtn_cash' => [
                    'name' => 'MTN Cash',
                    'instructions' => 'Send to 0930XXXXX with reference: COURSE-' . $order->id
                ],
                'bank_transfer' => [
                    'name' => 'Bank Transfer',
                    'instructions' => 'Transfer to account XXXX with reference: COURSE-' . $order->id
                ]
            ],
            'confirmation_instructions' => 'After payment, send receipt via WhatsApp or upload in the app'
        ];
    }

    public function supports($method)
    {
        return in_array($method, ['syriatel_cash', 'mtn_cash', 'bank_transfer', 'syrian_manual']);
    }
}