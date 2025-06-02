<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     * You can enable CORS for 1 or multiple paths.
     * Example: ['api/*']
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    /*
     * Matches the request method. `['*']` allows all methods.
     */
    'allowed_methods' => ['*'],

    /*
     * Matches the request origin. `['*']` allows all origins. 
     * For development, we'll allow the Vue.js dev server.
     * Add production domains when deploying.
     */
    'allowed_origins' => [
        'http://localhost:5173',    // Vue dev server
        'http://127.0.0.1:5173',    // Alternative Vue dev server URL
        'http://[::1]:5173',        // IPv6 localhost Vue dev server
        'https://[::1]:5173',       // IPv6 localhost Vue dev server (HTTPS)
        'http://localhost:3000',    // In case you use Vite/Nuxt/etc. on a different port
        'http://localhost:8000',    // Laravel itself (for same-origin requests)
        'http://127.0.0.1:8000',    // Alternative Laravel URL
        'http://localhost:8001',    // Additional dev server
        'http://127.0.0.1:8001',    // Additional dev server alternative URL
        // WordPress plugin domains
        'http://sign-language-video-plugin.local',  // WordPress plugin domain
        'https://sign-language-video-plugin.local', // WordPress plugin domain (HTTPS)
        'http://localhost',         // WordPress localhost
        'https://localhost',        // WordPress localhost (HTTPS)
    ],

    /*
     * Matches the request origin with, similar to `Request::is()`
     */
    'allowed_origins_patterns' => [],

    /*
     * Sets the Access-Control-Allow-Headers response header. `['*']` allows all headers.
     */
    'allowed_headers' => [
        'X-Requested-With',
        'Content-Type',
        'X-Token-Auth',
        'Authorization',
        'X-XSRF-TOKEN',
        'X-CSRF-TOKEN',
        'Accept',
        'X-Api-Key',
        'Origin',
    ],

    /*
     * Sets the Access-Control-Expose-Headers response header with these headers.
     */
    'exposed_headers' => [
        'Cache-Control',
        'Content-Language',
        'Content-Length',
        'Content-Type',
        'Expires',
        'Last-Modified',
        'Pragma',
        'X-Pagination-Total-Count',
        'X-Pagination-Page-Count',
        'X-Pagination-Current-Page',
        'X-Pagination-Per-Page',
    ],

    /*
     * Sets the Access-Control-Max-Age response header when > 0.
     * The value is in seconds.
     */
    'max_age' => 60 * 60 * 24, // 24 hours for preflight cache

    /*
     * Sets the Access-Control-Allow-Credentials header.
     */
    'supports_credentials' => true, // Important for cookies, authorization headers with HTTPS
]; 
