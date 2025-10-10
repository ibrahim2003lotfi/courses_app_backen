<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('lessons', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('section_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('s3_key')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->boolean('is_preview')->default(false);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('section_id')->references('id')->on('sections')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('lessons');
    }
};
