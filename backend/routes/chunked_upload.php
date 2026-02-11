<?php

use App\Http\Controllers\ChunkedVideoUploadController;

// Chunked video upload routes - work around php artisan serve limitations
Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video-chunked/init', 
    [ChunkedVideoUploadController::class, 'initChunk']);
    
Route::post('/upload-chunk/{uploadId}/{chunkIndex}', 
    [ChunkedVideoUploadController::class, 'uploadChunk']);
    
Route::post('/instructor/courses/{courseId}/lessons/{lessonId}/video-chunked/finalize', 
    [ChunkedVideoUploadController::class, 'finalize']);
