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
require_once SLVP_PLUGIN_PATH . 'includes/class-api-client.php';
require_once SLVP_PLUGIN_PATH . 'includes/class-domain-verifier.php';
require_once SLVP_PLUGIN_PATH . 'includes/class-video-player.php';
require_once SLVP_PLUGIN_PATH . 'includes/class-debug-admin.php';

/**
 * Check if the current domain is authorized
 * 
 * @return array Domain status information
 */
function slvp_check_domain_authorization() {
    $current_domain = $_SERVER['HTTP_HOST'] ?? '';
    
    // Use the centralized domain verifier instead of direct checks
    $verifier = SLVP_Domain_Verifier::get_instance();
    
    return [
        'is_active' => $verifier->is_domain_verified(),
        'message' => $verifier->get_message(),
        'forced' => false, // No more forced domains
        'domain_exists' => $verifier->domain_exists()
    ];
}

/**
 * Plugin activation hook - Check if the domain is authorized
 */
function slvp_activate_plugin() {
    $domain_status = slvp_check_domain_authorization();
    $current_domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    
    // Always try to update plugin status to active via API
    try {
        $api_client = SLVP_API_Client::get_instance();
        $update_result = $api_client->update_status($current_domain, 'active');
        
        if ($update_result && $update_result['success']) {
            error_log("HoverVid Plugin: Status updated to 'active' for domain: {$current_domain}");
        } else {
            error_log("HoverVid Plugin: Failed to update status to 'active' for domain: {$current_domain}");
        }
    } catch (Exception $e) {
        error_log('HoverVid Plugin: API status update failed during activation - ' . $e->getMessage());
    }
    
    // If domain doesn't exist in database, prevent activation
    if (!isset($domain_status['domain_exists']) || !$domain_status['domain_exists']) {
        // Store the error message for domain not found
        $message = "Domain '{$current_domain}' is not authorized to use the HoverVid plugin. Please contact the plugin provider to authorize your domain.";
        
        // Log the error
        error_log('HoverVid Plugin Activation Error: ' . $message);
        
        // Set a transient flag for the admin notice - domain not found
        set_transient('hovervid_activation_error', $message, 300);
        set_transient('hovervid_error_type', 'domain_not_found', 300);
        
        // This is crucial - we're setting a flag that our plugin will check later
        update_option('hovervid_needs_deactivation', 'yes');
        
        // Try to update status to inactive since activation failed
        try {
            $api_client = SLVP_API_Client::get_instance();
            $api_client->update_status($current_domain, 'inactive');
        } catch (Exception $e) {
            error_log('HoverVid Plugin: Failed to update status to inactive after activation failure');
        }
        
        // Plugin will be silently deactivated by slvp_handle_unauthorized_domain
        // The "Plugin activated" message will be hidden by slvp_hide_plugin_activated_message
    }
    // If domain exists but is not verified, show different message
    else if (isset($domain_status['domain_exists']) && $domain_status['domain_exists'] && 
             (!isset($domain_status['is_active']) || !$domain_status['is_active'])) {
        // Domain exists but is not verified - show support message
        $message = "Your HoverVid plugin for domain '{$current_domain}' is currently disabled. Your subscription may have expired or your account may be suspended.";
        
        error_log("HoverVid Plugin: Domain '{$current_domain}' exists but is not verified. Plugin will be inactive until verification.");
        
        // Set a transient flag for the admin notice - domain disabled
        set_transient('hovervid_activation_error', $message, 300);
        set_transient('hovervid_error_type', 'domain_disabled', 300);
    }
}
register_activation_hook(__FILE__, 'slvp_activate_plugin');

/**
 * Display activation error message and handle its dismissal
 */
function slvp_activation_error_notice() {
    // Standard WordPress error transient
    if ($message = get_transient('hovervid_activation_error')) {
        $error_type = get_transient('hovervid_error_type') ?: 'domain_not_found';
        
        if ($error_type === 'domain_disabled') {
            // Domain exists but is disabled - show support message
            ?>
            <div class="notice notice-warning is-dismissible" id="hovervid-error-notice" style="padding: 20px; border-left: 4px solid #f39c12;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 32px;">⚠️</div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 10px 0; color: #8a6d3b;">HoverVid Plugin - Service Disabled</h3>
                        <p style="margin: 0 0 15px 0;"><strong><?php echo esc_html($message); ?></strong></p>
                        <p style="margin: 0 0 15px 0;">Please contact our support team to resolve this issue and reactivate your plugin.</p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="#" class="button button-primary" id="hovervid-support-btn" style="background: #10b981; border: none; color: white; text-decoration: none;">
                                📞 Contact Support
                            </a>
                            <a href="#" class="button button-secondary" id="hovervid-dashboard-btn">
                                🏠 Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    // Get API base URL using the same logic as the API client
                    var apiBaseUrl = 'http://localhost:8000'; // Default to Laravel local development
                    
                    // Use the same detection logic as the API client
                    var serverName = window.location.hostname;
                    if (serverName.includes('localhost') || serverName.includes('127.0.0.1') || serverName.includes('.local')) {
                        // Local development (including .local domains)
                        apiBaseUrl = 'http://localhost:8000';
                    } else {
                        // Production - UPDATE THIS to your actual Laravel domain
                        apiBaseUrl = 'http://localhost:8000'; // Change this to your production Laravel URL when deploying
                    }
                    
                    var currentDomain = window.location.hostname;
                    
                    // Set up support and dashboard buttons
                    $('#hovervid-support-btn').attr('href', apiBaseUrl + '/support?domain=' + encodeURIComponent(currentDomain) + '&source=plugin');
                    $('#hovervid-dashboard-btn').attr('href', apiBaseUrl + '/login?domain=' + encodeURIComponent(currentDomain) + '&source=plugin');
                    
                    // Add click tracking
                    $('#hovervid-support-btn').on('click', function() {
                        console.log('HoverVid: Support button clicked for domain:', currentDomain);
                        console.log('HoverVid: Redirecting to:', this.href);
                    });
                    
                    $('#hovervid-dashboard-btn').on('click', function() {
                        console.log('HoverVid: Dashboard button clicked for domain:', currentDomain);
                        console.log('HoverVid: Redirecting to:', this.href);
                    });
                    
                    // Add a handler to delete the transient when the notice is dismissed
                    $('#hovervid-error-notice .notice-dismiss').on('click', function() {
                        $.ajax({
                            url: ajaxurl,
                            data: {
                                action: 'hovervid_dismiss_notice'
                            }
                        });
                    });
                });
            </script>
            <?php
        } else {
            // Domain not found - show registration message
            ?>
            <div class="notice notice-error is-dismissible" id="hovervid-error-notice" style="padding: 20px; border-left: 4px solid #dc3545;">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div style="font-size: 32px;">🔐</div>
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 10px 0; color: #721c24;">HoverVid Plugin - Domain Not Registered</h3>
                        <p style="margin: 0 0 15px 0;"><strong><?php echo esc_html($message); ?></strong></p>
                        <p style="margin: 0 0 15px 0;">To use this plugin, you need to have an active account and register your domain with HoverVid.</p>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <a href="#" class="button button-primary" id="hovervid-login-btn" style="background: #10b981; border: none; color: white; text-decoration: none;">
                                🔑 Login to Dashboard
                            </a>
                            <a href="#" class="button button-secondary" id="hovervid-signup-btn">
                                📝 Create Account
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
                jQuery(document).ready(function($) {
                    // Get API base URL using the same logic as the API client
                    var apiBaseUrl = 'http://localhost:8000'; // Default to Laravel local development
                    
                    // Use the same detection logic as the API client
                    var serverName = window.location.hostname;
                    if (serverName.includes('localhost') || serverName.includes('127.0.0.1') || serverName.includes('.local')) {
                        // Local development (including .local domains)
                        apiBaseUrl = 'http://localhost:8000';
                    } else {
                        // Production - UPDATE THIS to your actual Laravel domain
                        apiBaseUrl = 'http://localhost:8000'; // Change this to your production Laravel URL when deploying
                    }
                    
                    var currentDomain = window.location.hostname;
                    
                    // Set up login button
                    $('#hovervid-login-btn').attr('href', apiBaseUrl + '/login?domain=' + encodeURIComponent(currentDomain) + '&source=plugin');
                    $('#hovervid-signup-btn').attr('href', apiBaseUrl + '/register?domain=' + encodeURIComponent(currentDomain) + '&source=plugin');
                    
                    // Add click tracking
                    $('#hovervid-login-btn').on('click', function() {
                        console.log('HoverVid: Admin login button clicked for domain:', currentDomain);
                        console.log('HoverVid: Redirecting to:', this.href);
                    });
                    
                    $('#hovervid-signup-btn').on('click', function() {
                        console.log('HoverVid: Admin signup button clicked for domain:', currentDomain);
                        console.log('HoverVid: Redirecting to:', this.href);
                    });
                    
                    // Add a handler to delete the transient when the notice is dismissed
                    $('#hovervid-error-notice .notice-dismiss').on('click', function() {
                        $.ajax({
                            url: ajaxurl,
                            data: {
                                action: 'hovervid_dismiss_notice'
                            }
                        });
                    });
                });
            </script>
            <?php
        }
    }
}
add_action('admin_notices', 'slvp_activation_error_notice');

/**
 * AJAX handler to delete the error transient when the notice is dismissed
 */
function slvp_dismiss_notice_handler() {
    delete_transient('hovervid_activation_error');
    delete_transient('hovervid_error_type');
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
    require_once SLVP_PLUGIN_PATH . 'includes/class-api-client.php';
    require_once SLVP_PLUGIN_PATH . 'includes/class-domain-verifier.php';
    require_once SLVP_PLUGIN_PATH . 'includes/class-video-player.php';
    
    // Try to initialize API connection - but don't fail if it's not available
    try {
        $api_client = SLVP_API_Client::get_instance();
        if ($api_client->test_connectivity()) {
            error_log('HoverVid Plugin: Laravel API connection successful');
        } else {
            error_log('HoverVid Plugin: Laravel API connection failed');
            error_log('HoverVid Plugin: Continuing in degraded mode (plugin disabled for all domains)');
        }
    } catch (Exception $e) {
        error_log('HoverVid Plugin: API initialization failed - ' . $e->getMessage());
        error_log('HoverVid Plugin: Continuing in degraded mode (plugin disabled for all domains)');
    }
    
    // Initialize the main video player (which will check domain verification)
    // The domain verifier will handle the case where API is unavailable
    new SLVP_Video_Player();
    
    // Log plugin initialization
    error_log('HoverVid Plugin: Initialized with Laravel API-based domain verification system');
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
 * Display API connection error notice
 */
function slvp_api_error_notice() {
    // Test API connectivity to show notice if unavailable
    try {
        $api_client = SLVP_API_Client::get_instance();
        if (!$api_client->test_connectivity()) {
            ?>
            <div class="notice notice-warning">
                <p><strong>HoverVid Plugin:</strong> Laravel backend service is unavailable. The plugin is currently disabled. Please check your connection or contact the plugin provider.</p>
            </div>
            <?php
        }
    } catch (Exception $e) {
        ?>
        <div class="notice notice-warning">
            <p><strong>HoverVid Plugin:</strong> Backend service connection failed. The plugin is currently disabled. Please contact the plugin provider.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'slvp_api_error_notice');

/**
 * Plugin deactivation hook - Update plugin status to inactive
 */
function slvp_deactivate_plugin() {
    // Update plugin status to inactive
    try {
        $current_domain = $_SERVER['HTTP_HOST'] ?? '';
        $api_client = SLVP_API_Client::get_instance();
        $api_client->update_status($current_domain, 'inactive');
    } catch (Exception $e) {
        // Log the error but don't prevent deactivation
        error_log('HoverVid Plugin: Failed to update status on deactivation - ' . $e->getMessage());
    }
}
register_deactivation_hook(__FILE__, 'slvp_deactivate_plugin');

// Initialize debug admin page
if (is_admin()) {
    SLVP_Debug_Admin::init();
}

// Initialize plugin
add_action('plugins_loaded', 'slvp_init');
