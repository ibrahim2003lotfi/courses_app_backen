<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->boolean('is_university_course')
                ->default(false)
                ->after('total_students');

            $table->uuid('university_id')
                ->nullable()
                ->after('is_university_course');

            $table->uuid('faculty_id')
                ->nullable()
                ->after('university_id');

            $table->foreign('university_id')
                ->references('id')
                ->on('universities')
                ->nullOnDelete();

            $table->foreign('faculty_id')
                ->references('id')
                ->on('faculties')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropForeign(['university_id']);
            $table->dropForeign(['faculty_id']);
            $table->dropColumn(['is_university_course', 'university_id', 'faculty_id']);
        });
    }
};
















