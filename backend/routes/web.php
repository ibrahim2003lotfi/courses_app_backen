<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth; // ADD THIS IMPORT
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminCourseController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminInstructorApplicationController;
use App\Http\Controllers\Admin\AdminPayoutController;
use App\Http\Controllers\AuthController;
use Inertia\Inertia;

// Root redirect (ONLY ONE)
Route::get('/', function () {
    if (Auth::check()) {
        if (Auth::user()->hasRole('admin')) {
            return redirect('/admin');
        }
        return redirect('/dashboard');
    }
    return redirect('/login');
});

// Auth routes
Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login')->middleware('guest');

Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Test routes (for debugging)
Route::get('/test-laravel', function () {
    return 'Laravel is working!';
});

Route::get('/test-inertia', function () {
    return Inertia::render('Test', [
        'message' => 'Inertia is working!'
    ]);
});

// Admin routes (protected with auth and admin role)
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    
    // Users - using resource controller
    Route::resource('users', AdminUserController::class)->names('admin.users');
    
    // Courses - using resource controller + additional routes
    Route::resource('courses', AdminCourseController::class)->names('admin.courses');
    Route::post('courses/{id}/toggle-status', [AdminCourseController::class, 'toggleStatus'])->name('admin.courses.toggle');
    Route::post('courses/bulk-action', [AdminCourseController::class, 'bulkAction'])->name('admin.courses.bulk');
    Route::get('courses/{id}/reviews', [AdminCourseController::class, 'reviews'])->name('admin.courses.reviews');
    Route::delete('courses/{courseId}/reviews/{reviewId}', [AdminCourseController::class, 'deleteReview'])->name('admin.courses.reviews.delete');
    
    // Orders - using resource controller + refund
    Route::resource('orders', AdminOrderController::class)->names('admin.orders');
    Route::post('orders/{id}/refund', [AdminOrderController::class, 'refund'])->name('admin.orders.refund');
    Route::get('refunds', [AdminOrderController::class, 'refunds'])->name('admin.refunds.index');
    
    // Instructor Applications (NO DUPLICATES)
    Route::get('instructor-applications', [AdminInstructorApplicationController::class, 'index'])->name('admin.applications.index');
    Route::get('instructor-applications/{id}', [AdminInstructorApplicationController::class, 'show'])->name('admin.applications.show');
    Route::post('instructor-applications/{id}/approve', [AdminInstructorApplicationController::class, 'approve'])->name('admin.applications.approve');
    Route::post('instructor-applications/{id}/reject', [AdminInstructorApplicationController::class, 'reject'])->name('admin.applications.reject');
    Route::post('instructor-applications/bulk-action', [AdminInstructorApplicationController::class, 'bulkAction'])->name('admin.applications.bulk');
    Route::get('instructor-applications/{id}/document/{key}', [AdminInstructorApplicationController::class, 'downloadDocument'])->name('admin.applications.document');
    
    // Payouts (NO DUPLICATES)
    Route::get('payouts', [AdminPayoutController::class, 'index'])->name('admin.payouts.index');
    Route::get('payouts/pending', [AdminPayoutController::class, 'pendingPayouts'])->name('admin.payouts.pending');
    Route::post('payouts', [AdminPayoutController::class, 'create'])->name('admin.payouts.create');
    Route::get('payouts/{id}', [AdminPayoutController::class, 'show'])->name('admin.payouts.show');
    Route::post('payouts/{id}/process', [AdminPayoutController::class, 'process'])->name('admin.payouts.process');
    Route::post('payouts/{id}/complete', [AdminPayoutController::class, 'complete'])->name('admin.payouts.complete');
    Route::post('payouts/{id}/cancel', [AdminPayoutController::class, 'cancel'])->name('admin.payouts.cancel');
    Route::get('payouts/export', [AdminPayoutController::class, 'export'])->name('admin.payouts.export');
    
    // Test route for admin
    Route::get('/test', function () {
        return Inertia::render('Test');
    });
});

// Optional: Add a default dashboard for non-admin users
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth'])->name('dashboard');

// Optional: Student dashboard
Route::get('/student/dashboard', function () {
    return Inertia::render('Student/Dashboard');
})->middleware(['auth', 'role:student'])->name('student.dashboard');

// Optional: Instructor dashboard  
Route::get('/instructor/dashboard', function () {
    return Inertia::render('Instructor/Dashboard');
})->middleware(['auth', 'role:instructor'])->name('instructor.dashboard');