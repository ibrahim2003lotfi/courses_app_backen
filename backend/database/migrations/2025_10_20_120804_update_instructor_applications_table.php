<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            $table->json('additional_info')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->foreign('reviewed_by')->references('id')->on('users');
        });

        // Add the index conditionally using raw SQL
        DB::statement('CREATE INDEX IF NOT EXISTS instructor_applications_status_created_at_index ON instructor_applications (status, created_at)');
    }

    public function down()
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['additional_info', 'reviewed_at', 'reviewed_by', 'review_notes']);
        });

        // Drop the index in down method
        DB::statement('DROP INDEX IF EXISTS instructor_applications_status_created_at_index');
    }
};