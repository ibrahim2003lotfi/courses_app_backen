<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Add duration and lessons count columns if they don't exist
            if (!Schema::hasColumn('courses', 'duration_hours')) {
                $table->integer('duration_hours')->nullable()->after('level');
            }
            if (!Schema::hasColumn('courses', 'lessons_count')) {
                $table->integer('lessons_count')->default(0)->after('duration_hours');
            }
            // Add other useful columns
            if (!Schema::hasColumn('courses', 'course_image_url')) {
                $table->string('course_image_url')->nullable()->after('lessons_count');
            }
            if (!Schema::hasColumn('courses', 'is_university_course')) {
                $table->boolean('is_university_course')->default(false)->after('course_image_url');
            }
            if (!Schema::hasColumn('courses', 'university_id')) {
                $table->uuid('university_id')->nullable()->after('is_university_course');
            }
            if (!Schema::hasColumn('courses', 'faculty_id')) {
                $table->uuid('faculty_id')->nullable()->after('university_id');
            }
        });

        // Add foreign key constraints if tables exist
        try {
            Schema::table('courses', function (Blueprint $table) {
                if (Schema::hasTable('universities') && !Schema::hasColumn('courses', 'university_id_foreign')) {
                    $table->foreign('university_id')->references('id')->on('universities')->nullOnDelete();
                }
                if (Schema::hasTable('faculties') && !Schema::hasColumn('courses', 'faculty_id_foreign')) {
                    $table->foreign('faculty_id')->references('id')->on('faculties')->nullOnDelete();
                }
            });
        } catch (\Exception $e) {
            // Foreign keys may fail if tables don't exist, that's ok
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $columns = [
                'duration_hours',
                'lessons_count',
                'course_image_url',
                'is_university_course',
                'university_id',
                'faculty_id',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('courses', $column)) {
                    try {
                        $table->dropColumn($column);
                    } catch (\Exception $e) {
                        // Column might not exist
                    }
                }
            }
        });
    }
};
