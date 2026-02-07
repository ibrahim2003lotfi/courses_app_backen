

















<?php

/*

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
// Controllers
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
use App\Http\Controllers\SearchController;
use App\Http\Controllers\HomeController;

// ==============================
// 🔐 AUTH ROUTES
// ==============================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify', [AuthController::class, 'verify']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/login', [AuthController::class, 'apiLogin']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// ==============================
// 🧪 DEBUG ROUTES
// ==============================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/debug-user', function () {
        $user = auth('sanctum')->user();
        if (!$user) return response()->json(['message' => 'Not authenticated'], 401);

        $debug = [
            'user_id' => $user->id,
            'user_class' => get_class($user),
            'role_field' => $user->role ?? 'not set',
            'traits' => class_uses($user),
            'has_hasRole_method' => method_exists($user, 'hasRole'),
        ];

        // database roles
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

        return response()->json($debug);
    });
});

// ==============================
// 🟡 INSTRUCTOR ROUTES
// ==============================
Route::prefix('instructor')
    ->middleware(['auth:sanctum', 'checkRole:instructor'])
    ->group(function () {

    // Courses
    Route::get('/courses', [CourseController::class, 'index']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);

    // Sections
    Route::get('/courses/{courseId}/sections', [SectionController::class, 'index']);
    Route::post('/courses/{courseId}/sections', [SectionController::class, 'store']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [SectionController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [SectionController::class, 'destroy']);
    Route::post('/courses/{courseId}/sections/reorder', [SectionController::class, 'reorder']);

    // Lessons
    Route::get('/courses/{courseId}/sections/{sectionId}/lessons', [LessonController::class, 'index']);
    Route::post('/courses/{courseId}/sections/{sectionId}/lessons', [LessonController::class, 'store']);
    Route::put('/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}', [LessonController::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}/lessons/{lessonId}', [LessonController::class, 'destroy']);
    Route::post('/courses/{courseId}/sections/{sectionId}/lessons/reorder', [LessonController::class, 'reorder']);

    // Media
    Route::post('/media/sign', [MediaController::class, 'sign']);
    Route::post('/media/confirm', [MediaController::class, 'confirm']);
    Route::delete('/media/delete', [MediaController::class, 'delete']);
});

// ==============================
// 🔵 PUBLIC COURSES
// ==============================
Route::get('/courses', [CourseController::class, 'publicIndex']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::get('/courses/{slug}/stream/{lessonId}', [StreamController::class, 'stream'])
    ->middleware('auth:sanctum');

// ==============================
// ⭐ COURSE REVIEWS
// ==============================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courses/{course}/rate', [ReviewController::class, 'store']);
    Route::get('/courses/{course}/my-rating', [ReviewController::class, 'show']);
    Route::delete('/courses/{course}/my-rating', [ReviewController::class, 'destroy']);
    Route::get('/my-ratings', [ReviewController::class, 'getUserRatings']);
});

Route::get('/courses/{course}/rating', [ReviewController::class, 'getCourseRating']);

// ==============================
// 💳 PAYMENT ROUTES
// ==============================
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/courses/{courseId}/payment', [PaymentController::class, 'initiatePayment']);
    Route::post('/payments/confirm', [PaymentController::class, 'confirmPayment']);
    Route::get('/payments/{orderId}/status', [PaymentController::class, 'getPaymentStatus']);
});

// Admin payment verification
Route::middleware(['auth:sanctum', 'checkRole:admin'])
    ->prefix('admin/payments')
    ->group(function () {
        Route::get('/pending', [PaymentVerificationController::class, 'pendingPayments']);
        Route::post('/{orderId}/verify', [PaymentVerificationController::class, 'verifyPayment']);
        Route::post('/{orderId}/reject', [PaymentVerificationController::class, 'rejectPayment']);
    });

// Refunds
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/refunds')
    ->group(function () {
        Route::get('/history', [RefundController::class, 'refundHistory']);
        Route::get('/pending', [RefundController::class, 'pendingRefunds']);
        Route::get('/orders/{orderId}/refund/check', [RefundController::class, 'checkEligibility']);
        Route::post('/orders/{orderId}/refund', [RefundController::class, 'refundOrder']);
    });

Route::post('/refunds/{orderId}/approve', [RefundController::class, 'approveRefund']);

// ==============================
// 🔍 SEARCH + HOME
// ==============================
Route::prefix('v1')->group(function () {
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/suggestions', [SearchController::class, 'suggestions']);
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/categories/{slug}/courses', [HomeController::class, 'categoryDetail']);
});

// ==============================
// 🛡 ADMIN PANEL ROUTES
// ==============================
Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {

    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index']);

    // Users
    Route::get('/users', [App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::get('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'show']);
    Route::put('/users/{id}', [App\Http\Controllers\Admin\UserController::class, 'update']);
    Route::post('/users/{id}/toggle-status', [App\Http\Controllers\Admin\UserController::class, 'toggleStatus']);

    // Courses
    Route::get('/courses', [App\Http\Controllers\Admin\CourseController::class, 'index']);
    Route::get('/courses/{id}', [App\Http\Controllers\Admin\CourseController::class, 'show']);
    Route::post('/courses/{id}/toggle-status', [App\Http\Controllers\Admin\CourseController::class, 'toggleStatus']);

    // Orders
    Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index']);
    Route::get('/orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'show']);
    Route::post('/orders/{id}/refund', [App\Http\Controllers\Admin\OrderController::class, 'processRefund']);

    // Instructor Applications
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

// ==============================
// 🧩 INSTRUCTOR APPLICATION (USER)
// ==============================
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::post('/instructor/apply', [App\Http\Controllers\InstructorApplicationController::class, 'apply']);
    Route::get('/instructor/application', [App\Http\Controllers\InstructorApplicationController::class, 'myApplication']);
});

// Duplicated but kept
Route::middleware('auth:sanctum')->prefix('v1/instructor')->group(function () {
    Route::post('/apply', [App\Http\Controllers\InstructorApplicationController::class, 'apply']);
    Route::get('/application', [App\Http\Controllers\InstructorApplicationController::class, 'myApplication']);
    Route::delete('/application', [App\Http\Controllers\InstructorApplicationController::class, 'cancel']);
    Route::post('/reapply', [App\Http\Controllers\InstructorApplicationController::class, 'reapply']);
});

// ==============================
// 🧪 DEBUG ROUTES 2 (Logs / Redis)
// ==============================
Route::get('/test-redis', function () {
    try {
        Redis::set('test_key', 'Hello Redis!');
        $value = Redis::get('test_key');

        Cache::put('test_cache', 'Redis Cache Works!', 60);

        return response()->json([
            'redis_value' => $value,
            'cache_value' => Cache::get('test_cache'),
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug-logging', function () {
    \Log::info('Debug logging test');

    return response()->json([
        'log_channel' => config('logging.default'),
        'app_env' => config('app.env'),
    ]);
});

// ==============================
// 📧 Test Email
// ==============================
Route::get('/test-real-email', function () {
    try {
        $testUser = new \App\Models\User();
        $testUser->name = 'Test User';
        $testUser->email = 'ibrahim2003lotfi@gmail.com';

        Mail::send('emails.verification', [
            'code' => '654321',
            'user' => $testUser,
            'expires_in' => '15 minutes'
        ], function ($message) use ($testUser) {
            $message->to($testUser->email)
                    ->subject('TEST: Your Verification Code');
        });

        return response()->json(['message' => 'Email sent']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

*/
