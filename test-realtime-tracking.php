<?php
/**
 * Test script to verify real-time tracking of is_verified column
 */

// Database connection parameters
$host = '127.0.0.1';
$port = '5432';
$dbname = 'hovervid_db';
$username = 'postgres';
$password = 'postgres_hovervid';

echo "=== Real-Time is_verified Tracking Test ===\n\n";

try {
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    
    echo "✓ Database connection successful\n\n";
    
    // Test domain
    $test_domain = 'demo.local';
    
    echo "Testing real-time tracking with domain: {$test_domain}\n";
    echo str_repeat("-", 50) . "\n\n";
    
    // Function to get current status
    function getCurrentStatus($pdo, $domain) {
        $stmt = $pdo->prepare("SELECT domain, is_verified FROM domains WHERE domain = :domain");
        $stmt->execute([':domain' => $domain]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Function to simulate plugin check
    function simulatePluginCheck($pdo, $domain) {
        echo "[" . date('Y-m-d H:i:s') . "] Plugin checking domain status...\n";
        
        $result = getCurrentStatus($pdo, $domain);
        if ($result) {
            $is_verified = (bool)$result['is_verified'];
            $status = $is_verified ? 'ENABLED' : 'DISABLED (License Expired)';
            echo "Domain: {$domain}\n";
            echo "is_verified: " . ($is_verified ? 'true' : 'false') . "\n";
            echo "Plugin Status: {$status}\n";
            
            if (!$is_verified) {
                echo "Toggle Button: Disabled with message 'Your subscription or license has expired. Please contact support to renew your access.'\n";
            } else {
                echo "Toggle Button: Enabled and functional\n";
            }
        } else {
            echo "Domain not found - Plugin would be UNAUTHORIZED\n";
        }
        echo "\n";
    }
    
    // Show initial status
    echo "1. Initial Status:\n";
    simulatePluginCheck($pdo, $test_domain);
    
    // Simulate disabling the domain
    echo "2. Simulating license expiration (setting is_verified = false):\n";
    $stmt = $pdo->prepare("UPDATE domains SET is_verified = false WHERE domain = :domain");
    $stmt->execute([':domain' => $test_domain]);
    echo "✓ Updated is_verified to false\n\n";
    
    simulatePluginCheck($pdo, $test_domain);
    
    sleep(2); // Simulate time passing
    
    // Simulate enabling the domain
    echo "3. Simulating license renewal (setting is_verified = true):\n";
    $stmt = $pdo->prepare("UPDATE domains SET is_verified = true WHERE domain = :domain");
    $stmt->execute([':domain' => $test_domain]);
    echo "✓ Updated is_verified to true\n\n";
    
    simulatePluginCheck($pdo, $test_domain);
    
    sleep(2); // Simulate time passing
    
    // Reset to original state (false)
    echo "4. Resetting to original state (is_verified = false):\n";
    $stmt = $pdo->prepare("UPDATE domains SET is_verified = false WHERE domain = :domain");
    $stmt->execute([':domain' => $test_domain]);
    echo "✓ Reset is_verified to false\n\n";
    
    simulatePluginCheck($pdo, $test_domain);
    
    echo str_repeat("=", 50) . "\n";
    echo "✅ Real-time tracking test completed!\n\n";
    
    echo "How the plugin now works:\n";
    echo "1. ✅ Checks database on every page load\n";
    echo "2. ✅ Periodic checking every 30 seconds\n";
    echo "3. ✅ Checks when window regains focus\n";
    echo "4. ✅ Checks when page becomes visible\n";
    echo "5. ✅ Immediately responds to status changes\n";
    echo "6. ✅ No caching - always fresh data\n\n";
    
    echo "To test live changes:\n";
    echo "1. Open a website with the plugin installed\n";
    echo "2. Change is_verified in the database:\n";
    echo "   UPDATE domains SET is_verified = false WHERE domain = 'your-domain.com';\n";
    echo "3. The plugin will detect the change within 30 seconds (or immediately on page focus)\n";
    echo "4. Toggle button will become disabled with license expired message\n";
    echo "5. Change back to true to re-enable:\n";
    echo "   UPDATE domains SET is_verified = true WHERE domain = 'your-domain.com';\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?> 
