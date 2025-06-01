<?php
/**
 * WordPress function stubs for IDE support
 * 
 * This file contains stub declarations of WordPress functions to help IDE with code completion
 * and eliminate linting errors. This file should NOT be included in the actual plugin.
 */

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Only define these functions if WordPress core isn't loaded
if (!function_exists('plugin_dir_path')) {
    /**
     * Get the filesystem directory path (with trailing slash) for the plugin.
     *
     * @param string $file The plugin file path
     * @return string The filesystem path of the directory that contains the plugin
     */
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    /**
     * Get the URL directory path (with trailing slash) for the plugin.
     *
     * @param string $file The plugin file path
     * @return string The URL path of the directory that contains the plugin
     */
    function plugin_dir_url($file) {
        return '';
    }
}

if (!function_exists('add_action')) {
    /**
     * Hooks a function to a specific action.
     *
     * @param string   $tag             The name of the action to hook the callback to
     * @param callable $function_to_add The callback to be run when the action is called
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  associated with a particular action are executed
     * @param int      $accepted_args   Optional. The number of arguments the function accepts
     */
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        // This is just a stub
    }
}

if (!function_exists('add_filter')) {
    /**
     * Hooks a function to a specific filter action.
     *
     * @param string   $tag             The name of the filter to hook the callback to
     * @param callable $function_to_add The callback to be run when the filter is applied
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  associated with a particular filter are executed
     * @param int      $accepted_args   Optional. The number of arguments the function accepts
     */
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        // This is just a stub
    }
}

if (!function_exists('wp_send_json_success')) {
    /**
     * Send a JSON response back to an Ajax request, indicating success.
     *
     * @param mixed $data Optional. Data to encode as JSON, then print and die
     */
    function wp_send_json_success($data = null) {
        // This is just a stub
    }
}

if (!function_exists('wp_send_json_error')) {
    /**
     * Send a JSON response back to an Ajax request, indicating failure.
     *
     * @param mixed $data Optional. Data to encode as JSON, then print and die
     */
    function wp_send_json_error($data = null) {
        // This is just a stub
    }
}

if (!function_exists('check_ajax_referer')) {
    /**
     * Verifies the AJAX request to prevent processing requests external of the blog.
     *
     * @param string $action    Action nonce
     * @param string $query_arg Optional. Key to check for nonce in `$_REQUEST`
     * @param bool   $die       Optional. Whether to die early when the nonce cannot be verified
     * @return false|int False if the nonce is invalid, 1 if the nonce is valid and generated between
     *                   0-12 hours ago, 2 if the nonce is valid and generated between 12-24 hours ago
     */
    function check_ajax_referer($action, $query_arg = false, $die = true) {
        // This is just a stub
        return 1;
    }
}

if (!function_exists('register_activation_hook')) {
    /**
     * Registers a plugin activation hook.
     *
     * @param string   $file     Plugin file
     * @param callable $function The callback to run when the hook is called
     */
    function register_activation_hook($file, $function) {
        // This is just a stub
    }
}

if (!function_exists('register_deactivation_hook')) {
    /**
     * Registers a plugin deactivation hook.
     *
     * @param string   $file     Plugin file
     * @param callable $function The callback to run when the hook is called
     */
    function register_deactivation_hook($file, $function) {
        // This is just a stub
    }
} 

// Add missing WordPress function stubs

if (!function_exists('set_transient')) {
    /**
     * Sets/updates the value of a transient.
     *
     * @param string $transient  Transient name
     * @param mixed  $value      Transient value
     * @param int    $expiration Optional. Time until expiration in seconds
     * @return bool True if the value was set, false otherwise
     */
    function set_transient($transient, $value, $expiration = 0) {
        // This is just a stub
        return true;
    }
}

if (!function_exists('get_transient')) {
    /**
     * Gets the value of a transient.
     *
     * @param string $transient Transient name
     * @return mixed Value of transient or false if not set
     */
    function get_transient($transient) {
        // This is just a stub
        return false;
    }
}

if (!function_exists('delete_transient')) {
    /**
     * Deletes a transient.
     *
     * @param string $transient Transient name
     * @return bool True if successful, false otherwise
     */
    function delete_transient($transient) {
        // This is just a stub
        return true;
    }
}

if (!function_exists('update_option')) {
    /**
     * Updates an option value for the specified option.
     *
     * @param string      $option   Name of the option to update
     * @param mixed       $value    Option value
     * @param string|bool $autoload Optional. Whether to load the option when WordPress starts up
     * @return bool True if value was updated, false otherwise
     */
    function update_option($option, $value, $autoload = null) {
        // This is just a stub
        return true;
    }
}

if (!function_exists('get_option')) {
    /**
     * Retrieves an option value based on an option name.
     *
     * @param string $option  Name of the option to retrieve
     * @param mixed  $default Optional. Default value to return if the option does not exist
     * @return mixed Value of the option or default if option doesn't exist
     */
    function get_option($option, $default = false) {
        // This is just a stub
        return false;
    }
}

if (!function_exists('delete_option')) {
    /**
     * Removes option by name.
     *
     * @param string $option Name of the option to delete
     * @return bool True if the option was deleted, false otherwise
     */
    function delete_option($option) {
        // This is just a stub
        return true;
    }
}

if (!function_exists('deactivate_plugins')) {
    /**
     * Deactivate a single plugin or multiple plugins.
     *
     * @param string|array $plugins Single plugin or list of plugins to deactivate
     * @param bool         $silent  Optional. Whether to prevent calling activation hooks
     * @param bool|null    $network Optional. Whether to deactivate the plugin for all sites in the network
     * @return void
     */
    function deactivate_plugins($plugins, $silent = false, $network = null) {
        // This is just a stub
    }
}

if (!function_exists('plugin_basename')) {
    /**
     * Gets the basename of a plugin.
     *
     * @param string $file The filename of the plugin
     * @return string The base name of the plugin
     */
    function plugin_basename($file) {
        // This is just a stub
        return basename($file);
    }
}

if (!function_exists('esc_html')) {
    /**
     * Escapes HTML content.
     *
     * @param string $text The text to be escaped
     * @return string Escaped HTML content
     */
    function esc_html($text) {
        // This is just a stub
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    /**
     * Escapes HTML attributes.
     *
     * @param string $text The text to be escaped
     * @return string Escaped attribute content
     */
    function esc_attr($text) {
        // This is just a stub
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_die')) {
    /**
     * Kills WordPress execution and displays HTML message with error message.
     *
     * @param string|WP_Error $message Error message or WP_Error object
     * @param string          $title   Optional. Error title
     * @param array           $args    Optional. Arguments to control behavior
     */
    function wp_die($message = '', $title = '', $args = array()) {
        // This is just a stub
        die($message);
    }
}

if (!function_exists('is_admin')) {
    /**
     * Determines whether the current request is for an administrative interface page.
     *
     * @return bool True if inside WordPress administration interface, false otherwise
     */
    function is_admin() {
        // This is just a stub
        return false;
    }
} 
