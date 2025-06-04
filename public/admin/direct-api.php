<?php
// All-in-one direct API for domain management
// Development workaround - not for production

// Include configuration and helpers
require_once 'api-config.php';

// Set API headers
setApiHeaders();

try {
    // Parse request URI and method
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Remove the script name from URI
    $baseScript = '/direct-api.php';
    $path = '';
    
    if (strpos($uri, $baseScript) === 0) {
        $path = substr($uri, strlen($baseScript));
    }
    
    // Normalize path
    $path = trim($path, '/');
    $pathParts = explode('/', $path);
    
    // Route API requests
    $resource = $pathParts[0] ?? '';
    
    // Handle domains resource
    if ($resource === 'domains' || $resource === '') {
        // Get domains list
        if ($method === 'GET' && count($pathParts) <= 1) {
            handleGetDomains();
        }
        // Create new domain
        else if ($method === 'POST' && count($pathParts) === 1) {
            handleCreateDomain();
        }
        // Activate domain
        else if ($method === 'POST' && count($pathParts) === 3 && $pathParts[2] === 'activate') {
            handleActivateDomain($pathParts[1]);
        }
        // Deactivate domain
        else if ($method === 'POST' && count($pathParts) === 3 && $pathParts[2] === 'deactivate') {
            handleDeactivateDomain($pathParts[1]);
        }
        // Verify domain
        else if ($method === 'POST' && count($pathParts) === 3 && $pathParts[2] === 'verify') {
            handleVerifyDomain($pathParts[1]);
        }
        // Delete domain
        else if ($method === 'DELETE' && count($pathParts) === 2) {
            handleDeleteDomain($pathParts[1]);
        }
        // Invalid domain endpoint
        else {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid API endpoint'
            ], 404);
        }
    }
    // Default API index
    else if ($resource === '') {
        jsonResponse([
            'success' => true,
            'message' => 'Domain Management Direct API',
            'timestamp' => date('c'),
            'version' => '1.0'
        ]);
    }
    // Invalid resource
    else {
        jsonResponse([
            'success' => false,
            'message' => 'Invalid resource: ' . $resource
        ], 404);
    }
} catch (Exception $e) {
    handleError($e);
}

// Function to handle GET /domains
function handleGetDomains() {
    try {
        $pdo = getDbConnection();
        
        // Get query parameters
        $search = $_GET['q'] ?? '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = isset($_GET['itemsPerPage']) ? max(1, min(100, intval($_GET['itemsPerPage']))) : 10;
        $sortBy = $_GET['sortBy'] ?? 'created_at';
        $orderBy = (isset($_GET['orderBy']) && strtolower($_GET['orderBy']) === 'asc') ? 'ASC' : 'DESC';
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Base query
        $query = "
            SELECT 
                d.id, 
                d.domain, 
                d.status, 
                d.is_active, 
                d.is_verified, 
                d.created_at,
                d.user_id,
                u.name as user_name,
                u.email as user_email,
                u.role as user_role,
                l.expiry_date as license_expiry
            FROM 
                domains d
            LEFT JOIN 
                users u ON d.user_id = u.id
            LEFT JOIN 
                licenses l ON d.id = l.domain_id
        ";
        
        // Add search condition
        $params = [];
        if (!empty($search)) {
            $query .= " WHERE d.domain LIKE :search OR u.name LIKE :search OR u.email LIKE :search";
            $params['search'] = "%$search%";
        }
        
        // Add sorting - use safe column list
        $allowedColumns = ['id', 'domain', 'status', 'is_active', 'created_at', 'user_name', 'license_expiry'];
        $sortColumn = in_array($sortBy, $allowedColumns) ? $sortBy : 'created_at';
        
        // Map columns to database fields
        $columnMap = [
            'user_name' => 'u.name',
            'license_expiry' => 'l.expiry_date',
            'created_at' => 'd.created_at',
            'id' => 'd.id',
            'domain' => 'd.domain',
            'status' => 'd.status',
            'is_active' => 'd.is_active'
        ];
        
        $sortColumnDb = isset($columnMap[$sortColumn]) ? $columnMap[$sortColumn] : 'd.created_at';
        $query .= " ORDER BY $sortColumnDb $orderBy";
        
        // Count total domains (without limit/offset)
        $countQuery = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $query);
        $countQuery = preg_replace('/ORDER BY.*$/s', '', $countQuery);
        
        $stmt = $pdo->prepare($countQuery);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        $totalDomains = $stmt->fetchColumn();
        
        // Add pagination
        $query .= " LIMIT :limit OFFSET :offset";
        $params['limit'] = $perPage;
        $params['offset'] = $offset;
        
        // Execute query
        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') {
                $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(":$key", $value);
            }
        }
        $stmt->execute();
        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return JSON response
        jsonResponse([
            'success' => true,
            'domains' => $domains,
            'totalDomains' => $totalDomains,
            'page' => $page,
            'perPage' => $perPage
        ]);
    } catch (Exception $e) {
        handleError($e);
    }
}

// Function to handle POST /domains
function handleCreateDomain() {
    try {
        // Get request body
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($data['domain']) || empty($data['user_id'])) {
            jsonResponse([
                'success' => false,
                'message' => 'Domain and user_id are required'
            ], 400);
        }
        
        // Set default values
        $platform = $data['platform'] ?? 'WordPress';
        $status = $data['status'] ?? 'inactive';
        $isVerified = isset($data['is_verified']) ? (bool)$data['is_verified'] : false;
        
        // Generate API key and verification token
        $apiKey = bin2hex(random_bytes(16));
        $verificationToken = bin2hex(random_bytes(8));
        
        // Get database connection
        $pdo = getDbConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert domain
        $stmt = $pdo->prepare("
            INSERT INTO domains 
                (domain, user_id, platform, status, is_active, is_verified, api_key, verification_token, created_at, updated_at) 
            VALUES 
                (:domain, :user_id, :platform, :status, :is_active, :is_verified, :api_key, :verification_token, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            RETURNING id
        ");
        
        $isActive = ($status === 'active');
        
        $stmt->bindParam(':domain', $data['domain']);
        $stmt->bindParam(':user_id', $data['user_id']);
        $stmt->bindParam(':platform', $platform);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':is_active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindParam(':is_verified', $isVerified, PDO::PARAM_BOOL);
        $stmt->bindParam(':api_key', $apiKey);
        $stmt->bindParam(':verification_token', $verificationToken);
        
        $stmt->execute();
        
        // Get inserted ID
        $domainId = $stmt->fetchColumn();
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        jsonResponse([
            'success' => true,
            'message' => 'Domain created successfully',
            'domain' => [
                'id' => $domainId,
                'domain' => $data['domain'],
                'user_id' => $data['user_id'],
                'platform' => $platform,
                'status' => $status,
                'is_active' => $isActive,
                'is_verified' => $isVerified,
                'api_key' => $apiKey,
                'verification_token' => $verificationToken
            ]
        ]);
    } catch (Exception $e) {
        handleError($e);
    }
}

// Function to handle POST /domains/{id}/activate
function handleActivateDomain($id) {
    try {
        if (!is_numeric($id)) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid domain ID'
            ], 400);
        }
        
        // Get database connection
        $pdo = getDbConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update domain status
        $stmt = $pdo->prepare("
            UPDATE domains 
            SET status = 'active', is_active = true, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            throw new Exception('Domain not found');
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        jsonResponse([
            'success' => true,
            'message' => 'Domain activated successfully'
        ]);
    } catch (Exception $e) {
        handleError($e, isset($pdo) ? $pdo : null);
    }
}

// Function to handle POST /domains/{id}/deactivate
function handleDeactivateDomain($id) {
    try {
        if (!is_numeric($id)) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid domain ID'
            ], 400);
        }
        
        // Get database connection
        $pdo = getDbConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update domain status
        $stmt = $pdo->prepare("
            UPDATE domains 
            SET status = 'inactive', is_active = false, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            throw new Exception('Domain not found');
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        jsonResponse([
            'success' => true,
            'message' => 'Domain deactivated successfully'
        ]);
    } catch (Exception $e) {
        handleError($e, isset($pdo) ? $pdo : null);
    }
}

// Function to handle DELETE /domains/{id}
function handleDeleteDomain($id) {
    try {
        if (!is_numeric($id)) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid domain ID'
            ], 400);
        }
        
        // Get database connection
        $pdo = getDbConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Check if domain exists and get its user_id
        $stmt = $pdo->prepare("SELECT id, domain, user_id FROM domains WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Domain not found');
        }
        
        $domain = $stmt->fetch(PDO::FETCH_OBJ);
        
        // Clear the domain_id from the user record first
        if ($domain->user_id) {
            $stmt = $pdo->prepare("UPDATE users SET domain_id = NULL WHERE id = :user_id");
            $stmt->bindParam(':user_id', $domain->user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            error_log("Cleared domain_id from user during domain deletion - Domain ID: {$id}, User ID: {$domain->user_id}, Domain: {$domain->domain}");
        }
        
        // Delete domain
        $stmt = $pdo->prepare("DELETE FROM domains WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        error_log("Domain deleted successfully - Domain ID: {$id}, Domain: {$domain->domain}, User ID: {$domain->user_id}");
        
        // Return success response
        jsonResponse([
            'success' => true,
            'message' => 'Domain deleted successfully'
        ]);
    } catch (Exception $e) {
        handleError($e, isset($pdo) ? $pdo : null);
    }
}

// Function to handle POST /domains/{id}/verify
function handleVerifyDomain($id) {
    try {
        if (!is_numeric($id)) {
            jsonResponse([
                'success' => false,
                'message' => 'Invalid domain ID'
            ], 400);
        }
        
        // Get database connection
        $pdo = getDbConnection();
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Update domain verification status
        $stmt = $pdo->prepare("
            UPDATE domains 
            SET is_verified = true, updated_at = CURRENT_TIMESTAMP 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Check if any rows were affected
        if ($stmt->rowCount() === 0) {
            throw new Exception('Domain not found');
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Return success response
        jsonResponse([
            'success' => true,
            'message' => 'Domain verified successfully'
        ]);
    } catch (Exception $e) {
        handleError($e, isset($pdo) ? $pdo : null);
    }
} 
