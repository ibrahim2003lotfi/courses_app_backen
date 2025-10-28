<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class VerificationService
{
    /**
     * Send verification code via email
     */
    public function sendEmailVerificationCode(User $user, string $code): bool
    {
        try {
            Log::info("📧 Attempting to send verification email to: {$user->email}");
            
            // Send REAL email (this actually sends to the user's inbox)
            Mail::send('emails.verification', [
                'code' => $code,
                'user' => $user,
                'expires_in' => '15 minutes'
            ], function ($message) use ($user) {
                $message->to($user->email)  // Send TO the user's actual email
                        ->subject('Your Verification Code - ' . config('app.name', 'My Courses App'));
            });
            
            Log::info("✅ Email sent successfully to: {$user->email}");
            return true;
            
        } catch (\Exception $e) {
            Log::error('❌ Email sending failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send verification code via SMS (keep the log version for now)
     */
    public function sendSMSVerificationCode(User $user, string $code): bool
    {
        try {
            // For now, just log it (we'll set up SMS later)
            Log::info("📱 SMS verification code for {$user->phone}: {$code}");
            Log::info("🔔 Note: Real SMS service not configured yet - code is: {$code}");
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('SMS verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send verification code based on method
     */
    public function sendVerificationCode(User $user, string $method): bool
    {
        $code = $user->generateVerificationCode();
        
        Log::info("🔐 Sending verification via: {$method} for user: {$user->email}");
        
        return match($method) {
            'email' => $this->sendEmailVerificationCode($user, $code),
            'phone' => $this->sendSMSVerificationCode($user, $code),
            default => false,
        };
    }
}