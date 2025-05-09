<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable the pg_trgm extension for PostgreSQL
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // You might want to be cautious about dropping extensions, as they could be used by other applications
        // For a complete rollback, you can uncomment the line below:
        // DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
}; 