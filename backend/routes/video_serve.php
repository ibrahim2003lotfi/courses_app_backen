<?php

// Public video file serving - NO AUTHENTICATION REQUIRED
// The security is handled by the stream endpoint which returns a unique URL

Route::get('/videos/{path}', function ($path) {
    try {
        error_log(">>> VIDEO SERVE API: Raw path: " . $path);
        
        // Decode URL-encoded path
        $path = urldecode($path);
        error_log(">>> VIDEO SERVE API: Decoded path: " . $path);
        
        // Security: Prevent directory traversal
        if (strpos($path, '..') !== false || strpos($path, '~') !== false) {
            error_log(">>> VIDEO SERVE API: Invalid path detected");
            abort(403, 'Invalid path');
        }
        
        // Only allow access to courses/videos directory
        if (!str_starts_with($path, 'courses/videos')) {
            error_log(">>> VIDEO SERVE API: Path doesn't start with courses/videos: " . $path);
            abort(403, 'Access denied');
        }
        
        $fullPath = storage_path('app/public/' . $path);
        error_log(">>> VIDEO SERVE API: Looking at: " . $fullPath);
        error_log(">>> VIDEO SERVE API: File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));
        
        if (!file_exists($fullPath)) {
            // Try to find by filename
            $filename = basename($path);
            $coursesPath = storage_path('app/public/courses/videos/' . $filename);
            error_log(">>> VIDEO SERVE API: Trying alternative: " . $coursesPath);
            
            if (file_exists($coursesPath)) {
                $fullPath = $coursesPath;
                error_log(">>> VIDEO SERVE API: Found at alternative path!");
            } else {
                error_log(">>> VIDEO SERVE API: File not found anywhere");
                abort(404, 'Video not found');
            }
        }
        
        $mimeType = mime_content_type($fullPath) ?: 'video/mp4';
        error_log(">>> VIDEO SERVE API: Serving file with mime: " . $mimeType);
        
        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"',
            'Accept-Ranges' => 'bytes',
        ]);
        
    } catch (\Exception $e) {
        error_log(">>> VIDEO SERVE API ERROR: " . $e->getMessage());
        abort(500, 'Error serving video');
    }
})->where('path', '.*');
