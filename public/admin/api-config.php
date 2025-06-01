<?php
// Database connection configuration for direct API endpoints
// Add your actual database password before using

// Credentials from .env
$DB_CONFIG = [
    'host' => '127.0.0.1',
    'port' => '5432',
    'database' => 'hovervid_db',
    'username' => 'postgres',
    'password' => '', // Add your actual password here
];

// Function to get PDO connection
function getDbConnection() {
    global $DB_CONFIG;
    
    try {
        $dsn = "pgsql:host=" . $DB_CONFIG['host'] . 
               ";port=" . $DB_CONFIG['port'] . 
               ";dbname=" . $DB_CONFIG['database'];
               
        $pdo = new PDO($dsn, $DB_CONFIG['username'], $DB_CONFIG['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Database connection failed: " . $e->getMessage());
    }
}

// Function to set common headers
function setApiHeaders() {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    
    // Handle CORS preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Function to return JSON response
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit();
}

// Function to handle errors
function handleError($e, $pdo = null) {
    // Rollback transaction if exists
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error
    error_log($e->getMessage());
    
    // Return error response
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 500);
} 
