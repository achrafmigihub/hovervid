<?php

// Debugging output
error_log("api-users.php accessed at " . date('Y-m-d H:i:s'));
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Query params: " . json_encode($_GET));

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

// Database configuration
$host = '127.0.0.1';
$port = '5432';
$dbname = 'hovervid_db';
$username = 'postgres';
$password = 'postgres_hovervid';

// Create a new PDO connection
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
    exit;
}

// Check for user ID in query parameters (try all possible parameter names)
$userId = null;
if (isset($_GET['id'])) {
    $userId = intval($_GET['id']);
} elseif (isset($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
} elseif (isset($_GET['direct_id'])) {
    $userId = intval($_GET['direct_id']);
}

// If not found in query, try parsing from URL path
if (!$userId) {
    $path = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', trim($path, '/'));

    // Extract user ID from URL patterns like /api/users/123
    foreach ($pathParts as $i => $part) {
        if (($part === 'api' && isset($pathParts[$i+1]) && $pathParts[$i+1] === 'users' && isset($pathParts[$i+2]) && is_numeric($pathParts[$i+2])) || 
            ($part === 'users' && isset($pathParts[$i+1]) && is_numeric($pathParts[$i+1]))) {
            $userId = is_numeric($pathParts[$i+2] ?? $pathParts[$i+1]) ? intval($pathParts[$i+2] ?? $pathParts[$i+1]) : null;
            break;
        }
    }
}

// Log the extracted user ID
error_log("Extracted user ID: " . ($userId ? $userId : "none"));

// Handle GET request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Single user request
        if ($userId) {
            // Fetch user by ID with a secure parameterized query
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found'
                ]);
                exit;
            }
            
            // Return user data
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
            exit;
        }
        
        // List users (with pagination and filters)
        $search = $_GET['q'] ?? null;
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $sortBy = $_GET['sortBy'] ?? 'id';
        $orderBy = $_GET['orderBy'] ?? 'desc';
        $itemsPerPage = isset($_GET['itemsPerPage']) ? (int)$_GET['itemsPerPage'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        // Build the SQL query
        $query = "SELECT * FROM users WHERE 1=1";
        $params = [];
        
        if ($search) {
            $query .= " AND (name ILIKE :search OR email ILIKE :search)";
            $params[':search'] = "%{$search}%";
        }
        
        if ($role) {
            $query .= " AND role = :role";
            $params[':role'] = $role;
        }
        
        if ($status) {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        // Count total records
        $countQuery = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
        $stmt = $pdo->prepare($countQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Add sorting and pagination
        $query .= " ORDER BY {$sortBy} {$orderBy}";
        $query .= " LIMIT :limit OFFSET :offset";
        $params[':limit'] = $itemsPerPage;
        $params[':offset'] = ($page - 1) * $itemsPerPage;
        
        // Execute the query
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            if ($key === ':limit' || $key === ':offset') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate last page
        $lastPage = ceil($totalUsers / $itemsPerPage);
        
        // Return JSON response
        echo json_encode([
            'success' => true,
            'users' => $users,
            'totalUsers' => (int)$totalUsers,
            'page' => (int)$page,
            'lastPage' => (int)$lastPage,
        ]);
    } catch (Exception $e) {
        // Handle errors
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred',
            'error' => $e->getMessage()
        ]);
    }
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
} 
