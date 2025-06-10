<?php
/**
 * HoverVid Plugin Configuration
 * 
 * This file contains configuration settings for the HoverVid plugin.
 * Copy this to your WordPress installation and customize as needed.
 * 
 * @package SLVP
 */

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * CONFIGURATION INSTRUCTIONS:
 * 
 * 1. Copy this file to your WordPress root directory or wp-content directory
 * 2. Update the HOVERVID_API_URL constant below with your Laravel backend URL
 * 3. Include this file in your wp-config.php by adding:
 *    require_once ABSPATH . 'plugin-config.php';
 * 
 * OR
 * 
 * Add the HOVERVID_API_URL constant directly to your wp-config.php file:
 * define('HOVERVID_API_URL', 'https://your-laravel-backend.com');
 */

// HoverVid Plugin Configuration
if (!defined('HOVERVID_API_URL')) {
    /**
     * Laravel Backend API URL
     * 
     * Replace 'https://your-laravel-backend.com' with your actual Laravel backend URL
     * Examples:
     * - Local development: 'http://localhost:8000'
     * - Production: 'https://api.yourdomain.com'
     * - Subdomain: 'https://hovervid-api.yourdomain.com'
     */
    define('HOVERVID_API_URL', 'http://localhost:8000');
}

/**
 * Alternative method using environment variables:
 * 
 * If you prefer to use environment variables, you can set HOVERVID_API_URL
 * in your server environment and the plugin will automatically detect it.
 * 
 * Example for .env file or server environment:
 * HOVERVID_API_URL=https://your-laravel-backend.com
 */ 