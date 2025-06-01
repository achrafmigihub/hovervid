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
        // Create a function to update user status based on session activity
        DB::unprepared('
            CREATE OR REPLACE FUNCTION update_user_status_from_session()
            RETURNS TRIGGER AS $$
            BEGIN
                -- If user_id is not null, update the user status
                IF NEW.user_id IS NOT NULL THEN
                    -- Update user status to active if session is active
                    IF NEW.is_active = true THEN
                        UPDATE users SET status = \'active\' WHERE id = NEW.user_id;
                    ELSE
                        -- Check if user has any other active sessions
                        IF NOT EXISTS (
                            SELECT 1 FROM sessions 
                            WHERE user_id = NEW.user_id 
                            AND is_active = true 
                            AND id != NEW.id
                        ) THEN
                            UPDATE users SET status = \'inactive\' WHERE id = NEW.user_id;
                        END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger on sessions table
        DB::unprepared('
            CREATE TRIGGER update_user_status_trigger
            AFTER INSERT OR UPDATE OF is_active
            ON sessions
            FOR EACH ROW
            EXECUTE FUNCTION update_user_status_from_session();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the trigger and function
        DB::unprepared('DROP TRIGGER IF EXISTS update_user_status_trigger ON sessions;');
        DB::unprepared('DROP FUNCTION IF EXISTS update_user_status_from_session();');
    }
}; 
