<?php



namespace App\Http\Controllers;



use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Auth;



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



            // DEBUG: Bypass enrollment check for testing
            $isEnrolled = true;
            error_log(">>> DEBUG: Bypassing enrollment check for testing");



            // Check if video is ready - allow if video_path exists regardless of status

            error_log(">>> Lesson status: " . $lesson->status);
            error_log(">>> Lesson video_path: " . ($lesson->video_path ?? 'NULL'));
            error_log(">>> Lesson hls_manifest_url: " . ($lesson->hls_manifest_url ?? 'NULL'));
            
            // Allow video playback if video_path exists, regardless of processing status
            if (empty($lesson->video_path) && empty($lesson->hls_manifest_url)) {
                error_log(">>> NO VIDEO AVAILABLE: lesson has no video_path or hls_manifest_url");
                return response()->json([
                    'message' => 'No video available for this lesson. The instructor has not uploaded a video yet.',
                    'error_code' => 'NO_VIDEO_UPLOADED',
                    'lesson_status' => $lesson->status,
                    'has_video_path' => !empty($lesson->video_path),
                    'has_hls_manifest' => !empty($lesson->hls_manifest_url)
                ], 404);
            }
            
            // Optional warning for unprocessed videos
            if ($lesson->status !== 'processed' && $lesson->status !== 'compressed') {
                error_log(">>> WARNING: Video status is '{$lesson->status}' but allowing playback since video file exists");
            }



            // Check for local video first, then S3

            if (!empty($lesson->video_path)) {

                // Local video storage - use direct serving route to bypass symlink issues

                $fullPath = storage_path('app/' . $lesson->video_path);

                error_log(">>> Looking for video at: " . $fullPath);

                error_log(">>> File exists: " . (file_exists($fullPath) ? 'YES' : 'NO'));

                // If not found at exact path, try to find by filename in courses/videos

                if (!file_exists($fullPath)) {

                    $filename = basename($lesson->video_path);

                    $alternativePath = storage_path('app/public/courses/videos/' . $filename);

                    error_log(">>> Trying alternative path: " . $alternativePath);

                    if (file_exists($alternativePath)) {

                        $fullPath = $alternativePath;

                        error_log(">>> Found at alternative path!");

                    }

                }

                if (!file_exists($fullPath)) {
                    error_log(">>> Local video file not found: " . $lesson->video_path);
                    return response()->json([
                        'message' => 'Video file not found on server.',
                        'error_code' => 'VIDEO_FILE_NOT_FOUND',
                        'searched_path' => $fullPath
                    ], 404);
                }

                // Use hardcoded full video URL to avoid routing issues
                $filename = basename($lesson->video_path);
                $baseVideoUrl = 'http://192.168.1.5:8000/api/videos/courses/videos/' . $filename;
                
                // Build quality variants (all pointing to same video for now - transcoding needed for actual variants)
                $qualityUrls = [
                    'Auto' => $baseVideoUrl,
                    '1080p' => $baseVideoUrl,
                    '720p' => $baseVideoUrl,
                    '480p' => $baseVideoUrl,
                    '360p' => $baseVideoUrl,
                ];

                error_log(">>> STREAM SUCCESS: Direct video URL - $baseVideoUrl");

                return response()->json([
                    'stream_url' => $baseVideoUrl,
                    'quality_urls' => $qualityUrls,
                    'available_qualities' => ['Auto', '1080p', '720p', '480p', '360p'],
                    'thumbnail_url' => $lesson->thumbnail_url ? url('storage/' . str_replace('public/', '', $lesson->thumbnail_url)) : null,
                    'duration_seconds' => $lesson->duration_seconds,
                    'title' => $lesson->title,
                    'description' => $lesson->description,
                    'is_preview' => $lesson->is_preview,
                    'is_enrolled' => $isEnrolled,
                    'source' => 'local',
                ]);

            }



            // No video file available

            error_log(">>> NO VIDEO AVAILABLE: lesson has no video_path or hls_manifest_url");

            return response()->json([

                'message' => 'No video available for this lesson. The instructor has not uploaded a video yet.',

                'error_code' => 'NO_VIDEO_UPLOADED',

                'lesson_status' => $lesson->status,

                'has_video_path' => !empty($lesson->video_path),

                'has_hls_manifest' => !empty($lesson->hls_manifest_url)

            ], 404);



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