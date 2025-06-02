<?php
/**
 * API Handler Class
 * This handles all the AJAX requests for my video player
 * 
 * @package SLVP
 * @uses add_action WordPress action hook registration
 * @uses check_ajax_referer WordPress security verification
 * @uses wp_send_json_error WordPress JSON error response
 * @uses wp_send_json_success WordPress JSON success response
 */

if (!defined('ABSPATH')) {
    exit; // Security check - no direct access
}

/**
 * API Handler for the Sign Language Video Player
 * 
 * Manages AJAX requests and responses for video content
 */
class SLVP_API_Handler {
    /**
     * Constructor - sets up WordPress action hooks
     *
     * @uses add_action WordPress action hook registration
     */
    public function __construct() {
        // Hook into WordPress AJAX - both for logged in and guest users
        add_action('wp_ajax_slvp_get_video', [$this, 'handle_video_request']);
        add_action('wp_ajax_nopriv_slvp_get_video', [$this, 'handle_video_request']);
        
        // Add endpoint for domain status check
        add_action('wp_ajax_slvp_check_domain', [$this, 'handle_domain_check']);
        add_action('wp_ajax_nopriv_slvp_check_domain', [$this, 'handle_domain_check']);
        
        // Add endpoint for storing fingerprint content
        add_action('wp_ajax_slvp_store_fingerprints', [$this, 'handle_fingerprint_storage']);
        add_action('wp_ajax_nopriv_slvp_store_fingerprints', [$this, 'handle_fingerprint_storage']);
    }

    /**
     *  Initialization method
     */
    public function init() {
        // Keeping this for future setup needs
    }

    /**
     * Main AJAX handler for video requests
     * 
     * @uses check_ajax_referer WordPress security verification function
     * @uses wp_send_json_error WordPress function to return error JSON response
     * @uses wp_send_json_success WordPress function to return success JSON response
     */
    public function handle_video_request() {
        // Double check security with nonce
        check_ajax_referer('slvp_nonce', 'security');

        if (!isset($_POST['text_hash'])) {
            wp_send_json_error(['message' => 'Missing text hash']);
            return;
        }

        // For now, just returning a test video - will update this later
        wp_send_json_success([
            'video_url' => 'https://www.w3schools.com/html/mov_bbb.mp4'
        ]);
    }
    
    /**
     * Handle domain status check via AJAX
     */
    public function handle_domain_check() {
        // Verify nonce for security
        if (!check_ajax_referer('slvp_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        try {
            // Get centralized verifier instance
            $verifier = SLVP_Domain_Verifier::get_instance();
            
            // Force refresh to get latest status from database
            $verifier->refresh_verification();
            
            // Get verification status
            $status = $verifier->get_verification_status();
            
            // Return status to JavaScript
            wp_send_json_success([
                'is_active' => $verifier->is_domain_verified(),
                'domain_exists' => $verifier->domain_exists(),
                'message' => $verifier->get_message(),
                'domain' => $verifier->get_current_domain(),
                'timestamp' => date('Y-m-d H:i:s'),
                'debug' => $status
            ]);
            
        } catch (Exception $e) {
            error_log('HoverVid AJAX Domain Check Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'System error occurred']);
        }
    }
    
    /**
     * Handle fingerprint content storage via AJAX
     */
    public function handle_fingerprint_storage() {
        // Verify nonce for security
        if (!check_ajax_referer('slvp_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        // Check if fingerprint data is provided
        if (!isset($_POST['fingerprint_data']) || empty($_POST['fingerprint_data'])) {
            wp_send_json_error(['message' => 'Missing fingerprint data']);
            return;
        }
        
        try {
            // Get current domain
            $current_domain = $_SERVER['HTTP_HOST'] ?? '';
            
            if (empty($current_domain)) {
                wp_send_json_error(['message' => 'Could not determine domain']);
                return;
            }
            
            // Decode fingerprint data
            $fingerprint_data = json_decode(stripslashes($_POST['fingerprint_data']), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(['message' => 'Invalid fingerprint data format']);
                return;
            }
            
            // Validate fingerprint data structure
            if (!is_array($fingerprint_data)) {
                wp_send_json_error(['message' => 'Fingerprint data must be an array']);
                return;
            }
            
            // Validate each fingerprint item
            $validated_data = [];
            foreach ($fingerprint_data as $item) {
                if (!isset($item['text']) || !isset($item['hash']) || !isset($item['context'])) {
                    continue; // Skip invalid items
                }
                
                $validated_data[] = [
                    'text' => sanitize_text_field($item['text']),
                    'hash' => sanitize_text_field($item['hash']),
                    'context' => sanitize_text_field($item['context']),
                    'page_name' => sanitize_text_field($item['page_name'] ?? 'Unknown Page')
                ];
            }
            
            if (empty($validated_data)) {
                wp_send_json_error(['message' => 'No valid fingerprint data found']);
                return;
            }
            
            // Get API client instance
            $api_client = SLVP_API_Client::get_instance();
            
            // Store fingerprint data via API
            $result = $api_client->store_fingerprint_content($current_domain, $validated_data);
            
            if ($result) {
                wp_send_json_success([
                    'message' => 'Fingerprint data stored successfully',
                    'data' => $result['data'] ?? [],
                    'total_sent' => count($validated_data)
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to store fingerprint data']);
            }
            
        } catch (Exception $e) {
            error_log('HoverVid Fingerprint Storage Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'System error occurred while storing fingerprints']);
        }
    }
} 
