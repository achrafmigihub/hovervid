<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the plugin_status_logs table
        Schema::create('plugin_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained('domains')->onDelete('cascade');
            $table->string('old_status')->nullable(); // Can be null for initial records
            $table->string('new_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('change_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
            
            // Indexes for performance
            $table->index('domain_id');
            $table->index('new_status');
            $table->index('changed_by');
            $table->index('created_at');
            $table->index(['domain_id', 'created_at']);
        });

        // Convert old_status and new_status columns to use ENUM type
        DB::statement("
            ALTER TABLE plugin_status_logs 
            ALTER COLUMN old_status TYPE plugin_status_enum USING old_status::plugin_status_enum,
            ALTER COLUMN new_status TYPE plugin_status_enum USING new_status::plugin_status_enum;
        ");

        // Add comments
        DB::statement("COMMENT ON TABLE plugin_status_logs IS 'Audit log for plugin status changes';");
        DB::statement("COMMENT ON COLUMN plugin_status_logs.domain_id IS 'Foreign key to domains table';");
        DB::statement("COMMENT ON COLUMN plugin_status_logs.old_status IS 'Previous plugin status (null for initial records)';");
        DB::statement("COMMENT ON COLUMN plugin_status_logs.new_status IS 'New plugin status';");
        DB::statement("COMMENT ON COLUMN plugin_status_logs.changed_by IS 'User who made the change (null for system changes)';");
        DB::statement("COMMENT ON COLUMN plugin_status_logs.change_reason IS 'Optional reason for the status change';");

        // Create the trigger function for plugin status management
        DB::statement("
            CREATE OR REPLACE FUNCTION handle_plugin_status_change() 
            RETURNS TRIGGER AS \$\$
            DECLARE
                user_id INTEGER := NULL;
                reason TEXT := NULL;
            BEGIN
                -- Only proceed if plugin_status actually changed
                IF OLD IS NULL OR OLD.plugin_status IS DISTINCT FROM NEW.plugin_status THEN
                    
                    -- Update the updated_at timestamp
                    NEW.updated_at := CURRENT_TIMESTAMP;
                    
                    -- Validate status transition (only if this is an update, not insert)
                    IF OLD IS NOT NULL AND OLD.plugin_status IS NOT NULL THEN
                        IF NOT validate_plugin_status_transition(OLD.plugin_status, NEW.plugin_status) THEN
                            RAISE EXCEPTION 'Invalid plugin status transition from % to %. Check valid transitions in validate_plugin_status_transition function.', 
                                OLD.plugin_status, NEW.plugin_status
                                USING ERRCODE = '23514', -- Check constraint violation
                                      DETAIL = 'Plugin status transitions must follow business rules',
                                      HINT = 'Use the validate_plugin_status_transition function to check valid transitions';
                        END IF;
                    END IF;
                    
                    -- Try to get user_id and reason from session variables if they exist
                    BEGIN
                        user_id := current_setting('app.current_user_id', true)::INTEGER;
                    EXCEPTION WHEN OTHERS THEN
                        user_id := NULL;
                    END;
                    
                    BEGIN
                        reason := current_setting('app.status_change_reason', true);
                        IF reason = '' THEN
                            reason := NULL;
                        END IF;
                    EXCEPTION WHEN OTHERS THEN
                        reason := NULL;
                    END;
                    
                    -- Log the status change
                    INSERT INTO plugin_status_logs (
                        domain_id,
                        old_status,
                        new_status,
                        changed_by,
                        change_reason,
                        created_at,
                        updated_at
                    ) VALUES (
                        NEW.id,
                        CASE WHEN OLD IS NULL THEN NULL ELSE OLD.plugin_status END,
                        NEW.plugin_status,
                        user_id,
                        reason,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    );
                    
                    -- Clear the session variables
                    BEGIN
                        PERFORM set_config('app.current_user_id', '', false);
                        PERFORM set_config('app.status_change_reason', '', false);
                    EXCEPTION WHEN OTHERS THEN
                        -- Ignore errors when clearing session variables
                    END;
                    
                END IF;
                
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Add comment to the trigger function
        DB::statement("
            COMMENT ON FUNCTION handle_plugin_status_change() 
            IS 'Trigger function to handle plugin status changes: validates transitions, updates timestamps, and logs changes';
        ");

        // Create the trigger
        DB::statement("
            CREATE TRIGGER domains_plugin_status_change_trigger
                BEFORE INSERT OR UPDATE OF plugin_status ON domains
                FOR EACH ROW
                EXECUTE FUNCTION handle_plugin_status_change();
        ");

        // Create helper functions for setting session variables
        DB::statement("
            CREATE OR REPLACE FUNCTION set_plugin_status_change_context(
                p_user_id INTEGER DEFAULT NULL,
                p_reason TEXT DEFAULT NULL
            ) RETURNS VOID AS \$\$
            BEGIN
                IF p_user_id IS NOT NULL THEN
                    PERFORM set_config('app.current_user_id', p_user_id::TEXT, false);
                END IF;
                
                IF p_reason IS NOT NULL THEN
                    PERFORM set_config('app.status_change_reason', p_reason, false);
                END IF;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            COMMENT ON FUNCTION set_plugin_status_change_context(INTEGER, TEXT) 
            IS 'Helper function to set context for plugin status changes (user_id and reason)';
        ");

        // Create function to get plugin status history for a domain
        DB::statement("
            CREATE OR REPLACE FUNCTION get_plugin_status_history(
                p_domain_id INTEGER,
                p_limit INTEGER DEFAULT 50
            ) RETURNS TABLE (
                id BIGINT,
                old_status plugin_status_enum,
                new_status plugin_status_enum,
                changed_by INTEGER,
                change_reason TEXT,
                created_at TIMESTAMP
            ) AS \$\$
            BEGIN
                RETURN QUERY
                SELECT 
                    psl.id,
                    psl.old_status,
                    psl.new_status,
                    psl.changed_by,
                    psl.change_reason,
                    psl.created_at
                FROM plugin_status_logs psl
                WHERE psl.domain_id = p_domain_id
                ORDER BY psl.created_at DESC
                LIMIT p_limit;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            COMMENT ON FUNCTION get_plugin_status_history(INTEGER, INTEGER) 
            IS 'Get plugin status change history for a specific domain';
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger
        DB::statement("DROP TRIGGER IF EXISTS domains_plugin_status_change_trigger ON domains;");
        
        // Drop the functions
        DB::statement("DROP FUNCTION IF EXISTS handle_plugin_status_change();");
        DB::statement("DROP FUNCTION IF EXISTS set_plugin_status_change_context(INTEGER, TEXT);");
        DB::statement("DROP FUNCTION IF EXISTS get_plugin_status_history(INTEGER, INTEGER);");
        
        // Drop the table
        Schema::dropIfExists('plugin_status_logs');
    }
}; 
