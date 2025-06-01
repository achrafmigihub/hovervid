<?php
/**
 * Plugin Name: Sign Language Video Player
 * Plugin URI: 
 * Description: A WordPress plugin that adds a sign language video player for website content translation.
 * Version: 1.0.0
 *
 * @package SLVP
 */

// Include WordPress function stubs for IDE support - this file will be ignored when running in WordPress
if (!function_exists('add_action')) {
    require_once __DIR__ . '/includes/wp-stubs.php';
}

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * Define plugin constants
 * 
 * @uses plugin_dir_path() WordPress function to get the absolute path to the plugin directory
 * @uses plugin_dir_url() WordPress function to get the URL to the plugin directory
 */
define('SLVP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SLVP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load required classes
require_once SLVP_PLUGIN_PATH . 'includes/class-database.php';
require_once SLVP_PLUGIN_PATH . 'includes/class-domain-verifier.php';
require_once SLVP_PLUGIN_PATH . 'includes/class-video-player.php';

/**
 * Check if the current domain is authorized
 * 
 * @return array Domain status information
 */
function slvp_check_domain_authorization() {
    $current_domain = $_SERVER['HTTP_HOST'] ?? '';
    
    // Development domains are always authorized
    $force_active_domains = [
        'sign-language-video-plugin.local' => true,
        'localhost' => true,
        // Add other development domains as needed
    ];
    
    if (isset($force_active_domains[$current_domain])) {
        return [
            'is_active' => true,
            'message' => 'Development domain - always active',
            'forced' => true,
            'domain_exists' => true
        ];
    }
    
    // Check with database
    try {
        $db = HoverVid_Database::get_instance();
        $domain_status = $db->check_domain_status($current_domain);
        
        // If we received a status, return it
        if ($domain_status) {
            return $domain_status;
        }
        
        // Fallback message if no status is returned
        return [
            'is_active' => false,
            'message' => 'This domain is not authorized to use the HoverVid plugin.',
            'domain_exists' => false
        ];
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        error_log('HoverVid Authorization Check Error: ' . $error_message);
        
        // Handle database connection errors separately
        if (strpos($error_message, 'Database connection failed') !== false) {
            return [
                'is_active' => false,
                'message' => 'Database connection failed. Please check your plugin configuration.',
                'domain_exists' => false,
                'db_connection_error' => true
            ];
        }
        
        return [
            'is_active' => false,
            'message' => 'This domain is not authorized to use the HoverVid plugin.',
            'domain_exists' => false
        ];
    }
}

/**
 * Plugin activation hook - Check if the domain is authorized
 */
function slvp_activate_plugin() {
    $domain_status = slvp_check_domain_authorization();
    $current_domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    // If domain doesn't exist in database, prevent activation
    if (!isset($domain_status['domain_exists']) || !$domain_status['domain_exists']) {
        // Store the error message
        $message = "Domain '{$current_domain}' is not authorized to use the HoverVid plugin. Please contact the plugin provider to authorize your domain.";
        
        // Log the error
        error_log('HoverVid Plugin Activation Error: ' . $message);
        
        // Set a transient flag for the admin notice
        set_transient('hovervid_activation_error', $message, 300);
        
        // This is crucial - we're setting a flag that our plugin will check later
        update_option('hovervid_needs_deactivation', 'yes');
        
        // Plugin will be silently deactivated by slvp_handle_unauthorized_domain
        // The "Plugin activated" message will be hidden by slvp_hide_plugin_activated_message
    }
    // If domain exists but is not verified, allow activation but show inactive state
    else if (isset($domain_status['domain_exists']) && $domain_status['domain_exists'] && 
             (!isset($domain_status['is_active']) || !$domain_status['is_active'])) {
        // Domain exists but is not verified - plugin will be active but non-functional
        error_log("HoverVid Plugin: Domain '{$current_domain}' exists but is not verified. Plugin will be inactive until verification.");
    }
}
register_activation_hook(__FILE__, 'slvp_activate_plugin');

/**
 * Display activation error message and handle its dismissal
 */
function slvp_activation_error_notice() {
    // Standard WordPress error transient
    if ($message = get_transient('hovervid_activation_error')) {
        ?>
        <div class="notice notice-error is-dismissible" id="hovervid-error-notice">
            <p><strong>HoverVid Plugin Error:</strong> <?php echo esc_html($message); ?></p>
        </div>
        <script>
            // Add a handler to delete the transient when the notice is dismissed
            jQuery(document).on('click', '#hovervid-error-notice .notice-dismiss', function() {
                jQuery.ajax({
                    url: ajaxurl,
                    data: {
                        action: 'hovervid_dismiss_notice'
                    }
                });
            });
        </script>
        <?php
    }
}
add_action('admin_notices', 'slvp_activation_error_notice');

/**
 * AJAX handler to delete the error transient when the notice is dismissed
 */
function slvp_dismiss_notice_handler() {
    delete_transient('hovervid_activation_error');
    wp_die();
}
add_action('wp_ajax_hovervid_dismiss_notice', 'slvp_dismiss_notice_handler');

/**
 * Handle deactivation for unauthorized domains
 * This is called early to deactivate the plugin before WordPress does much else
 */
function slvp_handle_unauthorized_domain() {
    // Check if our plugin needs to be deactivated
    if (get_option('hovervid_needs_deactivation') === 'yes') {
        // Remove the flag
        delete_option('hovervid_needs_deactivation');
        
        // Deactivate the plugin silently
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        deactivate_plugins(plugin_basename(__FILE__), true); // true = silent
    }
}
add_action('admin_init', 'slvp_handle_unauthorized_domain', 1); // Priority 1 to run early

/**
 * Initialize the plugin
 */
function slvp_init() {
    // Include core classes
    require_once SLVP_PLUGIN_PATH . 'includes/class-database.php';
    require_once SLVP_PLUGIN_PATH . 'includes/class-domain-verifier.php';
    require_once SLVP_PLUGIN_PATH . 'includes/class-video-player.php';
    
    // Try to initialize database connection - but don't fail if it's not available
    try {
        HoverVid_Database::get_instance();
        error_log('HoverVid Plugin: Database connection successful');
    } catch (Exception $e) {
        error_log('HoverVid Plugin: Database connection failed - ' . $e->getMessage());
        error_log('HoverVid Plugin: Continuing in degraded mode (plugin disabled for all domains)');
        
        // Set a global flag that database is unavailable
        if (!defined('HOVERVID_DB_UNAVAILABLE')) {
            define('HOVERVID_DB_UNAVAILABLE', true);
        }
    }
    
    // Initialize the main video player (which will check domain verification)
    // The domain verifier will handle the case where database is unavailable
    new SLVP_Video_Player();
    
    // Log plugin initialization
    if (defined('HOVERVID_DB_UNAVAILABLE')) {
        error_log('HoverVid Plugin: Initialized in degraded mode (database unavailable)');
    } else {
        error_log('HoverVid Plugin: Initialized with centralized domain verification system');
    }
}

/**
 * Hide WordPress "Plugin activated" message when we've intercepted the activation
 */
function slvp_hide_plugin_activated_message() {
    // Only run this if we have an activation error
    if (get_transient('hovervid_activation_error') || get_option('hovervid_needs_deactivation') === 'yes') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Remove the "Plugin activated" message
            $('.updated, .notice-success').each(function() {
                if ($(this).text().indexOf('Plugin activated') >= 0) {
                    $(this).remove();
                }
            });
        });
        </script>
        <?php
    }
}
add_action('admin_head', 'slvp_hide_plugin_activated_message');

/**
 * Display database connection error notice
 */
function slvp_database_error_notice() {
    // Only show if database is unavailable
    if (defined('HOVERVID_DB_UNAVAILABLE') && HOVERVID_DB_UNAVAILABLE) {
        ?>
        <div class="notice notice-warning">
            <p><strong>HoverVid Plugin:</strong> Database connection unavailable. The plugin is currently disabled. Please check your database configuration or contact the plugin provider.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'slvp_database_error_notice');

// Initialize plugin
add_action('plugins_loaded', 'slvp_init');
