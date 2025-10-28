<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This adds verification fields to the users table
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add phone verification timestamp
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            
            // Add verification code (6-digit code)
            $table->string('verification_code', 6)->nullable()->after('phone');
            
            // Add when the code expires (15 minutes from creation)
            $table->timestamp('verification_code_expires_at')->nullable()->after('verification_code');
            
            // Track which method user chose for verification
            $table->enum('verification_method', ['email', 'phone'])->nullable()->after('verification_code_expires_at');
            
            // Track if user is fully verified
            $table->boolean('is_verified')->default(false)->after('verification_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified_at',
                'verification_code',
                'verification_code_expires_at',
                'verification_method',
                'is_verified'
            ]);
        });
    }
};