<?php
// This is a direct API endpoint for user data that bypasses Laravel routing

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get user ID from query parameter
$userId = isset($_GET['id']) ? $_GET['id'] : null;

// Check for special 'me' case which means current user
$isCurrentUser = ($userId === 'me');

// If 'me' is requested, we need to get the current authenticated user ID
if ($isCurrentUser) {
    // Starting a session to access session data
    session_start();
    
    // Check if there's a user ID in the session
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    } else {
        // Try to find the user ID from the auth session
        $sessionPath = __DIR__ . '/../storage/framework/sessions/';
        $files = scandir($sessionPath);
        $foundUserId = false;
        
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && is_file($sessionPath . $file)) {
                $sessionData = file_get_contents($sessionPath . $file);
                if (strpos($sessionData, 'user_id') !== false || strpos($sessionData, 'auth') !== false) {
                    // Found a potential auth session
                    try {
                        // Extract user ID - this is a simplistic approach
                        if (preg_match('/user_id";i:(\d+)/', $sessionData, $matches)) {
                            $userId = $matches[1];
                            $foundUserId = true;
                            break;
                        }
                    } catch (Exception $e) {
                        // Skip if session file is corrupt
                        continue;
                    }
                }
            }
        }
        
        if (!$foundUserId) {
            echo json_encode([
                'success' => false,
                'message' => 'Cannot determine current user. Please login first.'
            ]);
            exit;
        }
    }
}

// Validate user ID after potential 'me' resolution
if (!$userId || !is_numeric($userId)) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid user ID is required'
    ]);
    exit;
}

// Database configuration
$host = '127.0.0.1';
$port = '5432';
$dbname = 'hovervid_db';
$username = 'postgres';
$password = 'postgres_hovervid';

// Connect to the database
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Fetch the user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching user',
        'error' => $e->getMessage()
    ]);
} 
