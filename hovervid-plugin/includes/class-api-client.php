<?php
/**
 * API Client for HoverVid Plugin
 * Handles all communication with Laravel backend
 *
 * @package SLVP
 */

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * HoverVid API Client
 * Communicates with Laravel backend instead of direct database connection
 */
class SLVP_API_Client {
    
    /**
     * Singleton instance
     * @var SLVP_API_Client
     */
    private static $instance = null;
    
    /**
     * Laravel API base URL
     * @var string
     */
    private $api_base_url;
    
    /**
     * HTTP timeout for API calls
     * @var int
     */
    private $timeout = 10;
    
    /**
     * Get singleton instance
     *
     * @return SLVP_API_Client
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
        // Set API base URL (you can make this configurable)
        $this->api_base_url = $this->get_api_base_url();
    }
    
    /**
     * Get API base URL based on environment
     *
     * @return string
     */
    private function get_api_base_url() {
        // Check for WordPress constant first (can be set in wp-config.php)
        if (defined('HOVERVID_API_URL') && constant('HOVERVID_API_URL')) {
            return rtrim(constant('HOVERVID_API_URL'), '/') . '/api';
        }
        
        // Check for environment variable
        $env_url = getenv('HOVERVID_API_URL');
        if ($env_url) {
            return rtrim($env_url, '/') . '/api';
        }
        
        // Check if we're in local development
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        $http_host = $_SERVER['HTTP_HOST'] ?? '';
        
        // For local development, always use localhost:8000
        if (strpos($server_name, 'localhost') !== false || 
            strpos($server_name, '127.0.0.1') !== false ||
            strpos($server_name, '.local') !== false ||
            strpos($http_host, 'localhost') !== false ||
            strpos($http_host, '127.0.0.1') !== false ||
            strpos($http_host, '.local') !== false) {
            // Local development - Laravel should be running on localhost:8000
            return 'http://localhost:8000/api';
        }
        
        // If we can't determine the environment, try localhost:8000 as fallback for development
        // This is likely a development environment
        return 'http://localhost:8000/api';
    }
    
    /**
     * Verify domain with Laravel backend
     *
     * @param string $domain Domain to verify
     * @return array|false Verification result or false on failure
     */
    public function verify_domain($domain) {
        if (empty($domain)) {
            return false;
        }
        
        $endpoint = '/plugin/verify-domain';
        $data = ['domain' => $domain];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            // Log the API response for debugging
            error_log("HoverVid API Client: Domain verification response for {$domain}: " . json_encode($response));
            return $response;
        }
        
        return false;
    }
    
    /**
     * Get real-time domain status
     *
     * @param string $domain Domain to check
     * @return array|false Status result or false on failure
     */
    public function get_domain_status($domain) {
        if (empty($domain)) {
            return false;
        }
        
        $endpoint = '/plugin/domain-status';
        $url = $this->api_base_url . $endpoint . '?domain=' . urlencode($domain);
        
        $response = $this->make_api_request('GET', $endpoint, ['domain' => $domain]);
        
        if ($response && isset($response['success'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Update plugin status
     *
     * @param string $domain Domain
     * @param string $status Status to set
     * @return array|false Update result or false on failure
     */
    public function update_status($domain, $status = 'active') {
        if (empty($domain)) {
            return false;
        }
        
        $endpoint = '/plugin/update-status';
        $data = [
            'domain' => $domain,
            'status' => $status
        ];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Store fingerprint content data to Laravel backend
     *
     * @param string $domain Domain name
     * @param array $fingerprint_data Array of fingerprint data containing text, hash, and context
     * @return array|false Storage result or false on failure
     */
    public function store_fingerprint_content($domain, $fingerprint_data) {
        if (empty($domain) || empty($fingerprint_data)) {
            return false;
        }
        
        $endpoint = '/content'; // This matches the /api/content route in Laravel
        $data = [
            'domain_name' => $domain,
            'fingerprint_data' => $fingerprint_data
        ];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            // Log successful fingerprint storage
            error_log("HoverVid API Client: Fingerprint data stored successfully for {$domain}. Inserted: " . ($response['data']['inserted_count'] ?? 0) . ", Skipped: " . ($response['data']['skipped_count'] ?? 0));
            return $response;
        }
        
        return false;
    }
    
    /**
     * Check if video is available for content by hash
     *
     * @param string $domain Domain name
     * @param string $content_hash Content hash to check
     * @return array|false Result with video availability or false on failure
     */
    public function check_video_availability($domain, $content_hash) {
        if (empty($domain) || empty($content_hash)) {
            return false;
        }
        
        $endpoint = '/plugin/check-video';
        $data = [
            'domain_name' => $domain,
            'content_hash' => $content_hash
        ];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Get video URL for content by hash
     *
     * @param string $domain Domain name
     * @param string $content_hash Content hash
     * @return array|false Video data or false on failure
     */
    public function get_video_by_hash($domain, $content_hash) {
        if (empty($domain) || empty($content_hash)) {
            return false;
        }
        
        $endpoint = '/plugin/get-video';
        $data = [
            'domain_name' => $domain,
            'content_hash' => $content_hash
        ];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Batch check video availability for multiple content hashes
     *
     * @param string $domain Domain name
     * @param array $content_hashes Array of content hashes to check
     * @return array|false Batch result with video availability map or false on failure
     */
    public function batch_check_video_availability($domain, $content_hashes) {
        if (empty($domain) || empty($content_hashes)) {
            return false;
        }
        
        $endpoint = '/plugin/batch-check-videos';
        $data = [
            'domain_name' => $domain,
            'content_hashes' => $content_hashes
        ];
        
        $response = $this->make_api_request('POST', $endpoint, $data);
        
        if ($response && isset($response['success'])) {
            return $response;
        }
        
        return false;
    }
    
    /**
     * Make API request to Laravel backend
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data or false on failure
     */
    private function make_api_request($method, $endpoint, $data = []) {
        $url = $this->api_base_url . $endpoint;
        
        // Log the full URL being accessed for debugging
        error_log("HoverVid API Client: Making {$method} request to {$url}");
        
        // Prepare request arguments
        $args = [
            'method' => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'HoverVid-Plugin/1.0'
            ]
        ];
        
        // Add data based on method
        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = json_encode($data);
            error_log("HoverVid API Client: Request body: " . json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }
        
        // Make the request using WordPress HTTP API
        $response = wp_remote_request($url, $args);
        
        // Check for errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('HoverVid API Client Error: ' . $error_message);
            error_log('HoverVid API Client: Failed URL was: ' . $url);
            return false;
        }
        
        // Get response body
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        // Log response for debugging
        error_log("HoverVid API Client: Response code {$http_code}");
        if ($http_code >= 400) {
            error_log("HoverVid API Client: Error response body: {$body}");
        }
        
        // Check HTTP status
        if ($http_code >= 200 && $http_code < 300) {
            // Decode JSON response
            $decoded = json_decode($body, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            } else {
                error_log('HoverVid API Client: JSON decode error - ' . json_last_error_msg());
                error_log('HoverVid API Client: Raw response body: ' . $body);
                return false;
            }
        } else {
            error_log("HoverVid API Client: HTTP error {$http_code} for URL: {$url}");
            return false;
        }
    }
    
    /**
     * Test API connectivity
     *
     * @return bool True if API is reachable, false otherwise
     */
    public function test_connectivity() {
        // Test with a simple domain verification to check if API is working
        $test_domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $response = $this->make_api_request('POST', '/plugin/verify-domain', ['domain' => $test_domain]);
        
        // Check if we got a valid response structure
        return $response !== false && isset($response['success']);
    }
    
    /**
     * Get API base URL (for debugging)
     *
     * @return string
     */
    public function get_api_url() {
        return $this->api_base_url;
    }
    
    /**
     * Get detailed debug information for troubleshooting
     *
     * @return array Debug information
     */
    public function get_debug_info() {
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        
        return [
            'api_base_url' => $this->api_base_url,
            'current_domain' => $current_domain,
            'server_name' => $_SERVER['SERVER_NAME'] ?? '',
            'http_host' => $_SERVER['HTTP_HOST'] ?? '',
            'has_hovervid_constant' => defined('HOVERVID_API_URL'),
            'hovervid_constant_value' => defined('HOVERVID_API_URL') ? constant('HOVERVID_API_URL') : null,
            'env_hovervid_url' => getenv('HOVERVID_API_URL') ?: null,
            'connectivity_test' => $this->test_connectivity(),
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
        ];
    }
} 
