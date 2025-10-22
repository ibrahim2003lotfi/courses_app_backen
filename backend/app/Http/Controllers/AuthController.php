<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|string|in:student,instructor,admin',
        ]);

        // Create the user
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
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
     * Login user and create token
     */
    public function login(Request $request)
{
    // Add this at the very beginning
    \Log::info('Login attempt', $request->all());
    
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

        return response()->json([
            'message' => 'Login successful',
            'user' => $user,
            'role' => $user->getRoleNames(),
            'token' => $token,
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Login error', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

    /**
     * Logout user (revoke tokens)
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
