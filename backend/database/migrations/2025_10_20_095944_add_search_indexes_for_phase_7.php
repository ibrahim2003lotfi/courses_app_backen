<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable unaccent extension for better search
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        
        // Add full-text search indexes for courses
        DB::statement('CREATE INDEX IF NOT EXISTS courses_title_trgm_idx ON courses USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS courses_description_trgm_idx ON courses USING gin (description gin_trgm_ops)');
        
        // Add composite index for better performance
        DB::statement('CREATE INDEX IF NOT EXISTS courses_search_idx ON courses USING gin (to_tsvector(\'english\', title || \' \' || description))');
        
        // Add indexes for lessons
        DB::statement('CREATE INDEX IF NOT EXISTS lessons_title_trgm_idx ON lessons USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS lessons_description_trgm_idx ON lessons USING gin (description gin_trgm_ops) WHERE description IS NOT NULL');
        
        // Add indexes for users (instructors)
        DB::statement('CREATE INDEX IF NOT EXISTS users_name_trgm_idx ON users USING gin (name gin_trgm_ops)');
        
        // Add regular indexes for filtering
        Schema::table('courses', function (Blueprint $table) {
            $table->index(['level', 'created_at']);
            $table->index(['category_id', 'total_students']);
            $table->index(['price', 'rating']);
        });
    }

    public function down(): void
    {
        // Drop GIN indexes
        DB::statement('DROP INDEX IF EXISTS courses_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS courses_description_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS courses_search_idx');
        DB::statement('DROP INDEX IF EXISTS lessons_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS lessons_description_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS users_name_trgm_idx');
        
        // Drop regular indexes
        Schema::table('courses', function (Blueprint $table) {
            $table->dropIndex(['level', 'created_at']);
            $table->dropIndex(['category_id', 'total_students']);
            $table->dropIndex(['price', 'rating']);
        });
    }
};