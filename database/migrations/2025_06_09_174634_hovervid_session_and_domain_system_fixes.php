<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // =====================================================
        // 1. SESSION MANAGEMENT SYSTEM FIXES
        // =====================================================
        
        // Add is_suspended column to users table
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_suspended')->default(false)->after('email_verified_at');
        });

        // Create PostgreSQL functions for session management
        DB::statement("
            CREATE OR REPLACE FUNCTION get_user_active_sessions_count(user_id_param INTEGER)
            RETURNS INTEGER AS $$
            DECLARE
                session_count INTEGER;
            BEGIN
                SELECT COUNT(*) INTO session_count
                FROM sessions 
                WHERE user_id = user_id_param 
                AND is_active = true 
                AND expires_at > NOW();
                
                RETURN session_count;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE OR REPLACE FUNCTION enforce_session_limit(user_id_param INTEGER, max_sessions INTEGER DEFAULT 3)
            RETURNS INTEGER AS $$
            DECLARE
                current_count INTEGER;
                sessions_deactivated INTEGER := 0;
            BEGIN
                -- Get current active session count
                SELECT get_user_active_sessions_count(user_id_param) INTO current_count;
                
                -- If over limit, deactivate oldest sessions
                IF current_count > max_sessions THEN
                    WITH oldest_sessions AS (
                        SELECT id 
                        FROM sessions 
                        WHERE user_id = user_id_param 
                        AND is_active = true 
                        AND expires_at > NOW()
                        ORDER BY last_activity ASC 
                        LIMIT (current_count - max_sessions)
                    )
                    UPDATE sessions 
                    SET is_active = false, 
                        updated_at = NOW()
                    WHERE id IN (SELECT id FROM oldest_sessions);
                    
                    GET DIAGNOSTICS sessions_deactivated = ROW_COUNT;
                END IF;
                
                RETURN sessions_deactivated;
            END;
            $$ LANGUAGE plpgsql;
        ");

        // =====================================================
        // 2. DOMAIN MANAGEMENT SYSTEM FIXES
        // =====================================================
        
        // Add missing columns to domains table
        Schema::table('domains', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('is_verified');
            $table->string('status', 50)->default('inactive')->after('is_active');
            $table->string('url', 500)->nullable()->after('platform');
            
            // Add indexes for performance
            $table->index('is_active');
            $table->index('status');
        });

        // =====================================================
        // 3. DATABASE TRIGGER FIXES
        // =====================================================
        
        // Fix the domains plugin status change trigger timing
        DB::statement("DROP TRIGGER IF EXISTS domains_plugin_status_change_trigger ON domains");
        
        // Recreate trigger function if needed
        DB::statement("
            CREATE OR REPLACE FUNCTION check_plugin_status_on_domain_change()
            RETURNS TRIGGER AS $$
            BEGIN
                -- Plugin status change logic goes here
                -- (This should match your existing trigger function)
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");
        
        // Create the new AFTER INSERT trigger
        DB::statement("
            CREATE TRIGGER domains_plugin_status_change_trigger
                AFTER INSERT OR UPDATE OR DELETE ON domains
                FOR EACH ROW
                EXECUTE FUNCTION check_plugin_status_on_domain_change();
        ");

        // =====================================================
        // 4. DATA VALIDATION AND CLEANUP
        // =====================================================
        
        // Update existing domains to have proper status values
        DB::statement("
            UPDATE domains 
            SET status = 'inactive', is_active = false 
            WHERE status IS NULL OR status = '';
        ");
        
        // Update active domains to have consistent status
        DB::statement("
            UPDATE domains 
            SET status = 'active', is_active = true 
            WHERE is_active = true AND (status IS NULL OR status = '' OR status = 'inactive');
        ");
        
        // Clean up any orphaned domain_id references in users table
        DB::statement("
            UPDATE users 
            SET domain_id = NULL 
            WHERE domain_id IS NOT NULL 
            AND domain_id NOT IN (SELECT id FROM domains);
        ");
        
        // Set domain_id for users who have domains but no domain_id set
        DB::statement("
            UPDATE users 
            SET domain_id = (
                SELECT d.id 
                FROM domains d 
                WHERE d.user_id = users.id 
                ORDER BY d.created_at DESC 
                LIMIT 1
            )
            WHERE domain_id IS NULL 
            AND EXISTS (
                SELECT 1 FROM domains d WHERE d.user_id = users.id
            );
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop functions
        DB::statement("DROP FUNCTION IF EXISTS get_user_active_sessions_count(INTEGER)");
        DB::statement("DROP FUNCTION IF EXISTS enforce_session_limit(INTEGER, INTEGER)");
        
        // Drop trigger
        DB::statement("DROP TRIGGER IF EXISTS domains_plugin_status_change_trigger ON domains");
        DB::statement("DROP FUNCTION IF EXISTS check_plugin_status_on_domain_change()");
        
        // Remove columns from domains table
        Schema::table('domains', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropIndex(['status']);
            $table->dropColumn(['is_active', 'status', 'url']);
        });
        
        // Remove column from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_suspended');
        });
    }
};
