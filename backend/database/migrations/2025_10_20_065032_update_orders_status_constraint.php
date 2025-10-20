<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Drop the existing constraint
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        
        // Add new constraint with refund_requested status
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check 
                      CHECK (status IN ('pending', 'succeeded', 'failed', 'refunded', 'refund_requested'))");
    }

    public function down(): void
    {
        // Revert to original constraint if needed
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');
        DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check 
                      CHECK (status IN ('pending', 'succeeded', 'failed', 'refunded'))");
    }
};