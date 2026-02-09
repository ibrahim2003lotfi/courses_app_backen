<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

// Test endpoint to debug course creation issues
Route::post('/api/debug/test-validation', function (Request $request) {
    Log::info('TEST VALIDATION - Raw input: ' . json_encode($request->all()));
    
    $errors = [];
    
    // Check each field
    $fields = ['title', 'price', 'university_id', 'university_name', 'faculty_id', 'faculty_name', 'level', 'type'];
    foreach ($fields as $field) {
        $value = $request->input($field);
        Log::info("Field $field: " . var_export($value, true));
        
        if ($field === 'title' && empty($value)) {
            $errors[] = 'Title is required';
        }
        if ($field === 'price' && $value !== null && !is_numeric($value)) {
            $errors[] = 'Price must be numeric, got: ' . var_export($value, true);
        }
    }
    
    if (empty($errors)) {
        return response()->json([
            'success' => true,
            'message' => 'All fields valid!',
            'received' => $request->all(),
        ]);
    }
    
    return response()->json([
        'success' => false,
        'message' => 'Validation errors found',
        'errors' => $errors,
        'received' => $request->all(),
    ], 422);
});
