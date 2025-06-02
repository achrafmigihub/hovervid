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
     * @return bool True if the current request is for an administrative interface page, false otherwise
     */
    function is_admin() {
        // This is just a stub
        return false;
    }
} 

// WordPress HTTP API function stubs
if (!function_exists('wp_remote_request')) {
    /**
     * Performs an HTTP request using the WordPress HTTP API.
     *
     * @param string $url  URL to retrieve
     * @param array  $args Optional. Request arguments
     * @return array|WP_Error The response or WP_Error on failure
     */
    function wp_remote_request($url, $args = array()) {
        // For testing purposes, actually make the real HTTP request using curl
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: HoverVid-Plugin/1.0'
            )
        ));
        
        // Handle different HTTP methods
        $method = isset($args['method']) ? strtoupper($args['method']) : 'GET';
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (isset($args['body'])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $args['body']);
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Return WordPress-style response
        if ($error) {
            error_log('HTTP Request Error: ' . $error);
            return array(
                'headers'  => array(),
                'body'     => '',
                'response' => array(
                    'code'    => 0,
                    'message' => 'Connection Error'
                )
            );
        }
        
        return array(
            'headers'  => array(),
            'body'     => $response,
            'response' => array(
                'code'    => $http_code,
                'message' => 'OK'
            )
        );
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    /**
     * Retrieves only the body from the raw response.
     *
     * @param array|WP_Error $response HTTP response
     * @return string The body of the response or empty string on failure
     */
    function wp_remote_retrieve_body($response) {
        return isset($response['body']) ? $response['body'] : '';
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    /**
     * Retrieves only the response code from the raw response.
     *
     * @param array|WP_Error $response HTTP response
     * @return int The response code or 0 on failure
     */
    function wp_remote_retrieve_response_code($response) {
        return isset($response['response']['code']) ? $response['response']['code'] : 200;
    }
}

if (!function_exists('is_wp_error')) {
    /**
     * Checks whether variable is a WordPress Error.
     *
     * @param mixed $thing Check if unknown variable is WordPress Error object
     * @return bool True if WP_Error, false otherwise
     */
    function is_wp_error($thing) {
        return false; // For stub purposes, never return error
    }
}

if (!function_exists('wp_enqueue_style')) {
    /**
     * Enqueue a CSS stylesheet.
     *
     * @param string           $handle Name of the stylesheet
     * @param string           $src    Full URL of the stylesheet
     * @param array            $deps   Optional. An array of registered stylesheet handles this stylesheet depends on
     * @param string|bool|null $ver    Optional. String specifying stylesheet version number
     * @param string           $media  Optional. The media for which this stylesheet has been defined
     */
    function wp_enqueue_style($handle, $src = '', $deps = array(), $ver = false, $media = 'all') {
        // This is just a stub
    }
}

if (!function_exists('wp_enqueue_script')) {
    /**
     * Enqueue a script.
     *
     * @param string           $handle    Name of the script
     * @param string           $src       Full URL of the script
     * @param array            $deps      Optional. An array of registered script handles this script depends on
     * @param string|bool|null $ver       Optional. String specifying script version number
     * @param bool             $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>
     */
    function wp_enqueue_script($handle, $src = '', $deps = array(), $ver = false, $in_footer = false) {
        // This is just a stub
    }
}

if (!function_exists('wp_localize_script')) {
    /**
     * Localizes a script by adding data to it.
     *
     * @param string $handle      Script handle the data will be attached to
     * @param string $object_name Name for the JavaScript object
     * @param array  $l10n        The data itself
     * @return bool True if the script was successfully localized, false otherwise
     */
    function wp_localize_script($handle, $object_name, $l10n) {
        // This is just a stub
        return true;
    }
}

if (!function_exists('wp_add_inline_script')) {
    /**
     * Adds extra code to a registered script.
     *
     * @param string $handle   Name of the script to add extra code to
     * @param string $data     String containing the JavaScript to be added
     * @param string $position Optional. Whether to add the code before or after the script
     * @return bool True on success, false on failure
     */
    function wp_add_inline_script($handle, $data, $position = 'after') {
        // This is just a stub
        return true;
    }
}

if (!function_exists('admin_url')) {
    /**
     * Retrieves the URL to the admin area.
     *
     * @param string $path   Optional. Path relative to the admin URL
     * @param string $scheme Optional. The scheme to use
     * @return string Admin URL link with optional path appended
     */
    function admin_url($path = '', $scheme = 'admin') {
        return 'http://localhost/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    /**
     * Creates a cryptographic token tied to a specific action, user, and window of time.
     *
     * @param string|int $action Scalar value to add context to the nonce
     * @return string The token
     */
    function wp_create_nonce($action = -1) {
        return 'test_nonce_12345';
    }
} 
