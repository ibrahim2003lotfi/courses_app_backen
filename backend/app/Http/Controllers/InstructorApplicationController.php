<?php

namespace App\Http\Controllers;

use App\Models\InstructorApplication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class InstructorApplicationController extends Controller
{
    /**
     * Submit instructor application - handles both JSON and multipart/form-data
     */
    public function apply(Request $request)
    {
        try {
            Log::info('Instructor application received', [
                'content_type' => $request->header('Content-Type'),
                'has_files' => $request->hasFile('certificates'),
            ]);

            // Authenticate user
            $user = $this->authenticateUser($request);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // Validate
            $validated = $request->validate([
                'education_level' => 'required|string|max:255',
                'department' => 'required|string|max:500',
                'years_of_experience' => 'required|integer|min:0|max:50',
                'experience_description' => 'required|string|min:40|max:2000',
                'linkedin_url' => 'nullable|url|max:500',
                'portfolio_url' => 'nullable|url|max:500',
                'certificates' => 'nullable|array|max:5',
                'certificates.*' => 'file|mimes:pdf,jpg,jpeg,png|max:15360',
            ]);

            // Handle certificate uploads
            $certificates = [];
            if ($request->hasFile('certificates')) {
                foreach ($request->file('certificates') as $file) {
                    if ($file->isValid()) {
                        $path = $file->store('instructor-certificates/' . $user->id, 'public');
                        $certificates[] = [
                            'id' => (string) Str::uuid(),
                            'name' => $file->getClientOriginalName(),
                            'path' => $path,
                            'size' => $file->getSize(),
                            'mime_type' => $file->getMimeType(),
                            'uploaded_at' => now()->toISOString(),
                        ];
                    }
                }
            }

            // Find or create application
            $application = InstructorApplication::where('user_id', $user->id)->first();
            
            Log::info('Instructor application check', [
                'user_id' => $user->id,
                'existing_application' => $application ? $application->id : null,
            ]);
            
            if ($application) {
                // Delete old certificates
                if ($application->certificates) {
                    foreach ($application->certificates as $cert) {
                        if (isset($cert['path'])) {
                            Storage::disk('public')->delete($cert['path']);
                        }
                    }
                }
                
                $application->update([
                    'education_level' => $validated['education_level'],
                    'department' => $validated['department'],
                    'years_of_experience' => $validated['years_of_experience'],
                    'experience_description' => $validated['experience_description'],
                    'linkedin_url' => $validated['linkedin_url'] ?? null,
                    'portfolio_url' => $validated['portfolio_url'] ?? null,
                    'certificates' => $certificates,
                    'agreed_to_terms' => true,
                    'terms_agreed_at' => now(),
                    'status' => 'approved',
                    'reviewed_at' => now(),
                ]);
                Log::info('Existing instructor application updated', ['application_id' => $application->id]);
            } else {
                $application = InstructorApplication::create([
                    'user_id' => $user->id,
                    'education_level' => $validated['education_level'],
                    'department' => $validated['department'],
                    'years_of_experience' => $validated['years_of_experience'],
                    'experience_description' => $validated['experience_description'],
                    'linkedin_url' => $validated['linkedin_url'] ?? null,
                    'portfolio_url' => $validated['portfolio_url'] ?? null,
                    'certificates' => $certificates,
                    'agreed_to_terms' => true,
                    'terms_agreed_at' => now(),
                    'status' => 'approved',
                    'reviewed_at' => now(),
                ]);
                Log::info('New instructor application created', ['application_id' => $application->id]);
            }

            // Update user role
            $this->updateUserRole($user);
            
            // Clear file cache so profile will reload from database
            $this->clearUserFileCache($user->id);
            Log::info('User file cache cleared after role change', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'تم التحويل إلى مدرس بنجاح!',
                'status' => 'instructor',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Instructor application failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's application status
     */
    public function myApplication(Request $request)
    {
        try {
            $user = $this->authenticateUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $application = InstructorApplication::where('user_id', $user->id)->first();

            if (!$application) {
                return response()->json([
                    'success' => true,
                    'has_application' => false,
                    'message' => 'No application found',
                ]);
            }

            $certificates = [];
            if ($application->certificates) {
                foreach ($application->certificates as $cert) {
                    $certificates[] = [
                        'id' => $cert['id'] ?? null,
                        'name' => $cert['name'] ?? 'document',
                        'url' => isset($cert['path']) ? Storage::disk('public')->url($cert['path']) : null,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'has_application' => true,
                'application' => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'education_level' => $application->education_level,
                    'department' => $application->department,
                    'certificates' => $certificates,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error'], 500);
        }
    }

    /**
     * Cancel application
     */
    public function cancel(Request $request)
    {
        try {
            $user = $this->authenticateUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $application = InstructorApplication::where('user_id', $user->id)
                ->where('status', 'pending')
                ->first();

            if (!$application) {
                return response()->json(['success' => false, 'message' => 'No pending application'], 404);
            }

            if ($application->certificates) {
                foreach ($application->certificates as $cert) {
                    if (isset($cert['path'])) {
                        Storage::disk('public')->delete($cert['path']);
                    }
                }
            }

            $application->delete();

            return response()->json(['success' => true, 'message' => 'Application canceled']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error'], 500);
        }
    }

    /**
     * Authenticate user from bearer token
     */
    private function authenticateUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $accessToken = PersonalAccessToken::findToken($token);
        if (!$accessToken) return null;

        $user = $accessToken->tokenable;
        return ($user instanceof User) ? $user : null;
    }

    /**
     * Update user role to instructor
     */
    private function updateUserRole(User $user): void
    {
        Log::info('Starting role update for user: ' . $user->id);
        
        // ALWAYS use direct DB update to ensure it persists
        try {
            $updated = \DB::table('users')
                ->where('id', $user->id)
                ->update(['role' => 'instructor', 'updated_at' => now()]);
            
            if ($updated) {
                Log::info('Role updated via DIRECT DB for user: ' . $user->id);
                
                // Verify by reading back from DB
                $roleFromDb = \DB::table('users')->where('id', $user->id)->value('role');
                Log::info('Verified role in database: ' . ($roleFromDb ?? 'null'));
                
                // Update the user model in memory too
                $user->role = 'instructor';
            } else {
                Log::error('Direct DB update FAILED for user: ' . $user->id);
            }
        } catch (\Exception $e) {
            Log::error('Direct DB update error: ' . $e->getMessage());
        }

        // Also try Spatie role assignment
        try {
            if (method_exists($user, 'assignRole')) {
                $user->assignRole('instructor');
                Log::info('Spatie role assigned for user: ' . $user->id);
            }
        } catch (\Exception $e) {
            Log::warning('Spatie role assignment failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear user file cache so profile reloads from database
     */
    private function clearUserFileCache($userId): void
    {
        $files = [
            storage_path("app/user_data_{$userId}.json"),
            storage_path("app/profile_data_{$userId}.json"),
            storage_path("app/certificates_data_{$userId}.json"),
        ];
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
                Log::info('Deleted cache file: ' . $file);
            }
        }
    }
}
