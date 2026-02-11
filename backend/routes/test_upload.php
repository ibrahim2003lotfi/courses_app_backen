<?php

// Minimal video upload test - no dependencies, no database
Route::post('/test-video-upload/{courseId}/{lessonId}', function ($courseId, $lessonId) {
    $request = request();
    
    // Log basic request info
    error_log(">>> TEST UPLOAD: Starting upload test");
    error_log(">>> TEST UPLOAD: Course: $courseId, Lesson: $lessonId");
    error_log(">>> TEST UPLOAD: Method: " . $request->method());
    error_log(">>> TEST UPLOAD: Content-Type: " . $request->header('Content-Type'));
    error_log(">>> TEST UPLOAD: X-User-Id: " . $request->header('X-User-Id'));
    
    // Check for files
    $hasFile = $request->hasFile('video');
    error_log(">>> TEST UPLOAD: Has file 'video': " . ($hasFile ? 'YES' : 'NO'));
    
    if ($hasFile) {
        $file = $request->file('video');
        error_log(">>> TEST UPLOAD: File name: " . $file->getClientOriginalName());
        error_log(">>> TEST UPLOAD: File size: " . $file->getSize());
        error_log(">>> TEST UPLOAD: File valid: " . ($file->isValid() ? 'YES' : 'NO'));
        
        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_file',
                'message' => 'File upload error: ' . $file->getErrorMessage(),
                'error_code' => $file->getError()
            ], 422);
        }
        
        // Try to store
        try {
            $path = $file->store('test-videos', 'public');
            error_log(">>> TEST UPLOAD: Stored at: $path");
            return response()->json([
                'success' => true,
                'message' => 'Test upload successful',
                'path' => $path,
                'size' => $file->getSize()
            ]);
        } catch (\Exception $e) {
            error_log(">>> TEST UPLOAD ERROR: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'storage_failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    // No file - check what we received
    error_log(">>> TEST UPLOAD: All keys: " . json_encode(array_keys($request->all())));
    error_log(">>> TEST UPLOAD: Files array: " . json_encode($_FILES));
    
    return response()->json([
        'success' => false,
        'error' => 'no_file',
        'message' => 'No video file in request',
        'received_keys' => array_keys($request->all()),
        'files' => $_FILES
    ], 422);
});
