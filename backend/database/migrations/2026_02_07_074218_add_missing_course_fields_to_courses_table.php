<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (!Schema::hasColumn('courses', 'duration_hours')) {
                $table->integer('duration_hours')->nullable()->after('level');
            }
            if (!Schema::hasColumn('courses', 'lessons_count')) {
                $table->integer('lessons_count')->default(0)->after('duration_hours');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            if (Schema::hasColumn('courses', 'duration_hours')) {
                $table->dropColumn('duration_hours');
            }
            if (Schema::hasColumn('courses', 'lessons_count')) {
                $table->dropColumn('lessons_count');
            }
        });
    }
};
