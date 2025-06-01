<?php
/**
 * Domain Verifier Class - Single Source of Truth for Domain Verification
 *
 * This class handles ALL domain verification logic in one place
 * No other class should check domain status directly
 *
 * @package SLVP
 */

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * Centralized Domain Verification System
 * 
 * This is the ONLY class that should check is_verified column
 * All other classes must use this class for domain verification
 */
class SLVP_Domain_Verifier {
    
    /**
     * Singleton instance
     * @var SLVP_Domain_Verifier
     */
    private static $instance = null;
    
    /**
     * Current domain verification status
     * @var array|null
     */
    private $verification_status = null;
    
    /**
     * Current domain
     * @var string
     */
    private $current_domain;
    
    /**
     * Get singleton instance
     *
     * @return SLVP_Domain_Verifier
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private constructor for singleton
     */
    private function __construct() {
        $this->current_domain = $_SERVER['HTTP_HOST'] ?? '';
        $this->check_domain_verification();
    }
    
    /**
     * Check domain verification status (SINGLE SOURCE OF TRUTH)
     * This is the ONLY method that checks the database
     *
     * @return void
     */
    private function check_domain_verification() {
        try {
            if (empty($this->current_domain)) {
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'No domain detected',
                    'error' => true
                ];
                return;
            }
            
            // Check if database is unavailable
            if (defined('HOVERVID_DB_UNAVAILABLE') && HOVERVID_DB_UNAVAILABLE) {
                error_log("HoverVid Domain Verifier: Database unavailable - domain {$this->current_domain} disabled");
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'Plugin database connection unavailable. Please check configuration.',
                    'error' => true,
                    'database_unavailable' => true
                ];
                return;
            }
            
            // Get database instance with error handling
            try {
                $db = HoverVid_Database::get_instance();
            } catch (Exception $db_error) {
                error_log('HoverVid Domain Verifier: Database connection failed - ' . $db_error->getMessage());
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'Plugin database connection failed. Please check configuration.',
                    'error' => true,
                    'database_error' => true
                ];
                return;
            }
            
            // Get domain status from database
            $domain_data = $db->check_domain_status($this->current_domain);
            
            // Parse the result
            if (!$domain_data) {
                // Domain not found in database
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'This domain is not authorized to use the HoverVid plugin. Please contact the plugin provider.',
                    'error' => false
                ];
            } else {
                // Domain exists - check is_verified status
                $is_verified = isset($domain_data['is_active']) ? (bool)$domain_data['is_active'] : false;
                
                $this->verification_status = [
                    'is_verified' => $is_verified,
                    'domain_exists' => true,
                    'message' => $is_verified ? 
                        'Domain is verified and active.' : 
                        'Your subscription or license has expired. Please contact support to renew your access.',
                    'error' => false,
                    'raw_data' => $domain_data
                ];
            }
            
            // Log the verification result
            $status_text = $this->verification_status['is_verified'] ? 'VERIFIED' : 'NOT VERIFIED';
            $exists_text = $this->verification_status['domain_exists'] ? 'EXISTS' : 'NOT FOUND';
            error_log("HoverVid Domain Verifier: {$this->current_domain} - {$status_text} ({$exists_text})");
            
        } catch (Exception $e) {
            error_log('HoverVid Domain Verifier Error: ' . $e->getMessage());
            $this->verification_status = [
                'is_verified' => false,
                'domain_exists' => false,
                'message' => 'System error occurred. Please contact the plugin provider.',
                'error' => true
            ];
        }
    }
    
    /**
     * Get current domain verification status
     *
     * @return array Verification status array
     */
    public function get_verification_status() {
        return $this->verification_status;
    }
    
    /**
     * Check if current domain is verified (main method for other classes to use)
     *
     * @return bool True if domain is verified, false otherwise
     */
    public function is_domain_verified() {
        return $this->verification_status['is_verified'] ?? false;
    }
    
    /**
     * Check if domain exists in database
     *
     * @return bool True if domain exists, false otherwise
     */
    public function domain_exists() {
        return $this->verification_status['domain_exists'] ?? false;
    }
    
    /**
     * Get verification message for UI display
     *
     * @return string Message to show to user
     */
    public function get_message() {
        return $this->verification_status['message'] ?? 'Unknown status';
    }
    
    /**
     * Get current domain
     *
     * @return string Current domain
     */
    public function get_current_domain() {
        return $this->current_domain;
    }
    
    /**
     * Check if there was an error during verification
     *
     * @return bool True if error occurred, false otherwise
     */
    public function has_error() {
        return $this->verification_status['error'] ?? true;
    }
    
    /**
     * Force refresh verification status (for real-time updates)
     *
     * @return void
     */
    public function refresh_verification() {
        $this->verification_status = null;
        $this->check_domain_verification();
    }
    
    /**
     * Get verification data for JavaScript
     *
     * @return array Data to pass to JavaScript
     */
    public function get_js_data() {
        return [
            'is_domain_active' => $this->is_domain_verified(),
            'domain_exists' => $this->domain_exists(),
            'license_message' => $this->get_message(),
            'domain' => $this->get_current_domain(),
            'has_error' => $this->has_error()
        ];
    }
    
    /**
     * MASTER METHOD: Should plugin functionality be enabled?
     * This is the single method that determines if plugin should work
     *
     * @return bool True if plugin should work, false if disabled
     */
    public function should_plugin_work() {
        // Plugin only works if domain is verified
        return $this->is_domain_verified() && $this->domain_exists() && !$this->has_error();
    }
} 
