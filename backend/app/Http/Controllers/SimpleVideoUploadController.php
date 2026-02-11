<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimpleVideoUploadController extends Controller
{
    private $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'm4v', 'webm'];
    private $maxFileSize = 524288000; // 500MB - increased for larger videos

    public function upload(Request $request, $courseId, $lessonId)
    {
        // Check PHP upload limits first
        $maxUpload = ini_get('upload_max_filesize');
        $maxPost = ini_get('post_max_size');
        error_log(">>> SIMPLE VIDEO UPLOAD: PHP limits - upload_max_filesize: $maxUpload, post_max_size: $maxPost");
        
        // Check if request exceeded post_max_size (PHP silently drops the request)
        if (empty($_FILES) && empty($_POST) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            error_log(">>> SIMPLE VIDEO UPLOAD ERROR: Request too large, exceeded post_max_size");
            return response()->json([
                'success' => false,
                'message' => 'File too large for server. Maximum upload size: ' . ini_get('upload_max_filesize'),
                'error' => 'file_too_large_for_php',
            ], 413);
        }
        
        try {
            // Get user from header
            $userId = $request->header('X-User-Id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please provide X-User-Id header.'
                ], 401);
            }

            error_log(">>> SIMPLE VIDEO UPLOAD: User ID: $userId");
            error_log(">>> SIMPLE VIDEO UPLOAD: Course ID: $courseId, Lesson ID: $lessonId");

            // Check if file exists
            if (!$request->hasFile('video')) {
                error_log(">>> SIMPLE VIDEO UPLOAD ERROR: No file uploaded");
                return response()->json([
                    'success' => false,
                    'message' => 'No video file uploaded',
                    'error' => 'no_file'
                ], 422);
            }

            $videoFile = $request->file('video');
            
            // Check if file is valid
            if (!$videoFile->isValid()) {
                error_log(">>> SIMPLE VIDEO UPLOAD ERROR: Invalid file - " . $videoFile->getErrorMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid video file: ' . $videoFile->getErrorMessage(),
                    'error' => 'invalid_file'
                ], 422);
            }

            // Check file extension
            $extension = strtolower($videoFile->getClientOriginalExtension());
            if (!in_array($extension, $this->allowedExtensions)) {
                error_log(">>> SIMPLE VIDEO UPLOAD ERROR: Invalid extension: $extension");
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedExtensions),
                    'error' => 'invalid_extension'
                ], 422);
            }

            error_log(">>> SIMPLE VIDEO UPLOAD: File received - " . $videoFile->getClientOriginalName());
            error_log(">>> SIMPLE VIDEO UPLOAD: File size - " . $videoFile->getSize() . " bytes (" . $this->formatBytes($videoFile->getSize()) . ")");
            
            // Check file size (500MB limit)
            if ($videoFile->getSize() > $this->maxFileSize) {
                error_log(">>> SIMPLE VIDEO UPLOAD ERROR: File too large - " . $videoFile->getSize());
                return response()->json([
                    'success' => false,
                    'message' => 'File too large. Maximum size: ' . $this->formatBytes($this->maxFileSize),
                    'error' => 'file_too_large'
                ], 422);
            }

            // Get lesson first (simplified check)
            $lesson = DB::table('lessons')->where('id', $lessonId)->first();
            if (!$lesson) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lesson not found'
                ], 404);
            }

            // Store video file directly using stream to save memory
            $filename = Str::uuid() . '.' . $extension;
            $directory = 'videos/courses/' . $courseId;
            
            // Ensure directory exists
            Storage::disk('public')->makeDirectory($directory);
            
            // Move the uploaded file instead of store() to avoid memory issues
            $path = $videoFile->storeAs($directory, $filename, 'public');
            
            if (!$path) {
                error_log(">>> SIMPLE VIDEO UPLOAD ERROR: Failed to store file");
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to store video file',
                    'error' => 'storage_failed'
                ], 500);
            }

            // Update lesson in database
            $videoPath = 'public/' . $path;
            
            $updateResult = DB::table('lessons')
                ->where('id', $lessonId)
                ->update([
                    'video_path' => $videoPath,
                    'video_size' => $videoFile->getSize(),
                    'status' => 'compressed',
                    'updated_at' => now()
                ]);

            error_log(">>> SIMPLE VIDEO UPLOAD: Database update result: " . ($updateResult ? 'SUCCESS' : 'FAILED'));

            $videoUrl = url('storage/' . $path);

            error_log(">>> SIMPLE VIDEO UPLOAD SUCCESS: $videoUrl");

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'video_url' => $videoUrl,
                'file_size' => $this->formatBytes($videoFile->getSize())
            ]);

        } catch (\Exception $e) {
            error_log('>>> SIMPLE VIDEO UPLOAD ERROR: ' . $e->getMessage());
            error_log('>>> TRACE: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload video: ' . $e->getMessage(),
                'error' => 'upload_failed'
            ], 500);
        }
    }

    private function formatBytes($size)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        return round($size, 2) . ' ' . $units[$i];
    }
}
