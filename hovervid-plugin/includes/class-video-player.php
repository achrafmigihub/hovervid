<?php
/**
 * My main plugin class that handles all the core functionality
 * This manages the video player, assets, and rendering
 *
 * @package SLVP
 */

// Include WordPress function stubs for IDE support
if (!function_exists('add_action')) {
    require_once dirname(__FILE__) . '/wp-stubs.php';
}

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * Sign Language Video Player main class
 * 
 * Handles core functionality including asset loading and player rendering
 */
class SLVP_Video_Player {
    
    /**
     * Domain verifier instance
     * 
     * @var SLVP_Domain_Verifier
     */
    private $domain_verifier;
    
    /**
     * Constructor - initializes components and sets up WordPress hooks
     *
     * @uses add_action() WordPress action hook registration
     */
    public function __construct() {
        // First load my helper classes
        require_once SLVP_PLUGIN_PATH . 'includes/class-text-processor.php'; // My text processing class
        require_once SLVP_PLUGIN_PATH . 'includes/class-api-handler.php';    // My API handling class
        require_once SLVP_PLUGIN_PATH . 'includes/class-domain-verifier.php'; // Domain verification system
        
        // Initialize domain verifier (single source of truth)
        $this->domain_verifier = SLVP_Domain_Verifier::get_instance();
        
        // Only initialize components if domain is verified
        if ($this->domain_verifier->should_plugin_work()) {
            error_log('HoverVid: Domain verified - initializing plugin components');
            new SLVP_Text_Processor(); // Initialize text processing
        } else {
            error_log('HoverVid: Domain not verified - text processing disabled');
        }
        
        // Always initialize API handler (needed for domain status checks)
        new SLVP_API_Handler();
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']); // Load my styles and scripts
        add_action('wp_footer', [$this, 'render_player']);           // Add my player HTML
        add_action('init', [$this, 'add_cors_headers']);            // Add CORS headers
    }
    
    /**
     * Load all my frontend assets - both CSS and JS files
     *
     * @uses wp_enqueue_style() WordPress function to register and enqueue stylesheets
     * @uses wp_enqueue_script() WordPress function to register and enqueue scripts
     * @uses wp_localize_script() WordPress function to add data to scripts
     * @uses admin_url() WordPress function to get admin URL
     * @uses wp_create_nonce() WordPress function to create security token
     */
    public function enqueue_assets() {
        // My styling
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'slvp-player-style',                          
                SLVP_PLUGIN_URL . 'public/css/public-style.css', 
                [],                                          
                filemtime(SLVP_PLUGIN_PATH . 'public/css/public-style.css') // Using file time for cache busting
            );
            
            // Add popup styles
            wp_enqueue_style(
                'slvp-popup-style',                          
                SLVP_PLUGIN_URL . 'public/css/popup.css', 
                [],                                          
                filemtime(SLVP_PLUGIN_PATH . 'public/css/popup.css') // Using file time for cache busting
            );
        }
        
        // My main JS functionality
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'slvp-player-script',                        
                SLVP_PLUGIN_URL . 'public/js/public-script.js', 
                ['jquery'],                                 // Need jQuery for some features
                filemtime(SLVP_PLUGIN_PATH . 'public/js/public-script.js'), 
                true                                        // Footer loading for better performance
            );
            
            // Add popup functionality
            wp_enqueue_script(
                'slvp-login-popup-script',                        
                SLVP_PLUGIN_URL . 'public/js/login-popup.js', 
                ['jquery'],                                 // Need jQuery for some features
                filemtime(SLVP_PLUGIN_PATH . 'public/js/login-popup.js'), 
                true                                        // Footer loading for better performance
            );
        }
        
        // Get domain activation status from verifier
        $is_domain_active = $this->domain_verifier->is_domain_verified();
        $is_forced_active = false; // No more forced active logic
        $domain_exists = $this->domain_verifier->domain_exists();
        $license_message = $this->domain_verifier->get_message();
        $current_domain = $this->domain_verifier->get_current_domain();
        
        // Debug log what we're passing to JavaScript
        error_log('HoverVid Debug: Passing to JS - is_verified: ' . ($is_domain_active ? 'true' : 'false'));
        error_log('HoverVid Debug: Passing to JS - domain_exists: ' . ($domain_exists ? 'true' : 'false'));
        error_log('HoverVid Debug: Current domain: ' . $current_domain);
        
        // Setup my JS variables
        if (function_exists('wp_localize_script')) {
            $admin_ajax_url = function_exists('admin_url') ? admin_url('admin-ajax.php') : '';
            $nonce = function_exists('wp_create_nonce') ? wp_create_nonce('slvp_nonce') : '';
            
            // Get Laravel API base URL from API client
            $api_client = SLVP_API_Client::get_instance();
            $api_base_url = str_replace('/api', '', $api_client->get_api_url()); // Remove /api suffix for frontend URLs
            
            // Set up JS variables using wp_localize_script
            if (function_exists('wp_localize_script')) {
                wp_localize_script(
                    'slvp-player-script',      
                    'slvp_vars',               
                    [
                        'ajax_url' => $admin_ajax_url,
                        'ajax_nonce' => $nonce,
                        'plugin_url' => SLVP_PLUGIN_URL,
                        'is_domain_active' => $is_domain_active ? '1' : '0', // Convert to string for JS comparison
                        'is_forced_active' => $is_forced_active ? '1' : '0', // Convert to string for JS comparison
                        'domain_exists' => $domain_exists ? '1' : '0', // Convert to string for JS comparison
                        'license_message' => $license_message,
                        'domain' => $current_domain,
                        'api_base_url' => $api_base_url,
                        'login_url' => $api_base_url . '/login',
                        'signup_url' => $api_base_url . '/register'
                    ]
                );
                
                // Add debug console.log directly for troubleshooting
                $debug_script = "console.log('PHP Debug - Domain: {$current_domain}, Active: " . ($is_domain_active ? 'true' : 'false') . ", Forced: " . ($is_forced_active ? 'true' : 'false') . ", Exists: " . ($domain_exists ? 'true' : 'false') . "');";
                wp_add_inline_script('slvp-player-script', $debug_script, 'before');
            }
        }

        // Adding Font Awesome for my icons
        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
                [],
                '6.4.0'
            );
        }
    }
    
    /**
     * Renders my video player HTML structure in the footer
     */
    public function render_player() {
        // Get domain activation status from verifier
        $is_domain_active = $this->domain_verifier->is_domain_verified();
        $domain_exists = $this->domain_verifier->domain_exists();
        $license_message = $this->domain_verifier->get_message();
        $current_domain = $this->domain_verifier->get_current_domain();
        
        // Debug log what we're using for rendering
        error_log('HoverVid Debug: Rendering - Domain exists: ' . ($domain_exists ? 'true' : 'false'));
        error_log('HoverVid Debug: Rendering - is_verified: ' . ($is_domain_active ? 'true' : 'false'));
        error_log('HoverVid Debug: Rendering - License message: ' . $license_message);
        
        ?>
        <!-- Video player container -->
        <div id="slvp-player-container">
            <!-- Header section -->
            <div class="slvp-player-header">
                <div class="slvp-player-logo">
                    <img src="<?php echo SLVP_PLUGIN_URL . 'assets/hovervid-logo.svg'; ?>" alt="HoverVid Logo">
                </div>
                <div class="slvp-player-controls">
                    <button class="slvp-control-btn slvp-close-btn" title="Close">×</button>
                    <button class="slvp-control-btn slvp-plus-btn" title="Enlarge">+</button>
                    <button class="slvp-control-btn slvp-minus-btn" title="Shrink">-</button>
                </div>
            </div>
            <!-- Video container -->
            <div class="slvp-video-frame">
                <video id="slvp-video-player" controls playsinline>
                    Your browser does not support the video tag.
                </video>
            </div>
        </div>
        
        <!-- Toggle button with simple disabled state -->
        <button class="slvp-toggle-button<?php echo !$is_domain_active ? ' slvp-inactive' : ''; ?>" 
                <?php if (!$is_domain_active): ?>disabled="disabled"<?php endif; ?>
                data-is-active="<?php echo $is_domain_active ? 'true' : 'false'; ?>"
                title="<?php echo !$is_domain_active ? esc_attr($license_message) : 'Toggle HoverVid Player'; ?>">
            <img src="<?php echo SLVP_PLUGIN_URL . 'assets/hovervid-icon.svg'; ?>" alt="Toggle Player">
        </button>
        
        <?php if (!$is_domain_active): ?>
        <!-- Simple disabled styling -->
        <style>
            .slvp-toggle-button.slvp-inactive {
                opacity: 0.5 !important;
                cursor: not-allowed !important;
                pointer-events: auto !important;
            }
            .slvp-toggle-button.slvp-inactive:hover {
                transform: none !important;
                box-shadow: none !important;
            }
        </style>
        <?php endif; ?>
        <?php
    }

    /**
     * Add CORS headers for font loading
     *
     * @uses is_admin() WordPress function to check if in admin area
     */
    public function add_cors_headers() {
        if (!function_exists('is_admin') || !is_admin()) {
            // Only add headers if not already sent
            if (!headers_sent()) {
                header('Access-Control-Allow-Origin: *');
                header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
            }
        }
    }
}
