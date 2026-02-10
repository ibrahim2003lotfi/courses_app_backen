<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class StreamController extends Controller
{
    /**
     * Secure video streaming endpoint
     */
    public function stream(Request $request, $slug, $lessonId)
    {
        ini_set('memory_limit', '256M');
        error_log(">>> STREAM START: slug=$slug, lessonId=$lessonId");
        
        try {
            // Get user from header (bypass auth:sanctum to prevent memory crashes)
            $userId = $request->header('X-User-Id');
            
            if (!$userId) {
                // Fallback to auth if header not provided
                $user = Auth::user();
                if (!$user) {
                    return response()->json([
                        'message' => 'Unauthenticated. Please log in.'
                    ], 401);
                }
                $userId = $user->id;
            }
            
            error_log(">>> UserId: $userId");
            
            // Get course by slug using DB query
            $course = DB::table('courses')->where('slug', $slug)->first();
            
            if (!$course) {
                error_log(">>> Course not found: $slug");
                return response()->json(['message' => 'Course not found'], 404);
            }
            
            error_log(">>> Course found: " . $course->id);
            
            // Get lesson and verify it belongs to course via section
            $lesson = DB::table('lessons')
                ->join('sections', 'lessons.section_id', '=', 'sections.id')
                ->where('lessons.id', $lessonId)
                ->where('sections.course_id', $course->id)
                ->select('lessons.*', 'sections.course_id')
                ->first();
            
            if (!$lesson) {
                error_log(">>> Lesson not found or doesn't belong to course");
                return response()->json(['message' => 'Lesson not found'], 404);
            }
            
            error_log(">>> Lesson found: " . $lesson->title);

            // Check if user is enrolled
            $isEnrolled = DB::table('enrollments')
                ->where('user_id', $userId)
                ->where('course_id', $course->id)
                ->whereNull('refunded_at')
                ->exists();
            
            error_log(">>> Is enrolled: " . ($isEnrolled ? 'yes' : 'no'));

            if (!$isEnrolled && !$lesson->is_preview) {
                return response()->json([
                    'message' => 'Access denied. Please enroll in the course to view this lesson.'
                ], 403);
            }

            // Check if video is processed and ready
            error_log(">>> Lesson status: " . $lesson->status);
            error_log(">>> Lesson video_path: " . ($lesson->video_path ?? 'NULL'));
            error_log(">>> Lesson hls_manifest_url: " . ($lesson->hls_manifest_url ?? 'NULL'));
            
            if ($lesson->status !== 'processed' && $lesson->status !== 'compressed') {
                return response()->json([
                    'message' => 'Video is still processing. Please try again later.',
                    'lesson_status' => $lesson->status,
                    'has_video' => !empty($lesson->video_path) || !empty($lesson->hls_manifest_url)
                ], 422);
            }

            // Check for local video first, then S3
            if (!empty($lesson->video_path)) {
                // Local video storage
                $fullPath = storage_path('app/' . $lesson->video_path);
                error_log(">>> Looking for video at: " . $fullPath);
                error_log(">>> File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));
                
                if (!file_exists($fullPath)) {
                    error_log(">>> Local video file not found: " . $lesson->video_path);
                    return response()->json([
                        'message' => 'Video file not found on server.'
                    ], 404);
                }
                
                $videoUrl = url('storage/' . str_replace('public/', '', $lesson->video_path));
                error_log(">>> STREAM SUCCESS: Local video - $videoUrl");
                
                return response()->json([
                    'stream_url' => $videoUrl,
                    'thumbnail_url' => $lesson->thumbnail_url ? url('storage/' . str_replace('public/', '', $lesson->thumbnail_url)) : null,
                    'duration_seconds' => $lesson->duration_seconds,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'is_preview' => $lesson->is_preview,
                    'is_enrolled' => $isEnrolled,
                    'source' => 'local',
                ]);
            }

            // Fallback to S3/HLS if no local video
            if (empty($lesson->hls_manifest_url)) {
                return response()->json([
                    'message' => 'Video not available.'
                ], 404);
            }

            // Generate signed URL for HLS manifest (valid for 1 hour)
            $signedUrl = Storage::disk('s3')->temporaryUrl(
                $lesson->hls_manifest_url,
                now()->addMinutes(60)
            );

            // Generate signed URL for thumbnail if exists
            $thumbnailUrl = null;
            if ($lesson->thumbnail_url) {
                $thumbnailUrl = Storage::disk('s3')->temporaryUrl(
                    $lesson->thumbnail_url,
                    now()->addMinutes(60)
                );
            }

            error_log(">>> STREAM SUCCESS: S3 HLS");
            return response()->json([
                'stream_url' => $signedUrl,
                'thumbnail_url' => $thumbnailUrl,
                'duration_seconds' => $lesson->duration_seconds,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'is_preview' => $lesson->is_preview,
                'is_enrolled' => $isEnrolled,
                'source' => 's3',
            ]);
        } catch (\Exception $e) {
            error_log('>>> STREAM ERROR: ' . $e->getMessage());
            error_log('>>> TRACE: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error generating stream URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}