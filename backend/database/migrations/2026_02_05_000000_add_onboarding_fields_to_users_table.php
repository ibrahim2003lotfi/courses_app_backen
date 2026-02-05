<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_status')->nullable()->after('role');
            $table->string('university')->nullable()->after('onboarding_status');
            $table->string('major')->nullable()->after('university');
            $table->integer('graduation_year')->nullable()->after('major');
            $table->text('interests')->nullable()->after('graduation_year');
            $table->timestamp('onboarding_completed_at')->nullable()->after('interests');
            $table->timestamp('interests_updated_at')->nullable()->after('onboarding_completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'onboarding_status',
                'university',
                'major',
                'graduation_year',
                'interests',
                'onboarding_completed_at',
                'interests_updated_at',
            ]);
        });
    }
};
