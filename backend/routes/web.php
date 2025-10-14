<?php

use Illuminate\Support\Facades\Route;

// Fallback login route للتخلص من الخطأ
Route::get('/login', function () {
    return response()->json(['message' => 'Please login via API'], 401);
})->name('login');