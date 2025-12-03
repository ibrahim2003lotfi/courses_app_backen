<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            // Education & Experience
            $table->string('education_level')->nullable()->after('user_id');
            $table->string('department')->nullable()->after('education_level');
            $table->string('specialization')->nullable()->after('department');
            $table->integer('years_of_experience')->default(0)->after('specialization');
            $table->text('experience_description')->nullable()->after('years_of_experience');
            
            // Social Links
            $table->string('linkedin_url')->nullable()->after('experience_description');
            $table->string('portfolio_url')->nullable()->after('linkedin_url');
            
            // Certificates stored as JSON array
            $table->json('certificates')->nullable()->after('portfolio_url');
            
            // Terms acceptance
            $table->boolean('agreed_to_terms')->default(false)->after('certificates');
            $table->timestamp('terms_agreed_at')->nullable()->after('agreed_to_terms');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_applications', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};