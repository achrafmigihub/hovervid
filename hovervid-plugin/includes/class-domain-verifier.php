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
     * This is the ONLY method that checks via Laravel API
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
            
            // Use API client instead of direct database connection
            $api_client = SLVP_API_Client::get_instance();
            
            // Test API connectivity first
            if (!$api_client->test_connectivity()) {
                error_log("HoverVid Domain Verifier: Laravel API unavailable - domain {$this->current_domain} disabled");
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'Plugin backend service is unavailable. Please check your connection or contact support.',
                    'error' => true,
                    'api_unavailable' => true
                ];
                return;
            }
            
            // Get domain verification from Laravel API
            $api_response = $api_client->verify_domain($this->current_domain);
            
            if (!$api_response) {
                // API call failed
                error_log("HoverVid Domain Verifier: API call failed for domain {$this->current_domain}");
                $this->verification_status = [
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'Unable to verify domain status. Please try again later.',
                    'error' => true,
                    'api_error' => true
                ];
                return;
            }
            
            // Parse API response
            $is_verified = $api_response['is_verified'] ?? false;
            $domain_exists = $api_response['domain_exists'] ?? false;
            $message = $api_response['message'] ?? 'Unknown status';
            
            $this->verification_status = [
                'is_verified' => (bool)$is_verified,
                'domain_exists' => (bool)$domain_exists,
                'message' => $message,
                'error' => $api_response['error'] ?? false,
                'api_response' => $api_response
            ];
            
            // Log the verification result
            $status_text = $this->verification_status['is_verified'] ? 'VERIFIED' : 'NOT VERIFIED';
            $exists_text = $this->verification_status['domain_exists'] ? 'EXISTS' : 'NOT FOUND';
            error_log("HoverVid Domain Verifier (API): {$this->current_domain} - {$status_text} ({$exists_text})");
            
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
 