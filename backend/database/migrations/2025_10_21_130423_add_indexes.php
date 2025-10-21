<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPerformanceIndexes extends Migration
{
    public function up()
    {
        Schema::table('courses', function (Blueprint $table) {
            // Indexes for search and filtering
            $table->index(['level', 'price']); // For course filtering
            $table->index(['total_students']); // For popularity sorting
            $table->index(['created_at']);     // For newest sorting
            $table->index(['category_id', 'level']); // Combined filtering
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->index(['section_id', 'position']); // For ordered lesson retrieval
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->index(['course_id', 'position']); // For ordered section retrieval
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'status']); // For user payment history
            $table->index(['created_at']);         // For payment reports
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['user_id', 'course_id']); // For enrollment checks
        });
    }

    public function down()
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['level', 'price']);
            $table->dropIndex(['total_students']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category_id', 'level']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['section_id', 'position']);
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'position']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'course_id']);
        });
    }
}