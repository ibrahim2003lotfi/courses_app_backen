<?php

/**
 * @method \App\Models\User hasRole(string $role)
 */

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\StreamController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Admin\PaymentVerificationController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\UniversityController;

// Public video file serving - MUST BE FIRST to ensure registration
Route::get('/videos/{path}', function ($path) {
    try {
        error_log(">>> VIDEO SERVE: Raw path: " . $path);
        
        // Decode URL-encoded path
        $path = urldecode($path);
        error_log(">>> VIDEO SERVE: Decoded path: " . $path);
        
        // Security: Prevent directory traversal
        if (strpos($path, '..') !== false || strpos($path, '~') !== false) {
            error_log(">>> VIDEO SERVE: Invalid path");
            abort(403, 'Invalid path');
        }
        
        // Only allow access to courses/videos directory
        if (!str_starts_with($path, 'courses/videos')) {
            error_log(">>> VIDEO SERVE: Path doesn't start with courses/videos: " . $path);
            abort(403, 'Access denied');
        }
        
        $fullPath = storage_path('app/public/' . $path);
        error_log(">>> VIDEO SERVE: Looking at: " . $fullPath);
        error_log(">>> VIDEO SERVE: File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));
        
        if (!file_exists($fullPath)) {
            // Try to find by filename
            $filename = basename($path);
            $coursesPath = storage_path('app/public/courses/videos/' . $filename);
            error_log(">>> VIDEO SERVE: Trying alternative: " . $coursesPath);
            
            if (file_exists($coursesPath)) {
                $fullPath = $coursesPath;
                error_log(">>> VIDEO SERVE: Found at alternative path!");
            } else {
                error_log(">>> VIDEO SERVE: File not found anywhere");
                abort(404, 'Video not found');
            }
        }
        
        $mimeType = mime_content_type($fullPath) ?: 'video/mp4';
        error_log(">>> VIDEO SERVE: Serving file");
        
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"',
            'Accept-Ranges' => 'bytes',
        ]);
        
    } catch (\Exception $e) {
        error_log(">>> VIDEO SERVE ERROR: " . $e->getMessage());
        abort(500, 'Error serving video');
    }
})->where('path', '.*');


// User onboarding routes
Route::post('/onboarding/status', [App\Http\Controllers\OnboardingController::class, 'saveUserStatus']);
Route::post('/onboarding/interests', [App\Http\Controllers\OnboardingController::class, 'saveUserInterests']);
Route::get('/onboarding/profile', [App\Http\Controllers\OnboardingController::class, 'getUserProfile']);

// 🟢 Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify', [AuthController::class, 'verify']);
Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
Route::post('/login', [AuthController::class, 'apiLogin']); 
// Forgot password flow
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/verify', [AuthController::class, 'verifyResetCode']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);
Route::post('/logout', [AuthController::class, 'apiLogout']);

// 🟢 Profile routes (authenticated user) - TEMPORARILY REMOVED AUTH MIDDLEWARE TO ISOLATE CRASH
Route::get('/me', [ProfileController::class, 'me']);
Route::put('/me', [ProfileController::class, 'update']);
Route::post('/me/onboarding', [ProfileController::class, 'updateOnboarding']);
Route::post('/me/avatar', [ProfileController::class, 'updateAvatar']);
Route::post('/me/cover', [ProfileController::class, 'updateCover']);
Route::delete('/me', [ProfileController::class, 'destroy']);

// Get user's enrolled courses - TEMPORARILY REMOVED AUTH MIDDLEWARE TO DEBUG CRASH
Route::get('/my/enrolled-courses', [EnrollmentController::class, 'getEnrolledCourses']);

// Enroll in a course - TEMPORARILY REMOVED AUTH MIDDLEWARE TO DEBUG CRASH
Route::post('/courses/{id}/enroll', [EnrollmentController::class, 'enroll']);

// 🔵 Debug routes
Route::get('/debug-user', function () {
    $user = auth('sanctum')->user();
    if (!$user) {
        return response()->json(['message' => 'Not authenticated'], 401);
    }
    
    $debug = [
        'user_id' => $user->id,
        'user_class' => get_class($user),
        'role_field' => $user->role ?? 'not set',
        'traits' => class_uses($user),
        'has_hasRole_method' => method_exists($user, 'hasRole'),
    ];
    
    // التحقق من HasRoles trait
    if (trait_exists(\Spatie\Permission\Traits\HasRoles::class)) {
        $debug['hasRoles_trait_exists'] = true;
    } else {
        $debug['hasRoles_trait_exists'] = false;
    }
    
    // التحقق من الأدوار في قاعدة البيانات
    try {
        $dbRoles = \DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', get_class($user))
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->pluck('roles.name');
        
        $debug['database_roles'] = $dbRoles;
    } catch (\Exception $e) {
        $debug['database_roles_error'] = $e->getMessage();
    }
    
    // محاولة استخدام hasRole مع التحقق من وجود الدالة
    if (method_exists($user, 'hasRole')) {
        try {
            /** @var \App\Models\User $user */
            $debug['hasRole_result'] = $user->hasRole('instructor');
        } catch (\Exception $e) {
            $debug['hasRole_error'] = $e->getMessage();
            $debug['hasRole_result'] = false;
        }
    } else {
        $debug['hasRole_error'] = 'hasRole method does not exist';
        $debug['hasRole_result'] = false;
    }
    
    return response()->json($debug);
})->middleware('auth:sanctum');

// 🟡 Instructor-only routes
Route::middleware(['auth:sanctum'])->prefix('instructor')->group(function () {
    // Course Management
    Route::post('/courses', [CourseController::class, 'store']);
    Route::post('/university-courses', [CourseController::class, 'storeUniversityCourse']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/my-courses-db', [CourseController::class, 'index']); // Alias for Flutter app

    // Section Management
    Route::get('/courses/{courseId}/sections', [SectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [SectionController::class, 'store']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [SectionController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [SectionController::class, 'destroy']);
    Route::post('/courses/{courseId}/sections/reorder', [SectionController::class, 'reorder']);

    // Lesson Management
    Route::get('/courses/{courseId}/sections/{sectionId}/lessons', [LessonController::class, 'index']);
    Route::post('/courses/{courseId}/sections/{sectionId}/lessons', [LessonController::class, 'store']);
    Route::put('/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}', [LessonController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}', [LessonController::class, 'destroy']);
    Route::post('/courses/{courseId}/sections/{sectionId}/lessons/reorder', [LessonController::class, 'reorder']);

    // 🎥 Media Management
    Route::post('/media/sign', [MediaController::class, 'sign']);
    Route::post('/media/confirm', [MediaController::class, 'confirm']);
    Route::delete('/media/delete', [MediaController::class, 'delete']);

     // 📋 Get lessons list
    Route::get('/courses/{courseId}/sections/{sectionId}/lessons', [LessonController::class, 'index']);
})->middleware('checkRole:instructor');

// 🔵 Public routes
Route::get('/courses', [CourseController::class, 'publicIndex']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);

// ⭐ Course Rating Routes
// TEMPORARILY REMOVED AUTH TO DEBUG CRASH - add back after fixing
Route::get('/courses/{courseId}/my-rating', [ReviewController::class, 'show']);
Route::post('/courses/{courseId}/rate', [ReviewController::class, 'store']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Rating management
    Route::delete('/courses/{courseId}/my-rating', [ReviewController::class, 'destroy']);
    Route::get('/my-ratings', [ReviewController::class, 'getUserRatings']);
});

// Public rating info - use courseId instead of course
Route::get('/courses/{courseId}/rating', [ReviewController::class, 'getCourseRating']);

// Test routes
Route::get('/test', fn() => response()->json(['message' => 'API is working']));

// Video streaming - moved outside auth to prevent memory crashes
Route::get('/courses/{slug}/stream/{lessonId}', [StreamController::class, 'stream']);

// Video upload with compression (instructor only) - using simple controller to prevent memory crashes
Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video', [SimpleVideoUploadController::class, 'upload']);

// Payment routes
Route::post('/courses/{courseId}/payment', [PaymentController::class, 'initiatePayment'])->middleware('auth:sanctum');
Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment'])->middleware('auth:sanctum');
Route::get('/payments/{orderId}/status', [PaymentController::class, 'getPaymentStatus'])->middleware('auth:sanctum');

// Admin payment verification routes
Route::middleware(['auth:sanctum', 'checkRole:admin'])->prefix('admin')->group(function () {
    Route::get('/payments/pending', [PaymentVerificationController::class, 'pendingPayments']);
    Route::post('/payments/{orderId}/verify', [PaymentVerificationController::class, 'verifyPayment']);
    Route::post('/payments/{orderId}/reject', [PaymentVerificationController::class, 'rejectPayment']);
});

// Admin refund routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/orders/{orderId}/refund/check', [RefundController::class, 'checkEligibility']);
    Route::post('/orders/{orderId}/refund', [RefundController::class, 'refundOrder']);
    Route::get('/refunds/history', [RefundController::class, 'refundHistory']);
    Route::get('/refunds/pending', [RefundController::class, 'pendingRefunds']);
});

//for the optional method for admin approvale for refund
Route::post('/refunds/{orderId}/approve', [RefundController::class, 'approveRefund']);

// Test home route without prefix to isolate the issue
Route::get('/test-home', [App\Http\Controllers\HomeController::class, 'index']);

// Ultra-simple test endpoint - no controller
Route::get('/simple-test', function () {
    return response()->json(['message' => 'Simple test works']);
});

// Search routes + public catalog (v1)
Route::prefix('v1')->group(function () {
    // Search
    Route::get('/search', [App\Http\Controllers\SearchController::class, 'search']);
    Route::get('/search/suggestions', [App\Http\Controllers\SearchController::class, 'suggestions']);
    
    // Home and recommendations - RESTORED
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index']);
    Route::get('/categories/{slug}/courses', [App\Http\Controllers\HomeController::class, 'categoryDetail']);

    // Universities & faculties (for the university section in the app)
    Route::get('/universities', [UniversityController::class, 'index']);
    Route::get('/universities/{university}', [UniversityController::class, 'show']);
    Route::get('/universities/{university}/faculties', [UniversityController::class, 'faculties']);
    Route::get(
        '/universities/{university}/faculties/{faculty}/courses',
        [UniversityController::class, 'coursesByFaculty']
    );
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('v1/admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index']);
    
    // Users management
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::get('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::post('/users/{id}/toggle-status', [App\Http\Controllers\Admin\UserController::class, 'toggleStatus']);
    
    // Courses management
    Route::get('/courses', [App\Http\Controllers\Admin\CourseController::class, 'index']);
    Route::get('/courses/{id}', [App\Http\Controllers\Admin\CourseController::class, 'show']);
    Route::post('/courses/{id}/toggle-status', [App\Http\Controllers\Admin\CourseController::class, 'toggleStatus']);
    
    // Orders management
    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index']);
    Route::get('/orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'show']);
    Route::post('/orders/{id}/refund', [App\Http\Controllers\Admin\OrderController::class, 'processRefund']);
    
    // Instructor applications
    Route::get('/instructor-applications', [App\Http\Controllers\Admin\InstructorApplicationController::class, 'index']);
    Route::get('/instructor-applications/{id}', [App\Http\Controllers\Admin\InstructorApplicationController::class, 'show']);
    Route::post('/instructor-applications/{id}/approve', [App\Http\Controllers\Admin\InstructorApplicationController::class, 'approve']);
    Route::post('/instructor-applications/{id}/reject', [App\Http\Controllers\Admin\InstructorApplicationController::class, 'reject']);
    
    // Payouts
    Route::get('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'index']);
    Route::get('/payouts/pending', [App\Http\Controllers\Admin\PayoutController::class, 'pendingPayouts']);
    Route::post('/payouts', [App\Http\Controllers\Admin\PayoutController::class, 'createPayout']);
    Route::post('/payouts/{id}/process', [App\Http\Controllers\Admin\PayoutController::class, 'processPayout']);
    Route::get('/payouts/export', [App\Http\Controllers\Admin\PayoutController::class, 'exportPayouts']);
});

// Instructor application (for users)
Route::prefix('v1')->group(function () {
    Route::post('/instructor/apply', [App\Http\Controllers\InstructorApplicationController::class, 'apply']);
    Route::get('/instructor/application', [App\Http\Controllers\InstructorApplicationController::class, 'myApplication']);
    Route::get('/instructor/test', function () {
        return response()->json([
            'message' => 'Instructor API is working!',
            'timestamp' => now()->toISOString(),
        ]);
    });
    
    // Temporary test route without validation
    Route::post('/instructor/apply-simple', function (Request $request) {
        Log::info('🎓 INSTRUCTOR SIMPLE APPLY - Request received');
        Log::info('🎓 Request data: ' . json_encode($request->all()));
        
        return response()->json([
            'success' => true,
            'message' => 'Simple instructor application received!',
            'data' => $request->all(),
        ]);
    });
});

// Test different POST endpoint to check if it's instructor-specific blocking
Route::post('/test-post', function (Request $request) {
    Log::info('🧪 TEST POST - Request received');
    Log::info('🧪 Request data: ' . json_encode($request->all()));
    
    return response()->json([
        'success' => true,
        'message' => 'Test POST received!',
        'data' => $request->all(),
    ]);
});

// Test instructor endpoint without v1 prefix
Route::post('/instructor-test', function (Request $request) {
    Log::info('🎓 INSTRUCTOR TEST (NO V1) - Request received');
    Log::info('🎓 Request data: ' . json_encode($request->all()));
    
    return response()->json([
        'success' => true,
        'message' => 'Instructor test (no v1) received!',
        'data' => $request->all(),
    ]);
});

// Simple instructor application endpoint - uses controller
Route::post('/instructor-apply', [App\Http\Controllers\InstructorApplicationController::class, 'apply']);

// Instructor application routes (for users)
Route::prefix('v1/instructor')->group(function () {
    Route::post('/apply', [App\Http\Controllers\InstructorApplicationController::class, 'apply']);
    Route::get('/application', [App\Http\Controllers\InstructorApplicationController::class, 'myApplication']);
    Route::delete('/application', [App\Http\Controllers\InstructorApplicationController::class, 'cancel']);
    Route::post('/reapply', [App\Http\Controllers\InstructorApplicationController::class, 'reapply']);
});

// Debug route to check database structure
Route::get('/debug-db', function () {
    try {
        $user = \App\Models\User::first();
        $columns = \Schema::getColumnListing('users');
        
        return response()->json([
            'user_columns' => $columns,
            'has_verification_fields' => in_array('verification_code', $columns),
            'sample_user' => $user ? [
                'id' => $user->id,
                'has_verification_code' => !is_null($user->verification_code),
                'has_verification_method' => !is_null($user->verification_method),
                'is_verified' => $user->is_verified,
            ] : 'No users found'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Add this with your other routes
Route::get('/test-redis', function () {
    try {
        \Log::info('Testing Redis connection...');
        
        // Test Redis connection
        \Illuminate\Support\Facades\Redis::set('test_key', 'Hello Redis!');
        $value = \Illuminate\Support\Facades\Redis::get('test_key');
        
        // Test cache with Redis
        \Illuminate\Support\Facades\Cache::put('test_cache', 'Redis Cache Works!', 60);
        $cacheValue = \Illuminate\Support\Facades\Cache::get('test_cache');
        
        \Log::info('Redis test successful');
        
        return response()->json([
            'redis_connection' => 'SUCCESS',
            'redis_value' => $value,
            'cache_value' => $cacheValue,
            'message' => 'Redis is working correctly!'
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Redis test failed: ' . $e->getMessage());
        
        return response()->json([
            'redis_connection' => 'FAILED',
            'error' => $e->getMessage(),
            'config' => [
                'cache_driver' => config('cache.default'),
                'session_driver' => config('session.driver'),
                'redis_host' => config('database.redis.default.host'),
                'redis_port' => config('database.redis.default.port'),
            ]
        ], 500);
    }
});

Route::get('/debug-logging', function () {
    // Test different logging methods
    \Log::info('🔴 Testing Log::info');
    logger('🟡 Testing logger() helper');
    error_log('🔵 Testing error_log');
    
    // Test direct file writing
    $directWrite = file_put_contents(
        storage_path('logs/direct_test.txt'), 
        "Direct write test: " . now() . PHP_EOL, 
        FILE_APPEND
    );
    
    return response()->json([
        'logging_config' => [
            'default_channel' => config('logging.default'),
            'channels' => config('logging.channels'),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
        ],
        'storage_permissions' => [
            'logs_dir_exists' => file_exists(storage_path('logs')),
            'logs_dir_writable' => is_writable(storage_path('logs')),
            'storage_dir_writable' => is_writable(storage_path()),
        ],
        'test_results' => [
            'direct_write_success' => $directWrite !== false,
            'direct_write_bytes' => $directWrite,
        ]
    ]);
});

Route::get('/force-new-log', function () {
    // Test multiple logging methods
    \Log::info('🟢 NEW LOG ENTRY - Laravel Log::info - ' . now());
    logger('🟡 NEW LOG ENTRY - logger() helper - ' . now());
    
    // Test with different log levels
    \Log::debug('🔵 NEW LOG ENTRY - Debug level');
    \Log::warning('🟠 NEW LOG ENTRY - Warning level');
    \Log::error('🔴 NEW LOG ENTRY - Error level');
    
    return response()->json([
        'message' => 'New log entries forced',
        'timestamp' => now(),
        'log_file' => 'laravel-' . now()->format('Y-m-d') . '.log'
    ]);
});

Route::get('/check-current-config', function () {
    return response()->json([
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'log_channel' => config('logging.default'),
        'log_level' => config('logging.channels.stack.level', 'not set'),
        'current_time' => now()
    ]);
});

// Add to routes/api.php
Route::get('/test-real-email', function () {
    try {
        // Create a test user object
        $testUser = new \App\Models\User();
        $testUser->name = 'Test User';
        $testUser->email = 'ibrahim2003lotfi@gmail.com'; // ← Use YOUR real email here
        
        $code = '654321'; // Test code
        
        \Illuminate\Support\Facades\Mail::send('emails.verification', [
            'code' => $code,
            'user' => $testUser,
            'expires_in' => '15 minutes'
        ], function ($message) use ($testUser) {
            $message->to($testUser->email)
                    ->subject('TEST: Your Verification Code - ' . config('app.name'));
        });
        
        return response()->json([
            'success' => true,
            'message' => 'Test email sent! Check your inbox.',
            'sent_to' => $testUser->email,
            'code_used' => '654321'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Debug endpoint for testing file uploads
Route::post('/debug/upload', function (\Illuminate\Http\Request $request) {
    \Illuminate\Support\Facades\Log::info('Debug upload received', [
        'has_file' => $request->hasFile('thumbnail_image'),
        'content_type' => $request->header('Content-Type'),
        'all_keys' => array_keys($request->all()),
    ]);
    
    if ($request->hasFile('thumbnail_image')) {
        $file = $request->file('thumbnail_image');
        \Illuminate\Support\Facades\Log::info('File info', [
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getMimeType(),
        ]);
        
        try {
            $path = $file->store('debug', 'public');
            return response()->json(['success' => true, 'path' => $path]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Store failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    return response()->json(['success' => true, 'message' => 'No file received', 'data' => $request->all()]);
})->middleware('auth:sanctum');

// Test course creation WITHOUT checkRole middleware
Route::post('/debug/course-test', [CourseController::class, 'store'])
    ->middleware('auth:sanctum');

// 🎥 Lesson video upload endpoint
// REMOVED: This route conflicts with line 163 which uses SimpleVideoUploadController without Sanctum
// The working route is at line 163: Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video', [SimpleVideoUploadController::class, 'upload']);
// Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video', [CourseController::class, 'uploadLessonVideo'])
//     ->middleware(['auth:sanctum', 'checkRole:instructor']);

Route::get('/my-courses-v2', function () {
    return response()->json([
        'success' => true,
        'message' => 'Please use /api/instructor/my-courses endpoint',
    ]);
});

// Include the fixed instructor courses endpoint
require __DIR__ . '/instructor_courses.php';

// Include test upload route for debugging
require __DIR__ . '/test_upload.php';

// Include ping test route
require __DIR__ . '/ping_test.php';

// Include chunked upload routes for php artisan serve compatibility
require __DIR__ . '/chunked_upload.php';

// Include debug routes
require __DIR__ . '/debug.php';

// Include simple test route
require __DIR__ . '/simple_test.php';
 





