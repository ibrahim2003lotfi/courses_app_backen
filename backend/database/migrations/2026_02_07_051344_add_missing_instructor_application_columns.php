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
        Schema::table('instructor_applications', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('instructor_applications', 'education_level')) {
                $table->string('education_level')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('instructor_applications', 'department')) {
                $table->string('department')->nullable()->after('education_level');
            }
            if (!Schema::hasColumn('instructor_applications', 'specialization')) {
                $table->string('specialization')->nullable()->after('department');
            }
            if (!Schema::hasColumn('instructor_applications', 'years_of_experience')) {
                $table->integer('years_of_experience')->default(0)->after('specialization');
            }
            if (!Schema::hasColumn('instructor_applications', 'experience_description')) {
                $table->text('experience_description')->nullable()->after('years_of_experience');
            }
            if (!Schema::hasColumn('instructor_applications', 'linkedin_url')) {
                $table->string('linkedin_url')->nullable()->after('experience_description');
            }
            if (!Schema::hasColumn('instructor_applications', 'portfolio_url')) {
                $table->string('portfolio_url')->nullable()->after('linkedin_url');
            }
            if (!Schema::hasColumn('instructor_applications', 'certificates')) {
                $table->json('certificates')->nullable()->after('portfolio_url');
            }
            if (!Schema::hasColumn('instructor_applications', 'agreed_to_terms')) {
                $table->boolean('agreed_to_terms')->default(false)->after('certificates');
            }
            if (!Schema::hasColumn('instructor_applications', 'terms_agreed_at')) {
                $table->timestamp('terms_agreed_at')->nullable()->after('agreed_to_terms');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            $columns = [
                'education_level',
                'department',
                'specialization',
                'years_of_experience',
                'experience_description',
                'linkedin_url',
                'portfolio_url',
                'certificates',
                'agreed_to_terms',
                'terms_agreed_at',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('instructor_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
