<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddOnboardingColumnsSeeder extends Seeder
{
    public function run(): void
    {
        // Check if columns exist before adding
        $columns = Schema::getColumnListing('users');
        
        if (!in_array('onboarding_status', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN onboarding_status VARCHAR(255) NULL");
            echo "Added onboarding_status column\n";
        }
        
        if (!in_array('interests', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN interests TEXT NULL");
            echo "Added interests column\n";
        }
        
        if (!in_array('onboarding_completed_at', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN onboarding_completed_at TIMESTAMP NULL");
            echo "Added onboarding_completed_at column\n";
        }
        
        if (!in_array('university', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN university VARCHAR(255) NULL");
            echo "Added university column\n";
        }
        
        if (!in_array('major', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN major VARCHAR(255) NULL");
            echo "Added major column\n";
        }
        
        if (!in_array('graduation_year', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN graduation_year INTEGER NULL");
            echo "Added graduation_year column\n";
        }
        
        if (!in_array('interests_updated_at', $columns)) {
            DB::statement("ALTER TABLE users ADD COLUMN interests_updated_at TIMESTAMP NULL");
            echo "Added interests_updated_at column\n";
        }
        
        echo "All onboarding columns added successfully!\n";
    }
}
