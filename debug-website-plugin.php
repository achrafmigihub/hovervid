<?php
/**
 * Debug script to simulate what happens when plugin is installed on a website
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define WordPress constants that the plugin expects
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

echo "=== Website Plugin Debug ===\n\n";

// Ask user for the domain they're testing on
echo "What domain did you test the plugin on? ";
$website_domain = trim(fgets(STDIN));

if (empty($website_domain)) {
    echo "No domain provided. Using 'test-website.com' as example.\n";
    $website_domain = 'test-website.com';
}

echo "Testing plugin behavior for domain: {$website_domain}\n\n";

// Load plugin files
require_once __DIR__ . '/hovervid-plugin/includes/class-config.php';

// Simulate the website environment
$_SERVER['HTTP_HOST'] = $website_domain;
$_SERVER['SERVER_NAME'] = $website_domain;

echo "=== Step 1: Check Plugin Configuration ===\n";
$environment = HoverVid_Config::get_environment();
$use_direct_db = HoverVid_Config::use_direct_database();
$backend_url = HoverVid_Config::get_laravel_backend_url();

echo "Environment detected: {$environment}\n";
echo "Use direct database: " . ($use_direct_db ? 'YES' : 'NO') . "\n";
echo "Backend URL: {$backend_url}\n\n";

echo "=== Step 2: Test Domain Detection ===\n";
require_once __DIR__ . '/hovervid-plugin/sign-language-video.php';

$detected_domain = hovervid_get_current_domain();
echo "Domain detected by plugin: {$detected_domain}\n\n";

echo "=== Step 3: Test Authorization Process ===\n";

if ($use_direct_db) {
    echo "Plugin is trying to use DIRECT DATABASE mode...\n";
    require_once __DIR__ . '/hovervid-plugin/includes/class-database.php';
    
    try {
        $database = HoverVid_Database::get_instance();
        $result = $database->check_domain_authorization($detected_domain);
        echo "Database check result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "Plugin is trying to use API mode...\n";
    echo "API URL: {$backend_url}/api/plugin/validate-domain\n";
    
    // Test if API is accessible
    $test_url = $backend_url . '/api/plugin/validate-domain';
    echo "Testing API connectivity...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['domain' => $detected_domain]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        echo "❌ API Connection Error: {$curl_error}\n";
        echo "This is why the plugin is failing!\n";
    } elseif ($http_code !== 200) {
        echo "❌ API HTTP Error: {$http_code}\n";
        echo "Response: {$response}\n";
    } else {
        echo "✅ API Response: {$response}\n";
    }
}

echo "\n=== Step 4: Check if Domain Exists in Database ===\n";
echo "Let me check if '{$detected_domain}' exists in your local database...\n";

// Connect to local database to check if domain exists
try {
    $pdo = new PDO("pgsql:host=127.0.0.1;port=5432;dbname=hovervid_db", 'postgres', 'postgres_hovervid');
    $stmt = $pdo->prepare("SELECT domain, status, is_active FROM domains WHERE domain = ?");
    $stmt->execute([$detected_domain]);
    $domain_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($domain_record) {
        echo "✅ Domain found in database:\n";
        echo "   Domain: " . $domain_record['domain'] . "\n";
        echo "   Status: " . $domain_record['status'] . "\n";
        echo "   Active: " . ($domain_record['is_active'] ? 'YES' : 'NO') . "\n";
    } else {
        echo "❌ Domain NOT found in database!\n";
        echo "You need to add '{$detected_domain}' to your domains table.\n";
    }
} catch (Exception $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
}

echo "\n=== SOLUTION ===\n";
if (!$use_direct_db && strpos($backend_url, 'api.hovervid.com') !== false) {
    echo "PROBLEM: Plugin is configured for API mode but you haven't deployed your server yet!\n\n";
    echo "QUICK FIX OPTIONS:\n";
    echo "1. Add the domain to your database and test locally\n";
    echo "2. Deploy your Laravel app to a public server\n";
    echo "3. Temporarily use direct database mode for testing\n\n";
} elseif (!$use_direct_db) {
    echo "The plugin is using API mode but the API is not accessible.\n";
} else {
    echo "Check if the domain exists in your database and is active.\n";
}

echo "=== Debug Complete ===\n"; 
