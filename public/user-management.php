<?php

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

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

// Create a PDO connection
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

// Get the action from query parameter
$action = $_GET['action'] ?? 'list';

// Handle different actions
switch ($action) {
    case 'create':
        createUser($pdo);
        break;
    case 'update':
        updateUser($pdo);
        break;
    case 'delete':
        deleteUser($pdo);
        break;
    case 'list':
    default:
        listUsers($pdo);
        break;
}

/**
 * Create a new user
 */
function createUser($pdo) {
    // Only allow POST method for creating users
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed, use POST'
        ]);
        return;
    }

    try {
        // Get request body
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);

        // Validate required fields
        if (empty($data['name']) || empty($data['email']) || empty($data['role'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: name, email, and role are required'
            ]);
            return;
        }

        // Check if email already exists
        $checkEmailStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $checkEmailStmt->bindValue(':email', $data['email']);
        $checkEmailStmt->execute();
        
        if ($checkEmailStmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Email already exists'
            ]);
            return;
        }

        // Generate password if not provided
        if (empty($data['password'])) {
            $data['password'] = generatePassword();
        }

        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Set default values if not provided
        $fullName = $data['name'];
        $email = $data['email'];
        $role = $data['role'];
        $status = $data['status'] ?? 'active';
        $createdAt = date('Y-m-d H:i:s');

        // Check if users table exists and has the necessary columns
        try {
            $checkTableStmt = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
            $columns = $checkTableStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Log columns for debugging
            error_log('Available columns in users table: ' . implode(', ', $columns));
        } catch (Exception $e) {
            error_log('Error checking table structure: ' . $e->getMessage());
        }

        // Prepare SQL query with only essential fields
        $sql = "INSERT INTO users (
                name, 
                email, 
                password, 
                role, 
                status, 
                created_at
            ) VALUES (
                :name, 
                :email, 
                :password, 
                :role, 
                :status, 
                :created_at
            ) RETURNING id";

        $stmt = $pdo->prepare($sql);

        // Bind parameters
        $stmt->bindValue(':name', $fullName);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':created_at', $createdAt);

        // Execute and get the new user ID
        $stmt->execute();
        $userId = $stmt->fetchColumn();

        // Get the newly created user
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $userStmt->bindValue(':id', $userId);
        $userStmt->execute();
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'user' => $user
        ]);

    } catch (Exception $e) {
        // Log error details
        error_log('Error creating user: ' . $e->getMessage());
        error_log('SQL state: ' . $e->getCode());
        error_log('File: ' . $e->getFile() . ', Line: ' . $e->getLine());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create user',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update an existing user
 */
function updateUser($pdo) {
    // Only allow PUT method for updating users
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed, use PUT'
        ]);
        return;
    }

    try {
        // Get request body
        $requestBody = file_get_contents('php://input');
        $data = json_decode($requestBody, true);

        // Validate required fields
        if (empty($data['id'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            return;
        }

        // Check if user exists
        $checkUserStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = :id");
        $checkUserStmt->bindValue(':id', $data['id']);
        $checkUserStmt->execute();
        
        if ($checkUserStmt->fetchColumn() == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            return;
        }

        // Build update query
        $updateFields = [];
        $params = [':id' => $data['id']];

        if (!empty($data['name'])) {
            $updateFields[] = "name = :name";
            $params[':name'] = $data['name'];
        }

        if (!empty($data['email'])) {
            // Check if the new email already exists for a different user
            if (isset($data['email'])) {
                $checkEmailStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
                $checkEmailStmt->bindValue(':email', $data['email']);
                $checkEmailStmt->bindValue(':id', $data['id']);
                $checkEmailStmt->execute();
                
                if ($checkEmailStmt->fetchColumn() > 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Email already exists'
                    ]);
                    return;
                }
            }
            
            $updateFields[] = "email = :email";
            $params[':email'] = $data['email'];
        }

        if (!empty($data['password'])) {
            $updateFields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (!empty($data['role'])) {
            $updateFields[] = "role = :role";
            $params[':role'] = $data['role'];
        }

        if (!empty($data['status'])) {
            $updateFields[] = "status = :status";
            $params[':status'] = $data['status'];
        }

        if (!empty($data['company'])) {
            $updateFields[] = "company = :company";
            $params[':company'] = $data['company'];
        }

        if (!empty($data['country'])) {
            $updateFields[] = "country = :country";
            $params[':country'] = $data['country'];
        }

        if (!empty($data['contact'])) {
            $updateFields[] = "contact = :contact";
            $params[':contact'] = $data['contact'];
        }

        if (!empty($data['currentPlan'])) {
            $updateFields[] = "current_plan = :current_plan";
            $params[':current_plan'] = $data['currentPlan'];
        }

        // Add updated_at timestamp
        $updateFields[] = "updated_at = :updated_at";
        $params[':updated_at'] = date('Y-m-d H:i:s');

        // If no fields to update
        if (empty($updateFields)) {
            echo json_encode([
                'success' => false,
                'message' => 'No fields to update'
            ]);
            return;
        }

        // Create and execute the SQL query
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = :id RETURNING *";
        $stmt = $pdo->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully',
            'user' => $user
        ]);

    } catch (Exception $e) {
        // Log error details
        error_log('Error updating user: ' . $e->getMessage());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update user',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Delete a user
 */
function deleteUser($pdo) {
    // Only allow DELETE method for deleting users
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed, use DELETE'
        ]);
        return;
    }

    try {
        // Get user ID from the URL parameter
        $userId = $_GET['id'] ?? null;

        if (empty($userId)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'User ID is required'
            ]);
            return;
        }

        // Check if user exists
        $checkUserStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $checkUserStmt->bindValue(':id', $userId);
        $checkUserStmt->execute();
        
        $user = $checkUserStmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            return;
        }

        // Log the deletion for audit purposes
        error_log("Permanently deleting user ID {$userId} ({$user['name']})");

        // Hard delete - actually remove from database
        $sql = "DELETE FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $userId);
        $stmt->execute();

        // Log the permanent deletion
        error_log("User ID {$userId} ({$user['name']}) permanently deleted from database");

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'User permanently deleted'
        ]);

    } catch (Exception $e) {
        // Log error details
        error_log('Error deleting user: ' . $e->getMessage());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete user',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * List all users
 */
function listUsers($pdo) {
    // Only allow GET method for listing users
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed, use GET'
        ]);
        return;
    }

    try {
        // Retrieve query parameters
        $search = $_GET['q'] ?? null;
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $sortBy = $_GET['sortBy'] ?? 'id';
        $orderBy = $_GET['orderBy'] ?? 'desc';
        $itemsPerPage = isset($_GET['itemsPerPage']) ? (int)$_GET['itemsPerPage'] : 10;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        
        // Build the SQL query - no need to filter deleted_at since we're using permanent deletion
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
        
        if ($itemsPerPage > 0) {
            $query .= " LIMIT :limit OFFSET :offset";
            $params[':limit'] = $itemsPerPage;
            $params[':offset'] = ($page - 1) * $itemsPerPage;
        }
        
        // Execute the query
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            if (($key === ':limit' || $key === ':offset') && $itemsPerPage > 0) {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate last page
        $lastPage = $itemsPerPage > 0 ? ceil($totalUsers / $itemsPerPage) : 1;
        
        // Return JSON response
        echo json_encode([
            'success' => true,
            'users' => $users,
            'totalUsers' => (int)$totalUsers,
            'page' => (int)$page,
            'lastPage' => (int)$lastPage,
        ]);
    } catch (Exception $e) {
        // Log error details
        error_log('Error listing users: ' . $e->getMessage());
        
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to list users',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Generate a random password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+;:,.?';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    
    return $password;
} 