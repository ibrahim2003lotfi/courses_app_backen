<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('user_id');
            $table->uuid('course_id');
            $table->string('provider');
            $table->string('provider_payment_id')->nullable();
            $table->decimal('amount', 8, 2);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'refunded'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');

            // Add to orders table
$table->timestamp('refunded_at')->nullable();
$table->text('refund_reason')->nullable();
$table->decimal('refund_amount', 8, 2)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('orders');
    }
};
