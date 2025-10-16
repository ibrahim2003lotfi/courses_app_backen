<?php

namespace App\Http\Controllers;

use Aws\S3\S3Client;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class MediaController extends Controller
{
    /**
     * 🟢 إنشاء presigned URL لرفع الملفات
     */
    public function sign(Request $request)
    {
        // 1️⃣ Validate request
        $validator = Validator::make($request->all(), [
            'filename' => 'required|string',
            'content_type' => 'required|string|in:video/mp4,video/mpeg,video/quicktime',
            'filesize' => 'required|integer|max:524288000', // 500 MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $filename = $request->input('filename');
        $contentType = $request->input('content_type');

        // 2️⃣ Generate unique object key
        $objectKey = 'uploads/videos/' . Str::uuid() . '-' . $filename;

        try {
            // 3️⃣ Create S3 client
    $s3 = new S3Client([
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'version' => 'latest',
        'endpoint' => env('AWS_URL', 'http://127.0.0.1:9000'),
        'use_path_style_endpoint' => true, // هذا مهم جداً
        'credentials' => [
            'key' => env('AWS_ACCESS_KEY_ID', 'minioadmin'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'minioadmin'),
        ],
    ]);

            // 4️⃣ Generate presigned PUT URL (valid 1 hour)
            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => env('AWS_BUCKET'),
                'Key' => $objectKey,
                'ContentType' => $contentType,
            ]);

            $requestUrl = (string) $s3->createPresignedRequest($cmd, '+1 hour')->getUri();

            // 5️⃣ Return presigned details to client
            return response()->json([
                'url' => $requestUrl,
                'key' => $objectKey,
                'expires_in' => 3600,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to generate presigned URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🟡 تأكيد رفع الملف وتحديث الدرس
     */
    public function confirm(Request $request)
{
    $validator = Validator::make($request->all(), [
        'key' => 'required|string',
        'lesson_id' => 'required|uuid|exists:lessons,id',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    try {
        // Check if file exists
        if (!Storage::disk('s3')->exists($request->input('key'))) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Update lesson with S3 key
        $lesson = \App\Models\Lesson::findOrFail($request->input('lesson_id'));
        
        // Verify the lesson belongs to instructor's course
        $course = \App\Models\Course::where('id', $lesson->section->course_id)
            ->where('instructor_id', auth('sanctum')->id())
            ->first();

        if (!$course) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update lesson and start processing
        $lesson->update([
            's3_key' => $request->input('key'),
            'status' => 'processing'
        ]);

        // Dispatch the video processing job
        \App\Jobs\ProcessVideoJob::dispatch($lesson);

        return response()->json([
            'message' => 'Upload confirmed and video processing started',
            'lesson' => $lesson,
            'status' => 'processing'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Failed to confirm upload',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * 🔴 حذف ملف
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // حذف الملف
            $s3 = Storage::disk('s3');
            $s3->delete($request->input('key'));

            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete file',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}  



    /*

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Lesson;

class MediaController extends Controller
{
    /**
     * 🟢 رفع ملف مباشر إلى التخزين المحلي
     
    public function sign(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimetypes:video/mp4,video/mpeg,video/quicktime|max:512000', // 500MB
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate unique filename
        $file = $request->file('file');
        $filename = Str::uuid() . '-' . $file->getClientOriginalName();

        // Store locally (public disk)
        $path = $file->storeAs('uploads/videos', $filename, 'public');

        // Generate full URL
        $url = asset('storage/' . $path);

        return response()->json([
            'message' => 'File uploaded successfully',
            'path' => $path,
            'url' => $url,
        ], 201);
    }

    /**
     * 🟡 تأكيد ربط الفيديو مع درس معين
     
    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'lesson_id' => 'required|uuid|exists:lessons,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $lesson = Lesson::findOrFail($request->lesson_id);
        $lesson->update(['s3_key' => $request->path]); // بنستخدم نفس الحقل لتخزين المسار

        return response()->json([
            'message' => 'Upload confirmed successfully',
            'lesson' => $lesson,
        ]);
    }

    /**
     * 🔴 حذف ملف من التخزين المحلي
     
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (Storage::disk('public')->exists($request->path)) {
            Storage::disk('public')->delete($request->path);
            return response()->json(['message' => 'File deleted successfully']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
*/