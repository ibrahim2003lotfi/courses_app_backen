<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('hls_manifest_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default('pending'); // pending, processing, processed, failed
            $table->text('processing_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn([
                'hls_manifest_url',
                'thumbnail_url', 
                'processed_at',
                'status',
                'processing_error'
            ]);
        });
    }
};