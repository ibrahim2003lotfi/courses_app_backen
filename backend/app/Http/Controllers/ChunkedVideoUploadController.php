<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChunkedVideoUploadController extends Controller
{
    private $allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'm4v', 'webm'];
    private $chunkSize = 1024 * 1024; // 1MB chunks

    /**
     * Initialize chunked upload - create temp directory
     */
    public function initChunk(Request $request, $courseId, $lessonId)
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $uploadId = Str::uuid()->toString();
        $tempDir = storage_path("app/chunks/{$uploadId}");
        
        // Ensure chunks directory exists
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        error_log(">>> CHUNKED INIT: Upload ID: $uploadId for lesson $lessonId, tempDir: $tempDir");
        
        return response()->json([
            'success' => true,
            'upload_id' => $uploadId,
            'chunk_size' => $this->chunkSize,
        ]);
    }

    /**
     * Upload a single chunk
     */
    public function uploadChunk(Request $request, $uploadId, $chunkIndex)
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Authentication required'], 401);
        }

        try {
            if (!$request->hasFile('chunk')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No chunk data received'
                ], 422);
            }

            $chunk = $request->file('chunk');
            
            if (!$chunk->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid chunk: ' . $chunk->getErrorMessage()
                ], 422);
            }

            // Use direct file storage instead of Storage facade for reliability
            $tempDir = storage_path("app/chunks/{$uploadId}");
            
            // Ensure directory exists
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $chunkFile = $tempDir . "/chunk_{$chunkIndex}";
            
            // Move uploaded file directly
            if (!move_uploaded_file($chunk->getRealPath(), $chunkFile)) {
                // Fallback to copy if move fails
                file_put_contents($chunkFile, file_get_contents($chunk->getRealPath()));
            }
            
            error_log(">>> CHUNKED UPLOAD: Chunk $chunkIndex saved to $chunkFile");
            
            return response()->json([
                'success' => true,
                'chunk_index' => $chunkIndex,
                'received' => true
            ]);
            
        } catch (\Exception $e) {
            error_log(">>> CHUNKED UPLOAD ERROR: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Chunk upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Finalize - combine all chunks and save video
     */
    public function finalize(Request $request, $courseId, $lessonId)
    {
        $userId = $request->header('X-User-Id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Authentication required'], 401);
        }

        try {
            $uploadId = $request->input('upload_id');
            $filename = $request->input('filename', 'video.mp4');
            $totalChunks = $request->input('total_chunks', 0);
            
            if (!$uploadId || $totalChunks == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing upload_id or total_chunks'
                ], 422);
            }

            error_log(">>> CHUNKED FINALIZE: Combining $totalChunks chunks for upload $uploadId");

            $tempDir = storage_path("app/chunks/{$uploadId}");
            
            if (!is_dir($tempDir)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload session not found'
                ], 404);
            }

            // Verify all chunks exist
            $missingChunks = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . "/chunk_{$i}";
                if (!file_exists($chunkFile)) {
                    $missingChunks[] = $i;
                }
            }

            if (!empty($missingChunks)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing chunks: ' . implode(', ', $missingChunks),
                    'missing_chunks' => $missingChunks
                ], 422);
            }

            // Combine chunks
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($extension, $this->allowedExtensions)) {
                $extension = 'mp4';
            }
            
            $finalFilename = Str::uuid() . '.' . $extension;
            // Use courses/videos structure to match existing videos
            $directory = 'courses/videos';
            
            Storage::disk('public')->makeDirectory($directory);
            
            $finalPath = storage_path('app/public/' . $directory . '/' . $finalFilename);
            $outHandle = fopen($finalPath, 'wb');
            
            if (!$outHandle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot create final video file'
                ], 500);
            }

            $totalSize = 0;
            for ($i = 0; $i < $totalChunks; $i++) {
                $chunkFile = $tempDir . "/chunk_{$i}";
                $chunkData = file_get_contents($chunkFile);
                fwrite($outHandle, $chunkData);
                $totalSize += strlen($chunkData);
                unlink($chunkFile); // Delete chunk after combining
            }
            
            fclose($outHandle);
            rmdir($tempDir); // Remove temp directory

            // Get relative path for database
            $storagePath = 'public/courses/videos/' . $finalFilename;
            $videoUrl = url('api/videos/courses/videos/' . $finalFilename);

            // Update lesson in database
            DB::table('lessons')
                ->where('id', $lessonId)
                ->update([
                    'video_path' => $storagePath,
                    'video_size' => $totalSize,
                    'status' => 'compressed',
                    'updated_at' => now()
                ]);

            error_log(">>> CHUNKED FINALIZE SUCCESS: Video saved at $storagePath");

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully',
                'video_url' => $videoUrl,
                'file_size' => $this->formatBytes($totalSize)
            ]);

        } catch (\Exception $e) {
            error_log(">>> CHUNKED FINALIZE ERROR: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to finalize upload: ' . $e->getMessage()
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
