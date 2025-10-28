<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $verificationService;

    // ✅ INJECT VERIFICATION SERVICE
    public function __construct(VerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * ✅ REGISTER NEW USER
     * Flow: User registers → We send verification code → User verifies → Gets token
     */


    
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:student,instructor,admin',
            'age' => 'required|integer|min:1|max:120',
            'gender' => 'required|string|in:male,female,other',
            'phone' => 'required|string|max:20|unique:users',
            'verification_method' => 'required|string|in:email,phone', // ✅ NEW: User chooses method
        ]);

        // ✅ CREATE USER (NOT VERIFIED YET)
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'phone' => $validated['phone'],
            'verification_method' => $validated['verification_method'],
            'is_verified' => false, // ✅ User starts as unverified
        ]);

        // ✅ ASSIGN ROLE
        $user->assignRole($validated['role']);

        // ✅ SEND VERIFICATION CODE VIA CHOSEN METHOD
        $codeSent = $this->verificationService->sendVerificationCode($user, $validated['verification_method']);

        if (!$codeSent) {
            return response()->json([
                'message' => 'Failed to send verification code. Please try again.',
            ], 500);
        }

        // ✅ RESPONSE TO FLUTTER APP
        return response()->json([
            'message' => 'Registration successful. Please verify your account with the code we sent.',
            'user_id' => $user->id,
            'verification_method' => $validated['verification_method'],
            'needs_verification' => true, // ✅ Tell Flutter app to show verification screen
        ], 201);
    }
    

    
    /**
     * ✅ VERIFY USER ACCOUNT WITH CODE
     * Flow: User enters 6-digit code → We verify → User gets access token
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string', // The user ID from registration response
            'verification_code' => 'required|string|size:6', // 6-digit code
        ]);

        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        // ✅ CHECK IF ALREADY VERIFIED
        if ($user->isVerified()) {
            return response()->json([
                'message' => 'User already verified',
            ], 400);
        }

        // ✅ CHECK IF CODE IS VALID
        if (!$user->isValidVerificationCode($validated['verification_code'])) {
            return response()->json([
                'message' => 'Invalid or expired verification code',
            ], 400);
        }

        // ✅ MARK USER AS VERIFIED
        $user->markAsVerified();

        // ✅ CREATE AUTH TOKEN (User can now access the app)
        $token = $user->createToken('auth_token')->plainTextToken;

        // ✅ RESPONSE TO FLUTTER APP
        return response()->json([
            'message' => 'Account verified successfully!',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'is_verified' => true,
            ],
            'role' => $user->getRoleNames()->first(),
            'token' => $token, // ✅ This token allows access to protected routes
        ]);
    }

    /**
     * ✅ RESEND VERIFICATION CODE
     * Flow: User requests new code → We send new code → User verifies
     */
    public function resendVerification(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
        ]);

        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        if ($user->isVerified()) {
            return response()->json([
                'message' => 'User already verified',
            ], 400);
        }

        // ✅ SEND NEW VERIFICATION CODE
        $codeSent = $this->verificationService->sendVerificationCode($user, $user->verification_method);

        if (!$codeSent) {
            return response()->json([
                'message' => 'Failed to send verification code. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Verification code sent successfully',
        ]);
    }

    /**
     * ✅ LOGIN WITH EMAIL OR PHONE
     * Flow: User enters email OR phone + password → We check verification → User gets token
     */
    public function login(Request $request)
    {
        Log::info('Login attempt', $request->all());
        
        try {
            $validated = $request->validate([
                'login' => 'required|string', // ✅ Can be email OR phone number
                'password' => 'required',
            ]);

            // ✅ FIND USER BY EMAIL OR PHONE
            $user = User::where('email', $validated['login'])
                        ->orWhere('phone', $validated['login'])
                        ->first();

            if (!$user) {
                throw ValidationException::withMessages([
                    'login' => ['User not found.'],
                ]);
            }

            // ✅ CHECK PASSWORD
            if (!Hash::check($validated['password'], $user->password)) {
                throw ValidationException::withMessages([
                    'login' => ['The provided credentials are incorrect.'],
                ]);
            }

            // ✅ CHECK IF USER IS VERIFIED
            if (!$user->isVerified()) {
                return response()->json([
                    'message' => 'Please verify your account first.',
                    'needs_verification' => true,
                    'user_id' => $user->id,
                    'verification_method' => $user->verification_method,
                ], 403);
            }

            // ✅ DELETE OLD TOKENS (Optional security measure)
            $user->tokens()->delete();

            // ✅ CREATE NEW AUTH TOKEN
            $token = $user->createToken('auth_token')->plainTextToken;

            // ✅ RESPONSE TO FLUTTER APP
            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'is_verified' => true,
                ],
                'role' => $user->getRoleNames()->first(),
                'token' => $token,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ LOGOUT USER
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}