<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Lesson;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 1800;
    public $backoff = [60, 120, 300];

    protected $lesson;

    public function __construct(Lesson $lesson)
    {
        $this->lesson = $lesson;
    }

    public function handle()
    {
        Log::info('🎬 STARTING ProcessVideoJob for lesson: ' . $this->lesson->id . ' - ' . $this->lesson->title);
        
        $startTime = microtime(true);

        try {
            // STEP 1: Basic validation
            Log::info('🔍 STEP 1: Validating lesson data');
            if (empty($this->lesson->s3_key)) {
                throw new \Exception('Lesson s3_key is empty');
            }
            Log::info('✅ s3_key: ' . $this->lesson->s3_key);

            // STEP 2: Check file existence
            Log::info('🔍 STEP 2: Checking file existence in S3');
            $checkStart = microtime(true);
            $exists = Storage::disk('s3')->exists($this->lesson->s3_key);
            $checkTime = round((microtime(true) - $checkStart) * 1000, 2);
            Log::info('✅ File check took ' . $checkTime . 'ms - Exists: ' . ($exists ? 'YES' : 'NO'));
            
            if (!$exists) {
                throw new \Exception('File not found in S3: ' . $this->lesson->s3_key);
            }

            // STEP 3: Get file size
            $fileSize = Storage::disk('s3')->size($this->lesson->s3_key);
            Log::info('✅ File size: ' . $fileSize . ' bytes');
            
            if ($fileSize == 0) {
                throw new \Exception('File is empty (0 bytes)');
            }

            // STEP 4: Test FFmpeg
            Log::info('🔍 STEP 4: Testing FFmpeg');
            $ffmpegTest = new \Symfony\Component\Process\Process(['ffmpeg', '-version']);
            $ffmpegTest->run();
            
            if (!$ffmpegTest->isSuccessful()) {
                Log::error('FFmpeg test failed: ' . $ffmpegTest->getErrorOutput());
                throw new \Exception('FFmpeg not accessible: ' . $ffmpegTest->getErrorOutput());
            }
            Log::info('✅ FFmpeg is working');

            // STEP 5: Create directories
            Log::info('🔍 STEP 5: Creating temporary directories');
            $tempDir = storage_path('app/temp/' . $this->lesson->id);
            $hlsDir = storage_path('app/hls/' . $this->lesson->id);
            
            Log::info('📁 Temp dir: ' . $tempDir);
            Log::info('📁 HLS dir: ' . $hlsDir);
            
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
                Log::info('✅ Created temp directory');
            }
            if (!is_dir($hlsDir)) {
                mkdir($hlsDir, 0755, true);
                Log::info('✅ Created HLS directory');
            }

            $videoPath = $tempDir . '/original.mp4';
            Log::info('🎯 Target video path: ' . $videoPath);

            // STEP 6: Download video
            Log::info('🔍 STEP 6: Downloading video from S3');
            $this->downloadVideoFromS3($videoPath);
            
            // STEP 7: Generate thumbnail
            Log::info('🔍 STEP 7: Generating thumbnail');
            $thumbnailPath = $hlsDir . '/thumbnail.jpg';
            $this->generateThumbnail($videoPath, $thumbnailPath);
            // NEW: Verify thumbnail was created
        $this->verifyThumbnailExists($hlsDir);
            // STEP 8: Convert to HLS
            Log::info('🔍 STEP 8: Converting to HLS format');
            $this->convertToHLS($videoPath, $hlsDir);
            
            // STEP 9: Upload processed files
            Log::info('🔍 STEP 9: Uploading processed files to S3');
            $this->uploadProcessedFiles($hlsDir);
            
            // STEP 10: Update lesson
            Log::info('🔍 STEP 10: Updating lesson with HLS data');
            $this->updateLessonWithHLS();
            
            // STEP 11: Cleanup
            Log::info('🔍 STEP 11: Cleaning up temporary files');
            $this->cleanup($tempDir);

            $totalTime = round((microtime(true) - $startTime) / 60, 2);
            Log::info('🎉 SUCCESS: Video processing completed in ' . $totalTime . ' minutes for lesson: ' . $this->lesson->title);

        } catch (\Exception $e) {
            $totalTime = round((microtime(true) - $startTime) * 1000, 2);
            Log::error('💥 FAILED: ProcessVideoJob failed after ' . $totalTime . 'ms: ' . $e->getMessage());
            
            $this->lesson->update([
                'status' => 'failed',
                'processing_error' => $e->getMessage()
            ]);
            
            $this->fail($e);
        }
    }

    private function downloadVideoFromS3($destinationPath)
    {
        Log::info('📥 Downloading video from: ' . $this->lesson->s3_key);
        
        $fileContent = Storage::disk('s3')->get($this->lesson->s3_key);
        file_put_contents($destinationPath, $fileContent);
        
        // Verify download
        if (!file_exists($destinationPath)) {
            throw new \Exception('Download failed - file not created at: ' . $destinationPath);
        }
        
        $downloadedSize = filesize($destinationPath);
        Log::info('✅ Video downloaded: ' . $downloadedSize . ' bytes to ' . $destinationPath);
    }

    private function generateThumbnail($videoPath, $thumbnailPath)
{
    Log::info('🖼️ ULTRA-SIMPLE thumbnail generation');
    
    try {
        // Absolute simplest FFmpeg command
        $command = "ffmpeg -i \"{$videoPath}\" -ss 00:00:00.5 -vframes 1 -s 320x240 -y \"{$thumbnailPath}\" 2>&1";
        
        Log::info('🎯 Command: ' . $command);
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        // Check if file was created
        if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
            $size = filesize($thumbnailPath);
            Log::info('✅ Thumbnail created: ' . $size . ' bytes');
            return;
        }
        
        // If FFmpeg failed, create a basic text file as placeholder
        Log::warning('⚠️ FFmpeg failed, creating text placeholder');
        file_put_contents($thumbnailPath, "Thumbnail placeholder - video: " . basename($videoPath));
        Log::info('✅ Text placeholder created');
        
    } catch (\Exception $e) {
        Log::error('❌ Thumbnail error: ' . $e->getMessage());
        // Create basic placeholder even on exception
        try {
            file_put_contents($thumbnailPath, "Thumbnail error placeholder");
            Log::info('✅ Error placeholder created');
        } catch (\Exception $e2) {
            Log::error('💥 Even placeholder failed: ' . $e2->getMessage());
            // At this point, we just continue without thumbnail
        }
    }
}
private function createSimplePlaceholder($thumbnailPath)
{
    try {
        Log::info('🎯 Creating simple placeholder');
        
        $width = 320;
        $height = 240;
        
        $image = imagecreatetruecolor($width, $height);
        $backgroundColor = imagecolorallocate($image, 52, 152, 219); // Blue
        imagefill($image, 0, 0, $backgroundColor);
        
        // Save as JPEG
        imagejpeg($image, $thumbnailPath, 90);
        imagedestroy($image);
        
        Log::info('✅ Placeholder created');
        
    } catch (\Exception $e) {
        Log::error('❌ Placeholder failed: ' . $e->getMessage());
        // At this point, we've tried everything - just continue without thumbnail
    }
}

private function trySimpleThumbnail($videoPath, $thumbnailPath)
{
    try {
        Log::info('🎯 Attempt 1: Simple thumbnail with forced dimensions');
        
        $command = [
            'ffmpeg',
            '-i', $videoPath,
            '-ss', '00:00:00.5', // Slightly earlier timestamp
            '-vframes', '1',     // Capture 1 frame
            '-s', '320x240',     // Force standard dimensions (fixes aspect ratio issues)
            '-q:v', '2',         // Quality (2 = high quality, 31 = low quality)
            '-y',                // Overwrite output
            $thumbnailPath
        ];

        Log::info('🔧 Command: ' . implode(' ', $command));
        
        $process = new \Symfony\Component\Process\Process($command);
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful()) {
            if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
                $size = filesize($thumbnailPath);
                Log::info('✅ Thumbnail generated: ' . $size . ' bytes');
                return true;
            } else {
                Log::warning('❌ Thumbnail file created but empty');
            }
        } else {
            Log::warning('❌ FFmpeg failed: ' . $process->getErrorOutput());
        }
        
        return false;
        
    } catch (\Exception $e) {
        Log::warning('❌ Simple thumbnail error: ' . $e->getMessage());
        return false;
    }
}

private function tryPNGFormat($videoPath, $thumbnailPath)
{
    try {
        Log::info('🎯 Attempt 2: PNG format thumbnail');
        
        // Create PNG first, then convert to JPG
        $pngPath = $thumbnailPath . '.png';
        
        $command = [
            'ffmpeg',
            '-i', $videoPath,
            '-ss', '00:00:00.5',
            '-vframes', '1',
            '-s', '320x240',
            '-y',
            $pngPath
        ];

        $process = new \Symfony\Component\Process\Process($command);
        $process->setTimeout(60);
        $process->run();

        if ($process->isSuccessful() && file_exists($pngPath)) {
            // Convert PNG to JPG using GD
            $image = imagecreatefrompng($pngPath);
            if ($image) {
                // Create a white background for transparent PNGs
                $jpgImage = imagecreatetruecolor(imagesx($image), imagesy($image));
                $white = imagecolorallocate($jpgImage, 255, 255, 255);
                imagefill($jpgImage, 0, 0, $white);
                imagecopy($jpgImage, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
                
                imagejpeg($jpgImage, $thumbnailPath, 85);
                imagedestroy($image);
                imagedestroy($jpgImage);
                
                unlink($pngPath); // Clean up PNG
                
                if (file_exists($thumbnailPath)) {
                    Log::info('✅ PNG→JPG thumbnail generated');
                    return true;
                }
            }
            @unlink($pngPath);
        }
        
        return false;
        
    } catch (\Exception $e) {
        Log::warning('❌ PNG format error: ' . $e->getMessage());
        @unlink($pngPath ?? '');
        return false;
    }
}

private function createPlaceholderThumbnail($thumbnailPath)
{
    try {
        Log::info('🎯 Creating professional placeholder thumbnail');
        
        $width = 320;
        $height = 240;
        
        // Create image
        $image = imagecreatetruecolor($width, $height);
        
        // Colors
        $backgroundColor = imagecolorallocate($image, 52, 152, 219); // Nice blue
        $textColor = imagecolorallocate($image, 255, 255, 255);
        $accentColor = imagecolorallocate($image, 241, 196, 15); // Yellow
        
        // Fill background
        imagefill($image, 0, 0, $backgroundColor);
        
        // Add play icon (simple triangle)
        $centerX = $width / 2;
        $centerY = $height / 2;
        $triangleSize = 30;
        
        $points = [
            $centerX - $triangleSize/2, $centerY - $triangleSize/2,
            $centerX - $triangleSize/2, $centerY + $triangleSize/2, 
            $centerX + $triangleSize/2, $centerY
        ];
        imagefilledpolygon($image, $points, 3, $accentColor);
        
        // Add text
        $text = "Video Preview";
        $font = 5; // Built-in GD font
        $textWidth = imagefontwidth($font) * strlen($text);
        $textX = ($width - $textWidth) / 2;
        $textY = $height - 30;
        
        imagestring($image, $font, $textX, $textY, $text, $textColor);
        
        // Save as JPEG
        imagejpeg($image, $thumbnailPath, 90);
        imagedestroy($image);
        
        Log::info('✅ Professional placeholder created');
        return true;
        
    } catch (\Exception $e) {
        Log::error('❌ Placeholder creation failed: ' . $e->getMessage());
        return false;
    }
}

    private function convertToHLS($videoPath, $outputDir)
{
    Log::info('🔄 Converting video to HLS format');
    
    $playlistPath = $outputDir . '/playlist.m3u8';
    
    // Create segment directories
    for ($i = 0; $i < 3; $i++) {
        $segmentDir = $outputDir . '/v' . $i;
        if (!is_dir($segmentDir)) {
            mkdir($segmentDir, 0755, true);
        }
    }

    // Simplified HLS command
    $command = [
        'ffmpeg',
        '-i', $videoPath,
        '-preset', 'fast',
        '-profile:v', 'baseline', // Use baseline profile for better compatibility
        '-level', '3.0',
        '-start_number', '0',
        '-hls_time', '10',
        '-hls_list_size', '0',
        '-f', 'hls',
        $playlistPath
    ];

    Log::info('🎯 Simplified HLS command: ' . implode(' ', $command));
    
    $process = new \Symfony\Component\Process\Process($command);
    $process->setTimeout(1800);
    $process->run();

    if (!$process->isSuccessful()) {
        Log::error('❌ HLS conversion failed: ' . $process->getErrorOutput());
        throw new \Exception('HLS conversion failed: ' . $process->getErrorOutput());
    }

    if (!file_exists($playlistPath)) {
        throw new \Exception('HLS playlist not created: ' . $playlistPath);
    }

    Log::info('✅ HLS conversion completed: ' . $playlistPath);
}

   private function uploadProcessedFiles($hlsDir)
{
    Log::info('📤 UPLOAD: Starting file upload to S3');
    
    $files = glob($hlsDir . '/*');
    Log::info('📤 Found ' . count($files) . ' files to upload');
    
    foreach ($files as $file) {
        $filename = basename($file);
        $s3Path = 'hls/' . $this->lesson->id . '/' . $filename;
        
        try {
            if (is_file($file)) {
                Storage::disk('s3')->put($s3Path, file_get_contents($file));
                Log::info('✅ Uploaded: ' . $filename);
            }
        } catch (\Exception $e) {
            Log::error('❌ Failed to upload ' . $filename . ': ' . $e->getMessage());
        }
    }
    
    Log::info('📤 UPLOAD: Completed');
}


private function verifyThumbnailExists($hlsDir)
{
    $thumbnailPath = $hlsDir . '/thumbnail.jpg';
    
    if (!file_exists($thumbnailPath)) {
        Log::warning('⚠️ Thumbnail file not found at: ' . $thumbnailPath);
        Log::info('🔍 Checking what files exist in HLS directory:');
        
        $files = glob($hlsDir . '/*');
        foreach ($files as $file) {
            Log::info('📄 Found: ' . basename($file) . ' (' . filesize($file) . ' bytes)');
        }
        return false;
    }
    
    $size = filesize($thumbnailPath);
    Log::info('✅ Thumbnail verified: ' . $thumbnailPath . ' (' . $size . ' bytes)');
    return true;
}

    private function updateLessonWithHLS()
    {
        $this->lesson->update([
            'hls_manifest_url' => 'hls/' . $this->lesson->id . '/playlist.m3u8',
            'thumbnail_url' => 'hls/' . $this->lesson->id . '/thumbnail.jpg',
            'duration_seconds' => $this->getVideoDuration(),
            'processed_at' => now(),
            'status' => 'processed'
        ]);
        
        Log::info('✅ Lesson updated with HLS data');
    }

    private function getVideoDuration()
    {
        $tempVideoPath = storage_path('app/temp/' . $this->lesson->id . '/original.mp4');
        
        $command = [
            'ffprobe',
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $tempVideoPath
        ];

        $process = new \Symfony\Component\Process\Process($command);
        $process->run();

        if ($process->isSuccessful()) {
            return (int) floatval(trim($process->getOutput()));
        }

        return 0;
    }

    private function cleanup($tempDir)
    {
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) unlink($file);
            }
            rmdir($tempDir);
            Log::info('✅ Temporary files cleaned up');
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('💀 ProcessVideoJob completely failed: ' . $exception->getMessage());
    }
}