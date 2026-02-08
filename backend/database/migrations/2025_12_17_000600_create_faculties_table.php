<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('faculties', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('university_id');
            $table->string('name');
            $table->string('slug');
            $table->string('type')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('university_id')
                ->references('id')
                ->on('universities')
                ->onDelete('cascade');

            $table->unique(['university_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculties');
    }
};
















