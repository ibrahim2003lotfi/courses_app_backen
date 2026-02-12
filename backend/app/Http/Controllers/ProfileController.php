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
            // Get the authenticated user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $userId = $user->id;
            
            // 🔄 FORCE FRESH LOAD FROM DATABASE - don't use cached tokenable
            $freshUser = \App\Models\User::find($userId);
            if ($freshUser) {
                $user = $freshUser;
                Log::info('👤 Loaded fresh user from database');
            }
            
            Log::info('👤 Loading profile data for user: ' . $userId);

            // Get stored user data from file (simulating database)
            $userData = $this->getUserData($userId);
            $profileData = $this->getProfileData($userId);
            $certificatesData = $this->getCertificatesData($userId);
            
            // Update user data with actual user info from database
            $userData['id'] = $user->id;
            $userData['name'] = $user->name;
            $userData['email'] = $user->email;
            $userData['phone'] = $user->phone ?? null;
            $userData['age'] = $user->age ?? null;
            $userData['gender'] = $user->gender ?? null;
            $userData['is_verified'] = $user->is_verified ?? false;
            $userData['verification_method'] = $user->verification_method ?? null;
            
            // 🚨 FORCE READ ROLE DIRECTLY FROM DATABASE - bypass all caching
            $roleFromRawDb = \DB::table('users')->where('id', $userId)->value('role');
            
            // DEBUG: Log raw database values
            Log::info('👤 Raw database role check', [
                'user_id' => $userId,
                'role_from_raw_db_query' => $roleFromRawDb ?? 'NULL',
                'model_role_before_override' => $user->role ?? 'NULL',
            ]);
            
            // ALWAYS use the raw DB value
            $user->role = $roleFromRawDb;
            
            $spatieRole = null;
            if (method_exists($user, 'getRoleNames')) {
                $spatieRole = $user->getRoleNames()->first();
            }

            // Always trust database role first, then Spatie, then file cache
            $effectiveRole = $roleFromRawDb ?? $spatieRole ?? ($userData['role'] ?? 'student');
            
            Log::info('👤 Role calculation', [
                'role_from_db' => $roleFromRawDb ?? 'NULL',
                'spatieRole' => $spatieRole ?? 'NULL',
                'userData[role]' => $userData['role'] ?? 'NULL',
                'effectiveRole' => $effectiveRole,
            ]);
            
            $userData['role'] = $effectiveRole;
            $user->role = $effectiveRole; // Keep model in sync

            $userData['created_at'] = $user->created_at;
            
            // Add interests and onboarding data from database
            $userData['interests'] = $user->interests ?? [];
            $userData['onboarding_status'] = $user->onboarding_status ?? 'student';
            $userData['onboarding_completed_at'] = $user->onboarding_completed_at;

            $this->saveUserData($userData, $userId);
            
            Log::info('👤 Returning current user data: ' . json_encode($userData));
            Log::info('👤 Returning current profile data: ' . json_encode($profileData));
            Log::info('👤 Returning current certificates data: ' . json_encode($certificatesData));
            
            return response()->json([
                'message' => 'Profile data loaded successfully!',
                'user' => $userData,
                'profile' => $profileData,
                'certificates' => $certificatesData,
                'stats' => [
                    'enrolled' => 0,
                    'completed' => 0,
                    'certificates' => count($certificatesData),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('👤👤 PROFILE CONTROLLER ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error loading profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user basic info and profile bio.
     */
    public function update(Request $request)
    {
        Log::info('👤👤 UPDATE PROFILE - Starting update method');
        
        try {
            // Get the authenticated user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $userId = $user->id;
            Log::info('👤 Processing profile update request for user: ' . $userId);
            
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email',
                'phone' => 'sometimes|string|max:20',
                'bio' => 'sometimes|nullable|string|max:2000',
            ]);

            Log::info('👤 Validated data: ' . json_encode($validated));

            // Update the user in database
            if (isset($validated['name'])) $user->name = $validated['name'];
            if (isset($validated['email'])) $user->email = $validated['email'];
            if (isset($validated['phone'])) $user->phone = $validated['phone'];
            $user->save();

            // Update the stored user data
            $userData = $this->getUserData($userId);
            $profileData = $this->getProfileData($userId);
            
            if (isset($validated['name'])) $userData['name'] = $validated['name'];
            if (isset($validated['email'])) $userData['email'] = $validated['email'];
            if (isset($validated['phone'])) $userData['phone'] = $validated['phone'];
            if (isset($validated['bio'])) $profileData['bio'] = $validated['bio'];

            // Save updated data
            $this->saveUserData($userData, $userId);
            $this->saveProfileData($profileData, $userId);

            Log::info('👤 Updated user data: ' . json_encode($userData));

            // Return success with the updated data
            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $userData,
                'profile' => $profileData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('👤👤 UPDATE PROFILE ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error updating profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getUserData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/user_data_{$userId}.json");
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return $data ?: $this->getDefaultUserData();
        }
        return $this->getDefaultUserData();
    }

    private function getProfileData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/profile_data_{$userId}.json");
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return $data ?: $this->getDefaultProfileData();
        }
        return $this->getDefaultProfileData();
    }

    private function getCertificatesData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/certificates_data_{$userId}.json");
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return $data ?: $this->getDefaultCertificatesData();
        }
        return $this->getDefaultCertificatesData();
    }

    private function saveUserData($data, $userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/user_data_{$userId}.json");
        file_put_contents($file, json_encode($data));
    }

    private function saveProfileData($data, $userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/profile_data_{$userId}.json");
        file_put_contents($file, json_encode($data));
    }

    private function saveCertificatesData($data, $userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/certificates_data_{$userId}.json");
        file_put_contents($file, json_encode($data));
    }

    private function getDefaultUserData()
    {
        return [
            'id' => '6cf3bc66-e9b4-4ea1-9f17-0f72c6bbf55a',
            'name' => 'jvb bguy',
            'email' => 'yuhnb@gyu.com',
            'phone' => '328054269',
            'age' => 25,
            'gender' => 'male',
            'is_verified' => true,
            'verification_method' => 'email',
            'created_at' => '2026-02-03T18:19:43.000000Z',
        ];
    }

    private function getDefaultProfileData()
    {
        return [
            'bio' => null,
            'avatar_url' => null,
            'cover_url' => null,
        ];
    }

    private function getDefaultCertificatesData()
    {
        return [
            [
                'id' => 1,
                'title' => 'شهادة Flutter المتقدم',
                'date' => '2024',
                'color' => '#3B82F6',
                'icon' => 'code',
                'image_url' => null,
            ],
            [
                'id' => 2,
                'title' => 'شهادة UI/UX Design',
                'date' => '2024',
                'color' => '#8B5CF6',
                'icon' => 'design_services',
                'image_url' => null,
            ],
            [
                'id' => 3,
                'title' => 'شهادة التسويق الرقمي',
                'date' => '2023',
                'color' => '#F97316',
                'icon' => 'campaign',
                'image_url' => null,
            ],
        ];
    }

    /**
     * Upload avatar image
     */
    public function updateAvatar(Request $request)
    {
        Log::info('👤👤 UPLOAD AVATAR - Starting avatar upload method');
        
        try {
            // Get the authenticated user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $userId = $user->id;
            Log::info('👤 Processing avatar upload request for user: ' . $userId);
            
            $request->validate([
                'avatar' => 'required|image|max:2048', // 2MB max
            ]);

            Log::info('👤 Avatar validation passed');

            // Handle file upload
            if ($request->hasFile('avatar')) {
                $avatar = $request->file('avatar');
                $filename = 'avatar_' . time() . '.' . $avatar->getClientOriginalExtension();
                
                // Store the file in public storage
                $path = $avatar->storeAs('avatars', $filename, 'public');
                
                // Get the URL
                $avatarUrl = url('storage/' . $path);
                
                Log::info('👤 Avatar stored at: ' . $avatarUrl);

                // Update user data with avatar URL
                $userData = $this->getUserData($userId);
                $profileData = $this->getProfileData($userId);
                
                $profileData['avatar_url'] = $avatarUrl;
                
                // Save updated profile data
                $this->saveProfileData($profileData, $userId);
                
                Log::info('👤 Profile data updated with avatar URL');

                return response()->json([
                    'message' => 'Avatar uploaded successfully',
                    'profile' => [
                        'avatar_url' => $avatarUrl,
                    ],
                    'user' => $userData,
                    'avatar_url' => $avatarUrl,
                ]);
            }
            
            return response()->json([
                'message' => 'No avatar file provided',
                'error' => 'Avatar file is required'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('👤👤 UPLOAD AVATAR ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error uploading avatar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload cover image
     */
    public function updateCover(Request $request)
    {
        Log::info('👤👤 UPLOAD COVER - Starting cover upload method');
        
        try {
            // Get the authenticated user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $userId = $user->id;
            Log::info('👤 Processing cover upload request for user: ' . $userId);
            
            $request->validate([
                'cover' => 'required|image|max:2048', // 2MB max
            ]);

            Log::info('👤 Cover validation passed');

            // Handle file upload
            if ($request->hasFile('cover')) {
                $cover = $request->file('cover');
                $filename = 'cover_' . time() . '.' . $cover->getClientOriginalExtension();
                
                // Store the file in public storage
                $path = $cover->storeAs('covers', $filename, 'public');
                
                // Get the URL
                $coverUrl = url('storage/' . $path);
                
                Log::info('👤 Cover stored at: ' . $coverUrl);

                // Update user data with cover URL
                $userData = $this->getUserData($userId);
                $profileData = $this->getProfileData($userId);
                
                $profileData['cover_url'] = $coverUrl;
                
                // Save updated profile data
                $this->saveProfileData($profileData, $userId);
                
                Log::info('👤 Profile data updated with cover URL');

                return response()->json([
                    'message' => 'Cover uploaded successfully',
                    'profile' => [
                        'cover_url' => $coverUrl,
                    ],
                    'user' => $userData,
                    'cover_url' => $coverUrl,
                ]);
            }
            
            return response()->json([
                'message' => 'No cover file provided',
                'error' => 'Cover file is required'
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('👤👤 UPLOAD COVER ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error uploading cover',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user onboarding data - save learning state and interests to database
     */
    public function updateOnboarding(Request $request)
    {
        Log::info('🎯 ONBOARDING - Starting updateOnboarding method');
        
        try {
            // Get the authenticated user from token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['message' => 'No token provided'], 401);
            }

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if (!$accessToken) {
                return response()->json(['message' => 'Invalid token'], 401);
            }

            $user = $accessToken->tokenable;
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            Log::info('🎯 Processing onboarding update for user: ' . $user->id);
            
            $validated = $request->validate([
                'learning_state' => 'required|string|in:school_student,university_student,graduated,employed,job_seeker,learner',
                'interests' => 'required|array|min:1',
                'interests.*' => 'required|string|max:100',
            ]);

            Log::info('🎯 Validated onboarding data: ' . json_encode($validated));

            // Update the user in database - store onboarding data
            $user->onboarding_status = $validated['learning_state'];
            $user->interests = json_encode($validated['interests']);
            $user->onboarding_completed_at = now();
            $user->save();

            Log::info('🎯 Onboarding data saved to database for user: ' . $user->id);

            // Also update the stored user data (for consistency with other profile data)
            $userData = $this->getUserData($user->id);
            $profileData = $this->getProfileData($user->id);
            
            $userData['onboarding_status'] = $validated['learning_state'];
            $userData['interests'] = $validated['interests'];
            $userData['onboarding_completed_at'] = now()->toISOString();
            
            $this->saveUserData($userData, $user->id);

            Log::info('🎯 Onboarding data saved to storage for user: ' . $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Onboarding preferences saved successfully',
                'data' => [
                    'learning_state' => $validated['learning_state'],
                    'interests' => $validated['interests'],
                    'onboarding_completed' => true,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('🎯 ONBOARDING ERROR: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error saving onboarding preferences',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete user account and all associated data
     */
    public function destroy(Request $request)
    {
        Log::info('👤👤 DELETE ACCOUNT - Starting destroy method');
        
        try {
            // Manual token validation (bypassing Sanctum middleware)
            $token = $request->bearerToken();
            
            if (!$token) {
                Log::info('👤 No token provided for account deletion');
                return response()->json(['message' => 'No token provided'], 401);
            }

            Log::info('👤 Token provided for account deletion: ' . substr($token, 0, 20) . '...');

            // Find the token in the database
            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if (!$accessToken) {
                Log::info('👤 Token not found in database for account deletion');
                return response()->json(['message' => 'Invalid token'], 401);
            }

            Log::info('👤 Token found, deleting account for user: ' . $accessToken->tokenable_id);

            // Get the user from database
            $user = $accessToken->tokenable;
            if (!$user) {
                Log::info('👤 User not found for token');
                return response()->json(['message' => 'User not found'], 404);
            }

            Log::info('👤 Deleting account for user: ' . json_encode($user));

            // Delete user data files
            $this->deleteUserData($user->id);
            $this->deleteProfileData($user->id);
            $this->deleteCertificatesData($user->id);

            // Delete user from database (this will cascade delete tokens)
            $user->delete();

            Log::info('👤 Account and all associated data deleted successfully');

            return response()->json([
                'message' => 'Account deleted successfully',
                'user_deleted' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('👤👤 DELETE ACCOUNT ERROR: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Error deleting account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function deleteUserData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/user_data_{$userId}.json");
        if (file_exists($file)) {
            unlink($file);
            Log::info('👤 User data file deleted for user: ' . $userId);
        }
    }

    private function deleteProfileData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/profile_data_{$userId}.json");
        if (file_exists($file)) {
            unlink($file);
            Log::info('👤 Profile data file deleted for user: ' . $userId);
        }
    }

    private function deleteCertificatesData($userId = null)
    {
        $userId = $userId ?? 'default';
        $file = storage_path("app/certificates_data_{$userId}.json");
        if (file_exists($file)) {
            unlink($file);
            Log::info('👤 Certificates data file deleted for user: ' . $userId);
        }
    }
}


