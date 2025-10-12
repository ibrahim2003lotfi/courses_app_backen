<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;

// 🟢 Auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

// 🟡 Instructor-only routes
Route::middleware(['auth:sanctum', 'role:instructor'])->group(function () {
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{id}', [CourseController::class, 'update']);
    Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
    Route::get('/instructor/courses', [CourseController::class, 'index']);
});

// 🔵 Public routes (anyone can see)
Route::get('/courses', [CourseController::class, 'publicIndex']); // pagination

Route::get('/courses/{slug}', [CourseController::class, 'show']); // 🟢 عرض تفاصيل كورس

// test route
Route::get('/test', fn() => response()->json(['message' => 'API is working']));


