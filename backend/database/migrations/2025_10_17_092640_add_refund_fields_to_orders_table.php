<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('orders', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable();
            }
            if (!Schema::hasColumn('orders', 'refund_reason')) {
                $table->text('refund_reason')->nullable();
            }
            if (!Schema::hasColumn('orders', 'refund_amount')) {
                $table->decimal('refund_amount', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('orders', 'requested_refund_amount')) {
                $table->decimal('requested_refund_amount', 8, 2)->nullable();
            }
            if (!Schema::hasColumn('orders', 'refund_type')) {
                $table->string('refund_type')->nullable(); // auto_approved, manual_approved
            }
            if (!Schema::hasColumn('orders', 'admin_notes')) {
                $table->text('admin_notes')->nullable();
            }
        });
        
        // Enrollments table already has refunded_at from your previous migration
        // No need to add it again
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'refunded_at', 'refund_reason', 'refund_amount',
                'requested_refund_amount', 'refund_type', 'admin_notes'
            ]);
        });
    }
};