<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Get current authenticated user's profile and basic stats.
     */
    public function me(Request $request)
    {
        Log::info('👤👤 CLEAN PROFILE CONTROLLER - Starting me method');
        
        try {
            Log::info('👤 Skipping auth check for now - returning mock user data');
            
            // For now, return mock data until we fix the auth issue
            return response()->json([
                'message' => 'Profile data loaded successfully!',
                'user' => [
                    'id' => '6cf3bc66-e9b4-4ea1-9f17-0f72c6bbf55a',
                    'name' => 'jvb bguy',
                    'email' => 'yuhnb@gyu.com',
                    'phone' => '328054269',
                    'age' => 25,
                    'gender' => 'male',
                    'is_verified' => true,
                    'verification_method' => 'email',
                    'created_at' => '2026-02-03T18:19:43.000000Z',
                ],
                'profile' => null,
                'stats' => [
                    'enrolled' => 0,
                    'completed' => 0,
                    'certificates' => 0,
                ],
                'debug' => 'Mock user data - auth issue needs fixing'
            ]);
            
        } catch (\Exception $e) {
            Log::error('👤👤 PROFILE CONTROLLER ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error loading profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}


