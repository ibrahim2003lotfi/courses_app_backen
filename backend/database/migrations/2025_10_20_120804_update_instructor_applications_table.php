<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['additional_info', 'reviewed_at', 'reviewed_by', 'review_notes']);
        });
    }
};