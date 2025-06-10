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
        
        // Add endpoint for checking video availability
        add_action('wp_ajax_slvp_check_video_availability', [$this, 'handle_video_availability_check']);
        add_action('wp_ajax_nopriv_slvp_check_video_availability', [$this, 'handle_video_availability_check']);
        
        // Add endpoint for batch checking video availability
        add_action('wp_ajax_slvp_batch_check_videos', [$this, 'handle_batch_video_check']);
        add_action('wp_ajax_nopriv_slvp_batch_check_videos', [$this, 'handle_batch_video_check']);
        
        // Add debug endpoint to test API connectivity
        add_action('wp_ajax_slvp_debug_api', [$this, 'handle_debug_api']);
        add_action('wp_ajax_nopriv_slvp_debug_api', [$this, 'handle_debug_api']);
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

        $content_hash = sanitize_text_field($_POST['text_hash']);
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($current_domain)) {
            wp_send_json_error(['message' => 'Could not determine domain']);
            return;
        }

        try {
            // Get API client instance
            $api_client = SLVP_API_Client::get_instance();
            
            // Get video by content hash from Laravel backend
            $result = $api_client->get_video_by_hash($current_domain, $content_hash);
            
            if ($result && $result['success']) {
                // Check if video_url exists in the response
                if (isset($result['data']['video_url']) && !empty($result['data']['video_url'])) {
                    wp_send_json_success([
                        'video_url' => $result['data']['video_url'],
                        'content_id' => $result['data']['content_id'] ?? null,
                        'message' => 'Video found and loaded successfully'
                    ]);
                } else {
                    wp_send_json_error(['message' => 'No video available for this content']);
                }
            } else {
                $error_message = $result['message'] ?? 'Video not found';
                wp_send_json_error(['message' => $error_message]);
            }
            
        } catch (Exception $e) {
            error_log('HoverVid Video Request Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'System error occurred while fetching video']);
        }
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
    
    /**
     * Handle video availability check via AJAX
     */
    public function handle_video_availability_check() {
        // Verify nonce for security
        if (!check_ajax_referer('slvp_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        if (!isset($_POST['content_hash'])) {
            wp_send_json_error(['message' => 'Missing content hash']);
            return;
        }
        
        $content_hash = sanitize_text_field($_POST['content_hash']);
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($current_domain)) {
            wp_send_json_error(['message' => 'Could not determine domain']);
            return;
        }
        
        try {
            // Get API client instance
            $api_client = SLVP_API_Client::get_instance();
            
            // Check video availability
            $result = $api_client->check_video_availability($current_domain, $content_hash);
            
            if ($result) {
                wp_send_json_success([
                    'has_video' => $result['data']['has_video'] ?? false,
                    'content_hash' => $content_hash
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to check video availability']);
            }
            
        } catch (Exception $e) {
            error_log('HoverVid Video Availability Check Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'System error occurred while checking video availability']);
        }
    }
    
    /**
     * Handle batch video availability check via AJAX
     */
    public function handle_batch_video_check() {
        // Verify nonce for security
        if (!check_ajax_referer('slvp_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        if (!isset($_POST['content_hashes']) || empty($_POST['content_hashes'])) {
            wp_send_json_error(['message' => 'Missing content hashes']);
            return;
        }
        
        // Decode content hashes
        $content_hashes = json_decode(stripslashes($_POST['content_hashes']), true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($content_hashes)) {
            wp_send_json_error(['message' => 'Invalid content hashes format']);
            return;
        }
        
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        if (empty($current_domain)) {
            wp_send_json_error(['message' => 'Could not determine domain']);
            return;
        }
        
        try {
            // Get API client instance
            $api_client = SLVP_API_Client::get_instance();
            
            // Batch check video availability
            $result = $api_client->batch_check_video_availability($current_domain, $content_hashes);
            
            if ($result && $result['success']) {
                wp_send_json_success([
                    'video_availability' => $result['data']['video_availability'] ?? [],
                    'total_checked' => count($content_hashes)
                ]);
            } else {
                wp_send_json_error(['message' => 'Failed to batch check video availability']);
            }
            
        } catch (Exception $e) {
            error_log('HoverVid Batch Video Check Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'System error occurred while batch checking videos']);
        }
    }
    
    /**
     * Debug API connectivity and configuration
     */
    public function handle_debug_api() {
        // Verify nonce for security
        if (!check_ajax_referer('slvp_nonce', 'security', false)) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        try {
            // Get API client instance and debug info
            $api_client = SLVP_API_Client::get_instance();
            $debug_info = $api_client->get_debug_info();
            
            // Test connectivity
            $connectivity_test = $api_client->test_connectivity();
            
            // Get current domain
            $current_domain = $_SERVER['HTTP_HOST'] ?? '';
            
            wp_send_json_success([
                'message' => 'Debug information collected',
                'debug_info' => $debug_info,
                'connectivity_test' => $connectivity_test,
                'current_domain' => $current_domain,
                'wordpress_ajax_working' => true
            ]);
            
        } catch (Exception $e) {
            error_log('HoverVid Debug API Error: ' . $e->getMessage());
            wp_send_json_error([
                'message' => 'Debug API error: ' . $e->getMessage(),
                'wordpress_ajax_working' => true
            ]);
        }
    }
} 
