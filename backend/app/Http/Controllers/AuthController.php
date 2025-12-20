<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * 🔐 FORGOT PASSWORD - REQUEST RESET CODE
     * Flow: User enters email/phone → we generate reset code → send via same verification method
     */
    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'login' => 'required|string', // email or phone
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['login'])
            ->orWhere('phone', $validated['login'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        // Only allow password reset for verified users
        if (!$user->isVerified()) {
            return response()->json([
                'message' => 'Please verify your account first.',
            ], 403);
        }

        // Use the same verification mechanism (email / phone)
        $method = $user->verification_method ?? 'email';
        $codeSent = $this->verificationService->sendVerificationCode($user, $method);

        if (!$codeSent) {
            return response()->json([
                'message' => 'Failed to send reset code. Please try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'Reset code sent successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
            'method' => $method,
        ]);
    }

    /**
     * 🔐 VERIFY RESET CODE (optional step)
     * Confirms the reset code is valid before allowing password change
     */
    public function verifyResetCode(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        /** @var User|null $user */
        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->isValidVerificationCode($validated['code'])) {
            return response()->json([
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        return response()->json([
            'message' => 'Reset code is valid.',
        ]);
    }

    /**
     * 🔐 RESET PASSWORD
     * Flow: User enters code + new password → we validate & update password
     */
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:6|confirmed',
        ]);

        /** @var User|null $user */
        $user = User::find($validated['user_id']);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
            ], 404);
        }

        if (!$user->isValidVerificationCode($validated['code'])) {
            return response()->json([
                'message' => 'Invalid or expired reset code.',
            ], 400);
        }

        // Update password and clear the code
        $user->password = Hash::make($validated['password']);
        $user->verification_code = null;
        $user->verification_code_expires_at = null;
        $user->save();

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    /**
     * ✅ LOGIN WITH EMAIL OR PHONE
     * Flow: User enters email OR phone + password → We check verification → User gets token
     */
    public function apiLogin(Request $request)
    {
        // لا تسجّل كلمة السر في اللوج – فقط اسم الدخول و IP
        Log::info('Login attempt', [
            'login' => $request->input('login'),
            'ip' => $request->ip(),
        ]);
        
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

            // #region agent log
            try {
                $debugPayload = [
                    'sessionId' => 'debug-session',
                    'runId' => 'login-run',
                    'hypothesisId' => 'H_LOGIN_OK',
                    'location' => 'AuthController.php:apiLogin',
                    'message' => 'apiLogin success',
                    'data' => [
                        'user_id' => $user->id,
                        'ip' => $request->ip(),
                        'role' => $user->getRoleNames()->first(),
                        'token_present' => $token ? true : false,
                    ],
                    'timestamp' => (int) (microtime(true) * 1000),
                ];
                @file_put_contents(
                    base_path('../.cursor/debug.log'),
                    json_encode($debugPayload, JSON_UNESCAPED_UNICODE) . PHP_EOL,
                    FILE_APPEND
                );
            } catch (\Throwable $ignored) {
                // ignore debug logging errors
            }
            // #endregion

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
    public function apiLogout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    // ==================== WEB METHODS (for admin panel) ====================

    /**
     * Web Login - for admin panel
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        Log::info('Web login attempt', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Redirect based on user role
            if (Auth::user()->hasRole('admin')) {
                return redirect()->intended('/admin');
            }

            if (Auth::user()->hasRole('instructor')) {
                return redirect()->intended('/instructor/dashboard');
            }

            if (Auth::user()->hasRole('student')) {
                return redirect()->intended('/student/dashboard');
            }

            return redirect()->intended('/dashboard');
        }

        Log::warning('Web login failed', [
            'email' => $credentials['email'],
            'ip' => $request->ip(),
        ]);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Web Logout - for admin panel
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
