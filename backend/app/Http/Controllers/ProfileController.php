<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Get current authenticated user's profile and basic stats.
     */
    public function me(Request $request)
    {
        try {
            Log::info('Profile me called', ['user_id' => optional($request->user())->id]);

            // #region agent log
            try {
                $debugPayload = [
                    'sessionId' => 'debug-session',
                    'runId' => 'profile-run',
                    'hypothesisId' => 'H_ME_CALLED',
                    'location' => 'ProfileController.php:me:entry',
                    'message' => 'Profile me called',
                    'data' => [
                        'user_id' => optional($request->user())->id,
                        'has_user' => $request->user() ? true : false,
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

            $user = $request->user()->load('profile');

            $stats = [
                'enrolled' => method_exists($user, 'enrollments')
                    ? $user->enrollments()->whereNull('refunded_at')->count()
                    : 0,
                // You can later replace these placeholders with real tracking logic
                'completed' => 0,
                'certificates' => 0,
            ];

            $response = [
                'user' => $user,
                'profile' => $user->profile,
                'stats' => $stats,
            ];

            // #region agent log
            try {
                $debugPayload = [
                    'sessionId' => 'debug-session',
                    'runId' => 'profile-run',
                    'hypothesisId' => 'H_ME_OK',
                    'location' => 'ProfileController.php:me:exit',
                    'message' => 'Profile me success',
                    'data' => [
                        'user_id' => $user->id ?? null,
                        'has_profile' => $user->profile ? true : false,
                        'stats' => $stats,
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

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Profile me error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // #region agent log
            try {
                $debugPayload = [
                    'sessionId' => 'debug-session',
                    'runId' => 'profile-run',
                    'hypothesisId' => 'H_ME_ERROR',
                    'location' => 'ProfileController.php:me:catch',
                    'message' => 'Profile me exception',
                    'data' => [
                        'error_message' => $e->getMessage(),
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

            return response()->json([
                'message' => 'خطأ غير متوقع أثناء تحميل الملف الشخصي',
            ], 500);
        }
    }

    /**
     * Update user basic info and profile bio.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'bio' => 'sometimes|nullable|string|max:2000',
        ]);

        // Update basic user fields
        $user->fill(collect($validated)->only(['name', 'email', 'phone'])->toArray());
        $user->save();

        // Ensure profile exists
        $profile = $user->profile ?: Profile::create(['user_id' => $user->id]);

        if (array_key_exists('bio', $validated)) {
            $profile->bio = $validated['bio'];
            $profile->save();
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh('profile'),
        ]);
    }

    /**
     * Save onboarding preferences: learning_state + interests.
     * We store them in the profile's social_links JSON field.
     */
    public function updateOnboarding(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'learning_state' => 'required|string|max:255',
            'interests' => 'required|array|min:1',
            'interests.*' => 'string|max:255',
        ]);

        $profile = $user->profile ?: Profile::create(['user_id' => $user->id]);

        $links = $profile->social_links ?? [];
        $links['learning_state'] = $validated['learning_state'];
        $links['interests'] = $validated['interests'];

        $profile->social_links = $links;
        $profile->save();

        return response()->json([
            'message' => 'Onboarding preferences saved successfully',
            'profile' => $profile,
        ]);
    }

    /**
     * Upload or change avatar image.
     */
    public function updateAvatar(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'avatar' => 'required|image|max:2048', // 2MB
            ]);

            $profile = $user->profile ?: Profile::create(['user_id' => $user->id]);

            // Delete old avatar if exists and stored locally
            if ($profile->avatar_url && Str::startsWith($profile->avatar_url, 'storage/')) {
                $oldPath = str_replace('storage/', '', $profile->avatar_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('avatar')->store('avatars', 'public');

            $profile->avatar_url = 'storage/' . $path;
            $profile->save();

            return response()->json([
                'message' => 'Avatar updated successfully',
                'profile' => $profile,
            ]);
        } catch (\Throwable $e) {
            Log::error('Profile avatar error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'فشل رفع الصورة، حاول مرة أخرى لاحقًا',
            ], 500);
        }
    }

    /**
     * Delete current user's account.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        // Revoke tokens first
        $user->tokens()->delete();

        // Delete user (and cascade to profile, enrollments, etc. via FKs)
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully',
        ]);
    }
}


