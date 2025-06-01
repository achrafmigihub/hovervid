<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // Add fingerprint column for enhanced security tracking
            if (!Schema::hasColumn('sessions', 'fingerprint')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->string('fingerprint')->nullable()->after('user_agent');
                });
                Log::info('Added fingerprint column to sessions table');
            }
            
            // Add indexes for faster lookups
            if (!$this->hasIndex('sessions', 'sessions_expires_at_index')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->index('expires_at');
                });
                Log::info('Added expires_at index to sessions table');
            }
            
            if (!$this->hasIndex('sessions', 'sessions_is_active_index')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->index('is_active');
                });
                Log::info('Added is_active index to sessions table');
            }
        } catch (\Exception $e) {
            Log::error('Error optimizing sessions table: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Drop fingerprint column
            if (Schema::hasColumn('sessions', 'fingerprint')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->dropColumn('fingerprint');
                });
            }
            
            // Drop indexes
            if ($this->hasIndex('sessions', 'sessions_expires_at_index')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->dropIndex(['expires_at']);
                });
            }
            
            if ($this->hasIndex('sessions', 'sessions_is_active_index')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->dropIndex(['is_active']);
                });
            }
        } catch (\Exception $e) {
            Log::error('Error rolling back session table optimizations: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if the given index exists for PostgreSQL
     */
    protected function hasIndex($table, $index)
    {
        try {
            // For PostgreSQL, use a direct query to check if index exists
            $result = DB::select("
                SELECT COUNT(*) as count 
                FROM pg_indexes 
                WHERE tablename = ? AND indexname = ?
            ", [$table, $index]);
            
            return $result[0]->count > 0;
        } catch (\Exception $e) {
            Log::error('Error checking index existence: ' . $e->getMessage());
            return false;
        }
    }
};
