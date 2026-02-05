<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OnboardingController extends Controller
{
    /**
     * Save user's current status (student, graduate, employee, etc.)
     */
    public function saveUserStatus(Request $request)
    {
        Log::info('🎯 ONBOARDING STATUS - Saving user status');
        
        $validated = $request->validate([
            'user_id' => 'required|string',
            'status' => 'required|string|in:student,graduate,employee,other',
            'university' => 'nullable|string|max:255',
            'major' => 'nullable|string|max:255',
            'graduation_year' => 'nullable|integer|min:1950|max:2030',
        ]);

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update user's onboarding data
        $user->onboarding_status = $validated['status'];
        $user->university = $validated['university'] ?? null;
        $user->major = $validated['major'] ?? null;
        $user->graduation_year = $validated['graduation_year'] ?? null;
        $user->onboarding_completed_at = now();
        $user->save();

        Log::info('🎯 Status saved for user: ' . $user->id . ' Status: ' . $validated['status']);

        return response()->json([
            'success' => true,
            'message' => 'Status saved successfully',
            'data' => [
                'status' => $validated['status'],
                'university' => $validated['university'],
                'major' => $validated['major'],
                'graduation_year' => $validated['graduation_year'],
            ]
        ]);
    }

    /**
     * Save user's interests
     */
    public function saveUserInterests(Request $request)
    {
        Log::info('🎯 ONBOARDING INTERESTS - Request received');
        Log::info('🎯 Request data: ' . json_encode($request->all()));
        
        try {
            $validated = $request->validate([
                'user_id' => 'required|string',
                'interests' => 'required|array|min:1',
                'interests.*' => 'required|string|max:100',
            ]);
            
            Log::info('🎯 Validated interests: ' . json_encode($validated['interests']));
        } catch (\Exception $e) {
            Log::error('🎯 Validation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage(),
            ], 422);
        }

        $user = User::find($validated['user_id']);
        if (!$user) {
            Log::info('🎯 User not found for ID: ' . $validated['user_id']);
            return response()->json(['message' => 'User not found'], 404);
        }

        try {
            // Store interests as JSON
            $user->interests = json_encode($validated['interests']);
            $user->interests_updated_at = now();
            $user->save();
            
            Log::info('🎯 Interests saved for user: ' . $user->id . ' Interests: ' . json_encode($validated['interests']));
            
            return response()->json([
                'success' => true,
                'message' => 'Interests saved successfully',
                'data' => [
                    'interests' => $validated['interests'],
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('🎯 Error saving interests: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save interests: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's complete onboarding profile
     */
    public function getUserProfile(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|string',
        ]);

        $user = User::find($validated['user_id']);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $interests = $user->interests ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $user->onboarding_status,
                'university' => $user->university,
                'major' => $user->major,
                'graduation_year' => $user->graduation_year,
                'interests' => $interests,
            ]
        ]);
    }
}
