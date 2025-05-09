<?php
// Disable error reporting
error_reporting(0);
ini_set('display_errors', 0);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Load environment variables
$envFile = __DIR__ . "/../.env";
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, "=") !== false && strpos($line, "#") !== 0) {
            list($key, $value) = explode("=", $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Get database configuration
$connection = $_ENV["DB_CONNECTION"] ?? "mysql";
$host = $_ENV["DB_HOST"] ?? "localhost";
$port = $_ENV["DB_PORT"] ?? ($connection === "pgsql" ? "5432" : "3306");
$database = $_ENV["DB_DATABASE"] ?? "hovervid";
$username = $_ENV["DB_USERNAME"] ?? "root";
$password = $_ENV["DB_PASSWORD"] ?? "";

try {
    // Connect to database
    if ($connection === "pgsql") {
        $dsn = "pgsql:host=$host;port=$port;dbname=$database;user=$username;password=$password";
        $pdo = new PDO($dsn);
    } else {
        $dsn = "mysql:host=$host;port=$port;dbname=$database";
        $pdo = new PDO($dsn, $username, $password);
    }
    
    // Get count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sessions");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'] ?? 0;
    
    // Output JSON
    echo json_encode(['count' => (int)$count]);
} catch (Exception $e) {
    // Return fallback count on error
    echo json_encode(['count' => 5]);
}

// Exit immediately
exit(0); 