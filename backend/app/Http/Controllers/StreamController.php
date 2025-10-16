<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Enrollment;
use App\Models\Section;

class StreamController extends Controller
{
    /**
     * Secure video streaming endpoint
     */
    public function stream(Request $request, $slug, $lessonId)
    {
        // Get authenticated user using Auth facade
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated. Please log in.'
            ], 401);
        }
        
        // Get the course by slug
        $course = Course::where('slug', $slug)->firstOrFail();
        
        // Get the lesson and ensure it belongs to the course
        $lesson = Lesson::where('id', $lessonId)
            ->whereHas('section', function($q) use ($course) {
                $q->where('course_id', $course->id);
            })->firstOrFail();

        // Check if user is enrolled or lesson is preview
        $isEnrolled = false;
        
        // First check if enrollment model exists and has records
        if (class_exists(Enrollment::class)) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->whereNull('refunded_at')
                ->exists();
        } else {
            // If enrollment table doesn't exist yet, allow access for testing
            // Remove this else block once enrollment system is implemented
            $isEnrolled = true;
        }

        if (!$isEnrolled && !$lesson->is_preview) {
            return response()->json([
                'message' => 'Access denied. Please enroll in the course to view this lesson.'
            ], 403);
        }

        // Check if video is processed and ready
        if ($lesson->status !== 'processed' || !$lesson->hls_manifest_url) {
            return response()->json([
                'message' => 'Video is still processing. Please try again later.',
                'lesson_status' => $lesson->status,
                'has_hls_manifest' => !empty($lesson->hls_manifest_url)
            ], 422);
        }

        try {
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

            return response()->json([
                'stream_url' => $signedUrl,
                'thumbnail_url' => $thumbnailUrl,
                'duration_seconds' => $lesson->duration_seconds,
                'title' => $lesson->title,
                'description' => $lesson->description,
                'is_preview' => $lesson->is_preview,
                'is_enrolled' => $isEnrolled,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error generating stream URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}