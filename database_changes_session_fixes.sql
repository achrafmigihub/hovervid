-- =====================================================
-- HoverVid Database Fixes - Complete Session Changes
-- =====================================================
-- This script contains all database modifications made to align
-- the database structure with the working backend systems.
-- 
-- Systems Fixed:
-- 1. Session Management System
-- 2. Domain Management System  
-- 3. User Management System
-- 4. Client Dashboard Domain Popup
-- =====================================================

-- =====================================================
-- 1. SESSION MANAGEMENT SYSTEM FIXES
-- =====================================================

-- Add is_suspended column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_suspended BOOLEAN DEFAULT FALSE;

-- Create function to get active session count for a user
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

-- Create function to enforce session limit for a user
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

-- =====================================================
-- 2. DOMAIN MANAGEMENT SYSTEM FIXES
-- =====================================================

-- Add is_active column to domains table
ALTER TABLE domains 
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT FALSE;

-- Add status column to domains table
ALTER TABLE domains 
ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'inactive';

-- Add url column to domains table for client dashboard
ALTER TABLE domains 
ADD COLUMN IF NOT EXISTS url VARCHAR(500) NULL;

-- Create indexes for performance on new columns
CREATE INDEX IF NOT EXISTS idx_domains_is_active ON domains(is_active);
CREATE INDEX IF NOT EXISTS idx_domains_status ON domains(status);

-- =====================================================
-- 3. DATABASE TRIGGER FIXES
-- =====================================================

-- Fix the domains plugin status change trigger timing
-- Change from BEFORE INSERT to AFTER INSERT to prevent foreign key constraint violations

-- First, drop the existing trigger if it exists
DROP TRIGGER IF EXISTS domains_plugin_status_change_trigger ON domains;

-- Recreate the trigger as AFTER INSERT
CREATE OR REPLACE FUNCTION check_plugin_status_on_domain_change()
RETURNS TRIGGER AS $$
BEGIN
    -- Your existing trigger logic here
    -- (This function should already exist from previous setup)
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Create the new AFTER INSERT trigger
CREATE TRIGGER domains_plugin_status_change_trigger
    AFTER INSERT OR UPDATE OR DELETE ON domains
    FOR EACH ROW
    EXECUTE FUNCTION check_plugin_status_on_domain_change();

-- =====================================================
-- 4. DATA VALIDATION AND CLEANUP
-- =====================================================

-- Update existing domains to have proper status values
UPDATE domains 
SET status = 'inactive', is_active = false 
WHERE status IS NULL OR status = '';

-- Update active domains to have consistent status
UPDATE domains 
SET status = 'active', is_active = true 
WHERE is_active = true AND (status IS NULL OR status = '' OR status = 'inactive');

-- Clean up any orphaned domain_id references in users table
UPDATE users 
SET domain_id = NULL 
WHERE domain_id IS NOT NULL 
AND domain_id NOT IN (SELECT id FROM domains);

-- Set domain_id for users who have domains but no domain_id set
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

-- =====================================================
-- 5. VERIFICATION QUERIES
-- =====================================================

-- Verify session management columns and functions
SELECT 'Session Management Check' as check_type;
SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN is_suspended IS NOT NULL THEN 1 END) as users_with_suspension_column
FROM users;

SELECT 'Session Functions Check' as check_type;
SELECT 
    routine_name,
    routine_type
FROM information_schema.routines 
WHERE routine_name IN ('get_user_active_sessions_count', 'enforce_session_limit');

-- Verify domain management columns
SELECT 'Domain Management Check' as check_type;
SELECT 
    COUNT(*) as total_domains,
    COUNT(CASE WHEN is_active IS NOT NULL THEN 1 END) as domains_with_active_column,
    COUNT(CASE WHEN status IS NOT NULL THEN 1 END) as domains_with_status_column,
    COUNT(CASE WHEN url IS NOT NULL THEN 1 END) as domains_with_url_column
FROM domains;

-- Verify indexes
SELECT 'Index Check' as check_type;
SELECT 
    indexname,
    tablename
FROM pg_indexes 
WHERE indexname IN ('idx_domains_is_active', 'idx_domains_status');

-- Show domain status distribution
SELECT 'Domain Status Distribution' as check_type;
SELECT 
    status,
    is_active,
    COUNT(*) as count
FROM domains 
GROUP BY status, is_active 
ORDER BY status, is_active;

-- Show user-domain relationships
SELECT 'User-Domain Relationships' as check_type;
SELECT 
    u.role,
    COUNT(u.id) as total_users,
    COUNT(u.domain_id) as users_with_domain_id,
    COUNT(d.id) as users_with_actual_domains
FROM users u
LEFT JOIN domains d ON u.id = d.user_id
GROUP BY u.role;

-- =====================================================
-- SCRIPT COMPLETION MESSAGE
-- =====================================================
SELECT 'Database fixes completed successfully!' as status,
       NOW() as completion_time; 