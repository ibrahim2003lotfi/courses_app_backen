<?php

namespace App\Payment\Contracts;

interface PaymentProvider
{
    public function createPayment($amount, $orderId, $metadata = []);
    public function verifyPayment($paymentId);
    public function getPaymentInstructions($order);
    public function supports($method);
}