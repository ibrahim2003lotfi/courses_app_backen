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


// 🟢 Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

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
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/courses', [CourseController::class, 'index']);

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

// Test routes
Route::get('/test', fn() => response()->json(['message' => 'API is working']));

// In your routes/api.php
Route::get('/courses/{slug}/stream/{lessonId}', [StreamController::class, 'stream'])
    ->middleware('auth:sanctum');

// Create StreamController



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