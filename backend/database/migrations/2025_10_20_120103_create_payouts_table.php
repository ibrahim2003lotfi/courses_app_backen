<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('instructor_id');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('method')->default('manual'); // manual, stripe, bank_transfer
            $table->json('payment_details')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('instructor_id')->references('id')->on('users');
            $table->index(['status', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payouts');
    }
};