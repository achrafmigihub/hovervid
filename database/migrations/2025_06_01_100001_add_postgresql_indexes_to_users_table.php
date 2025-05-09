<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add regular indexes for role and status columns
        DB::statement('CREATE INDEX IF NOT EXISTS users_role_index ON users(role)');
        DB::statement('CREATE INDEX IF NOT EXISTS users_status_index ON users(status)');
        
        // Add a trigram index for email to improve ILIKE performance
        DB::statement('CREATE INDEX IF NOT EXISTS users_email_trgm_idx ON users USING gin(email gin_trgm_ops)');
        
        // Add partial indexes for common query patterns
        // Index for active users only
        DB::statement('CREATE INDEX IF NOT EXISTS users_active_index ON users(id) WHERE status = \'active\'');
        
        // Index for admin users only
        DB::statement('CREATE INDEX IF NOT EXISTS users_admin_index ON users(id) WHERE role = \'admin\'');
        
        // Composite index for status and created_at for queries that filter by status and sort by created_at
        DB::statement('CREATE INDEX IF NOT EXISTS users_status_created_at_index ON users(status, created_at)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop all the added indexes
        DB::statement('DROP INDEX IF EXISTS users_role_index');
        DB::statement('DROP INDEX IF EXISTS users_status_index');
        DB::statement('DROP INDEX IF EXISTS users_email_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS users_active_index');
        DB::statement('DROP INDEX IF EXISTS users_admin_index');
        DB::statement('DROP INDEX IF EXISTS users_status_created_at_index');
    }
}; 