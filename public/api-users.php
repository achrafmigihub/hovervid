<?php

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Database configuration - hardcoded values
        $host = '127.0.0.1';
        $port = '5432';
        $dbname = 'hovervid_db';
        $username = 'postgres';
        $password = 'postgres_hovervid';
        
        // Create a new PDO connection
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Retrieve query parameters
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
            $query .= " AND (full_name ILIKE :search OR email ILIKE :search)";
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
        
        // Count total records (for pagination)
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
            'users' => $users,
            'totalUsers' => (int)$totalUsers,
            'page' => (int)$page,
            'lastPage' => (int)$lastPage,
        ]);
    } catch (Exception $e) {
        // Handle errors
        http_response_code(500);
        echo json_encode([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
} else {
    // Method not allowed
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} 