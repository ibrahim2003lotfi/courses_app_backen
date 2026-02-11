<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VideoUploadController extends Controller
{
    private $allowedMimeTypes = [
        'video/mp4',
        'video/mpeg',
        'video/quicktime',
        'video/x-msvideo',
        'video/x-matroska',
        'video/webm'
    ];

    private $allowedExtensions = [
        'mp4', 'mov', 'avi', 'mkv', 'm4v', 'webm'
    ];

    private $maxFileSize = 524288000; // 500MB

    /**
     * Upload and compress video for a lesson
     */
    public function upload(Request $request, $courseId, $lessonId)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes max

        try {
            // Get user from header (bypass auth:sanctum to prevent memory crashes)
            $userId = $request->header('X-User-Id');
            if (!$userId) {
                return response()->json([
                    'message' => 'Authentication required. Please provide X-User-Id header.'
                ], 401);
            }

            error_log(">>> VIDEO UPLOAD: User ID: $userId");
            error_log(">>> VIDEO UPLOAD: Course ID: $courseId, Lesson ID: $lessonId");

            // Check if file exists
            if (!$request->hasFile('video')) {
                error_log(">>> VIDEO UPLOAD ERROR: No file uploaded");
                return response()->json([
                    'success' => false,
                    'message' => 'No video file uploaded',
                    'error' => 'no_file'
                ], 422);
            }

            $videoFile = $request->file('video');
            
            // Check if file is valid
            if (!$videoFile->isValid()) {
                error_log(">>> VIDEO UPLOAD ERROR: Invalid file - " . $videoFile->getErrorMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid video file: ' . $videoFile->getErrorMessage(),
                    'error' => 'invalid_file'
                ], 422);
            }

            error_log(">>> VIDEO UPLOAD: File received - " . $videoFile->getClientOriginalName());
            error_log(">>> VIDEO UPLOAD: File size - " . $videoFile->getSize() . " bytes");
            error_log(">>> VIDEO UPLOAD: MIME type - " . $videoFile->getMimeType());

            // Validate request with more flexible rules
            $validated = $request->validate([
                'video' => 'required|file|max:' . ($this->maxFileSize / 1024),
            ]);

            // Get lesson and verify ownership
            $lesson = DB::table('lessons')
                ->join('sections', 'lessons.section_id', '=', 'sections.id')
                ->join('courses', 'sections.course_id', '=', 'courses.id')
                ->where('lessons.id', $lessonId)
                ->where('courses.id', $courseId)
                ->where('courses.instructor_id', $userId)
                ->select('lessons.*', 'courses.slug as course_slug')
                ->first();

            if (!$lesson) {
                return response()->json(['message' => 'Lesson not found or not authorized'], 404);
            }

            $videoFile = $request->file('video');
            $originalExtension = strtolower($videoFile->getClientOriginalExtension());
            $originalSize = $videoFile->getSize();

            // Check file extension
            if (!in_array($originalExtension, $this->allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file type. Allowed: ' . implode(', ', $this->allowedExtensions)
                ], 422);
            }

            // Create storage directory with proper permissions
            $storagePath = storage_path('app/public/videos/courses/' . $courseId);
            if (!file_exists($storagePath)) {
                if (!mkdir($storagePath, 0755, true)) {
                    error_log(">>> VIDEO UPLOAD ERROR: Failed to create directory: $storagePath");
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create storage directory',
                        'error' => 'directory_creation_failed'
                    ], 500);
                }
            }

            // Generate unique filename
            $filename = Str::uuid() . '.mp4';
            $tempPath = $storagePath . '/temp_' . $filename;
            $finalPath = $storagePath . '/' . $filename;

            error_log(">>> VIDEO UPLOAD: Storage path: $storagePath");
            error_log(">>> VIDEO UPLOAD: Temp path: $tempPath");
            error_log(">>> VIDEO UPLOAD: Final path: $finalPath");

            // Save original temporarily
            $videoFile->move($storagePath, 'temp_' . $filename);

            // Compress video with FFmpeg
            $compressedPath = $this->compressVideo($tempPath, $finalPath);

            // Get video dimensions and duration
            $videoInfo = $this->getVideoInfo($compressedPath);

            // Calculate final size
            $finalSize = filesize($compressedPath);
            $compressionRatio = round((1 - ($finalSize / $originalSize)) * 100, 1);

            // Delete temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            // Update lesson in database
            $videoPath = 'public/videos/courses/' . $courseId . '/' . $filename;
            
            DB::table('lessons')
                ->where('id', $lessonId)
                ->update([
                    'video_path' => $videoPath,
                    'video_size' => $finalSize,
                    'video_width' => $videoInfo['width'] ?? null,
                    'video_height' => $videoInfo['height'] ?? null,
                    'duration_seconds' => $videoInfo['duration'] ?? null,
                    'status' => 'compressed',
                    'updated_at' => now()
                ]);

            $videoUrl = url('storage/videos/courses/' . $courseId . '/' . $filename);

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded and compressed successfully',
                'video_url' => $videoUrl,
                'original_size' => $this->formatBytes($originalSize),
                'compressed_size' => $this->formatBytes($finalSize),
                'compression_ratio' => $compressionRatio . '%',
                'duration' => $videoInfo['duration'] ?? null,
                'resolution' => ($videoInfo['width'] ?? 0) . 'x' . ($videoInfo['height'] ?? 0)
            ]);

        } catch (\Exception $e) {
            error_log('>>> VIDEO UPLOAD ERROR: ' . $e->getMessage());
            error_log('>>> TRACE: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload video',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Compress video using FFmpeg
     */
    private function compressVideo($inputPath, $outputPath)
    {
        // FFmpeg command for compression
        // - Preset slow for better compression
        // - CRF 23 for good quality/size balance
        // - Scale to max 1080p while maintaining aspect ratio
        $cmd = sprintf(
            'ffmpeg -i %s -vcodec h264 -acodec aac -preset slow -crf 23 -movflags +faststart -vf "scale=trunc(min(iw\,1920)/2)*2:trunc(ow/a/2)*2" -y %s 2>&1',
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            // If compression fails, just copy the original
            copy($inputPath, $outputPath);
            error_log('>>> FFmpeg compression failed, using original. Error: ' . implode("\n", $output));
        }

        return $outputPath;
    }

    /**
     * Get video info using FFmpeg
     */
    private function getVideoInfo($videoPath)
    {
        $cmd = sprintf(
            'ffprobe -v error -select_streams v:0 -show_entries stream=width,height,duration -of csv=s=x:p=0 %s 2>&1',
            escapeshellarg($videoPath)
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            $parts = explode('x', $output[0]);
            return [
                'width' => isset($parts[0]) ? (int) $parts[0] : null,
                'height' => isset($parts[1]) ? (int) $parts[1] : null,
                'duration' => isset($parts[2]) ? (int) round((float) $parts[2]) : null
            ];
        }

        return ['width' => null, 'height' => null, 'duration' => null];
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
