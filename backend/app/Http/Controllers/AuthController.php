<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    // ==================== API METHODS (for mobile app) ====================

    /**
     * Register a new user (API)
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
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'age' => $validated['age'],
            'gender' => $validated['gender'],
            'phone' => $validated['phone'],
        ]);

        // Assign role
        $user->assignRole($validated['role']);

        // Create token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'role' => $user->getRoleNames(),
            'token' => $token,
        ], 201);
    }

    /**
     * API Login - for mobile app
     */
    public function apiLogin(Request $request)
    {
        \Log::info('API Login attempt', $request->all());

        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            \Log::info('Validation passed', ['email' => $validated['email']]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user) {
                \Log::warning('User not found', ['email' => $validated['email']]);
                throw ValidationException::withMessages([
                    'email' => ['User not found.'],
                ]);
            }

            \Log::info('User found', ['id' => $user->id]);

            if (!Hash::check($validated['password'], $user->password)) {
                \Log::warning('Password mismatch');
                throw ValidationException::withMessages([
                    'email' => ['The provided credentials are incorrect.'],
                ]);
            }

            \Log::info('Password verified');

            // Delete old tokens (optional)
            $user->tokens()->delete();

            $token = $user->createToken('auth_token')->plainTextToken;

            \Log::info('Token created successfully');

            // Load roles for response
            $user->load('roles');

            return response()->json([
                'message' => 'Login successful',
                'user' => $user,
                'role' => $user->getRoleNames(),
                'token' => $token,
            ]);

        } catch (\Exception $e) {
            \Log::error('API Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * API Logout - revoke tokens
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
