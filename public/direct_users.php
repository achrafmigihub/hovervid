<?php
// Direct database access script - completely bypasses Laravel
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// Basic error handling
try {
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
    
    // Initialize Laravel's database connection
    require __DIR__ . '/../vendor/autoload.php';
    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    
    try {
        // Use Laravel's DB facade to query users
        $users = \Illuminate\Support\Facades\DB::table('users')
            ->select('*')
            ->limit(10)
            ->get()
            ->toArray();
            
        // Convert to array
        $users = array_map(function($user) {
            return (array) $user;
        }, $users);
        
    } catch (Exception $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
    
    // Debug: show raw data plus formatted data
    $formattedUsers = [];
    foreach ($users as $user) {
        $formattedUser = [
            'id' => $user['id'],
            'fullName' => $user['name'] ?? $user['full_name'] ?? $user['username'] ?? ('User ' . $user['id']),
            'email' => $user['email'] ?? ('user' . $user['id'] . '@example.com'),
            'role' => $user['role'] ?? 'client',
            'status' => $user['status'] ?? 'active',
        ];
        
        $formattedUsers[] = $formattedUser;
    }
    
    // Return user data
    echo json_encode([
        "success" => true,
        "count" => count($users),
        "raw_users" => $users,
        "formatted_users" => $formattedUsers,
        "db_connection" => $connection,
        "timestamp" => time()
    ]);
    
} catch (Exception $e) {
    // Return error with some fallback data
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage(),
        "count" => 3,
        "users" => [
            ["id" => 1, "fullName" => "Fallback User 1", "email" => "fallback1@example.com"],
            ["id" => 2, "fullName" => "Fallback User 2", "email" => "fallback2@example.com"],
            ["id" => 3, "fullName" => "Fallback User 3", "email" => "fallback3@example.com"]
        ]
    ]);
}
?> 