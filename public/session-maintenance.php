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

// Database configuration
$host = '127.0.0.1';
$port = '5432';
$dbname = 'hovervid_db';
$username = 'postgres';
$password = 'postgres_hovervid';

// Create PDO connection
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
$pdo = new PDO($dsn, $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Initialize global variables
$now = time();
$hourAgo = $now - 3600;
$dayAgo = $now - 86400;
$weekAgo = $now - 604800;
$sessionLifetime = 7200; // Session lifetime in seconds (2 hours)

// Helper function to format PostgreSQL timestamp
function pgTimestamp($timestampInSeconds) {
    return date('Y-m-d H:i:s', $timestampInSeconds);
}

// Helper function to fix a single session
function fixSession($pdo, $id) {
    global $now, $sessionLifetime;
    
    // Get the session data
    $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$session) {
        return ['success' => false, 'error' => 'Session not found'];
    }
    
    // Handle NULL or invalid last_activity
    $lastActivity = null;
    if (!empty($session['last_activity'])) {
        if (is_numeric($session['last_activity'])) {
            // Already a timestamp
            $lastActivity = (int)$session['last_activity'];
        } else {
            // Try to convert from PostgreSQL timestamp
            $lastActivity = strtotime($session['last_activity']);
        }
    }
    
    // If conversion failed or empty, use default
    if (!$lastActivity) {
        $lastActivity = $now - 43200; // Default to 12 hours ago
    }
    
    // Calculate if session is active based on last activity
    $isActive = $lastActivity >= ($now - 3600); // Active if used in the last hour

    // Extract device info from user agent if available
    $userAgent = $session['user_agent'] ?? '';
    $deviceInfo = extractDeviceInfo($userAgent);

    // Update the session with correct values
    try {
        $stmt = $pdo->prepare("
            UPDATE sessions 
            SET 
                is_active = :is_active,
                created_at = :created_at,
                updated_at = :updated_at,
                device_info = :device_info
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':is_active' => $isActive,
            ':created_at' => pgTimestamp($lastActivity - 86400), // 1 day before last activity
            ':updated_at' => pgTimestamp($lastActivity),
            ':device_info' => $deviceInfo,
            ':id' => $id
        ]);
        
        // Update expires_at separately
        $stmt = $pdo->prepare("UPDATE sessions SET expires_at = to_timestamp(:expires) WHERE id = :id");
        $stmt->execute([
            ':expires' => $lastActivity + $sessionLifetime,
            ':id' => $id
        ]);
        
        return [
            'id' => $id,
            'success' => true,
            'is_active' => $isActive,
            'device_info' => $deviceInfo
        ];
    } catch (Exception $e) {
        return [
            'id' => $id,
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

// Helper function to extract device info from user agent
function extractDeviceInfo($userAgent) {
    $deviceInfo = [];
    
    // Detect browser
    if (strpos($userAgent, 'Chrome') !== false) {
        $deviceInfo['browser'] = 'Chrome';
    } elseif (strpos($userAgent, 'Firefox') !== false) {
        $deviceInfo['browser'] = 'Firefox';
    } elseif (strpos($userAgent, 'Safari') !== false) {
        $deviceInfo['browser'] = 'Safari';
    } elseif (strpos($userAgent, 'Edge') !== false || strpos($userAgent, 'Edg') !== false) {
        $deviceInfo['browser'] = 'Edge';
    } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
        $deviceInfo['browser'] = 'Internet Explorer';
    } else {
        $deviceInfo['browser'] = 'Unknown';
    }
    
    // Detect operating system
    if (strpos($userAgent, 'Windows') !== false) {
        $deviceInfo['os'] = 'Windows';
    } elseif (strpos($userAgent, 'Macintosh') !== false || strpos($userAgent, 'Mac OS') !== false) {
        $deviceInfo['os'] = 'macOS';
    } elseif (strpos($userAgent, 'Linux') !== false) {
        $deviceInfo['os'] = 'Linux';
    } elseif (strpos($userAgent, 'iPhone') !== false) {
        $deviceInfo['os'] = 'iOS';
        $deviceInfo['device'] = 'iPhone';
    } elseif (strpos($userAgent, 'iPad') !== false) {
        $deviceInfo['os'] = 'iOS';
        $deviceInfo['device'] = 'iPad';
    } elseif (strpos($userAgent, 'Android') !== false) {
        $deviceInfo['os'] = 'Android';
    } else {
        $deviceInfo['os'] = 'Unknown';
    }
    
    // Detect if mobile
    if (strpos($userAgent, 'Mobile') !== false || 
        strpos($userAgent, 'Android') !== false || 
        strpos($userAgent, 'iPhone') !== false || 
        strpos($userAgent, 'iPad') !== false) {
        $deviceInfo['type'] = 'Mobile';
    } else {
        $deviceInfo['type'] = 'Desktop';
    }
    
    return json_encode($deviceInfo);
}

// Process based on request method
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get query parameters
        $action = $_GET['action'] ?? 'status';
        
        if ($action === 'status') {
            // Count total sessions
            $stmt = $pdo->query("SELECT COUNT(*) FROM sessions");
            $totalSessions = $stmt->fetchColumn();
            
            // Count sessions with NULL values
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM sessions 
                WHERE expires_at IS NULL 
                OR created_at IS NULL 
                OR updated_at IS NULL
            ");
            $sessionsWithNullColumns = $stmt->fetchColumn();
            
            // Count incorrect active sessions
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM sessions 
                WHERE is_active = TRUE 
                AND last_activity < :hour_ago
            ");
            $stmt->execute([':hour_ago' => $hourAgo]);
            $incorrectActiveSessions = $stmt->fetchColumn();
            
            // Count active sessions
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM sessions 
                WHERE last_activity >= :hour_ago
            ");
            $stmt->execute([':hour_ago' => $hourAgo]);
            $activeSessions = $stmt->fetchColumn();
            
            // Return status information
            echo json_encode([
                'success' => true,
                'total_sessions' => (int)$totalSessions,
                'sessions_with_null_columns' => (int)$sessionsWithNullColumns,
                'incorrect_active_sessions' => (int)$incorrectActiveSessions,
                'active_sessions' => (int)$activeSessions,
            ]);
        }
        elseif ($action === 'fix-all') {
            // Get all sessions
            $stmt = $pdo->query("SELECT * FROM sessions");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fixed = 0;
            $errors = [];
            
            foreach ($sessions as $session) {
                $result = fixSession($pdo, $session['id']);
                if ($result['success']) {
                    $fixed++;
                } else {
                    $errors[] = $result;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fixed $fixed sessions",
                'fixed_count' => $fixed,
                'errors' => $errors,
            ]);
        }
        elseif ($action === 'fix-nulls') {
            // Fix only sessions with NULL values
            $stmt = $pdo->query("
                SELECT * FROM sessions 
                WHERE expires_at IS NULL 
                OR created_at IS NULL 
                OR updated_at IS NULL
            ");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fixed = 0;
            $errors = [];
            
            foreach ($sessions as $session) {
                $result = fixSession($pdo, $session['id']);
                if ($result['success']) {
                    $fixed++;
                } else {
                    $errors[] = $result;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fixed $fixed sessions with NULL columns",
                'fixed_count' => $fixed,
                'errors' => $errors,
            ]);
        }
        elseif ($action === 'fix-active-status') {
            // Fix only sessions with incorrect active status
            $stmt = $pdo->prepare("
                SELECT * FROM sessions 
                WHERE is_active = TRUE 
                AND last_activity < :hour_ago
            ");
            $stmt->execute([':hour_ago' => $hourAgo]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $fixed = 0;
            $errors = [];
            
            foreach ($sessions as $session) {
                $result = fixSession($pdo, $session['id']);
                if ($result['success']) {
                    $fixed++;
                } else {
                    $errors[] = $result;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Fixed $fixed sessions with incorrect active status",
                'fixed_count' => $fixed,
                'errors' => $errors,
            ]);
        }
        elseif ($action === 'cleanup') {
            // Delete expired sessions
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE last_activity < :week_ago");
            $stmt->execute([':week_ago' => $weekAgo]);
            $deleted = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Deleted $deleted old sessions (older than 1 week)",
                'deleted_count' => $deleted,
            ]);
        }
        elseif ($action === 'update-device-info') {
            // Update device_info and is_active for all sessions
            $stmt = $pdo->query("SELECT * FROM sessions");
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $updated = 0;
            $errors = [];
            
            foreach ($sessions as $session) {
                $result = fixSession($pdo, $session['id']);
                if ($result['success']) {
                    $updated++;
                } else {
                    $errors[] = $result;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Updated device_info and is_active status for $updated sessions",
                'updated_count' => $updated,
                'errors' => $errors,
            ]);
        }
        elseif ($action === 'list') {
            // List sessions with details
            $limit = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT 
                    s.*, 
                    u.email, 
                    u.name as user_name
                FROM sessions s
                LEFT JOIN users u ON s.user_id = u.id
                ORDER BY last_activity DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $sessions = [];
            while ($session = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Convert last_activity to timestamp if needed
                $lastActivity = null;
                if (!empty($session['last_activity'])) {
                    if (is_numeric($session['last_activity'])) {
                        $lastActivity = (int)$session['last_activity'];
                    } else {
                        $lastActivity = strtotime($session['last_activity']);
                    }
                }
                
                // Default if conversion failed
                if (!$lastActivity) {
                    $lastActivity = $now - 43200; // 12 hours ago
                }
                
                $isActive = $lastActivity >= ($now - 3600); // Active if within last hour
                
                $sessions[] = [
                    'id' => $session['id'],
                    'user_id' => $session['user_id'],
                    'user_email' => $session['email'] ?? null,
                    'user_name' => $session['user_name'] ?? null,
                    'ip_address' => $session['ip_address'],
                    'user_agent' => $session['user_agent'],
                    'is_active' => $session['is_active'] === 't' || $session['is_active'] === true,
                    'active_status' => $isActive ? 'active' : 'inactive',
                    'last_activity' => $lastActivity,
                    'last_activity_human' => date('Y-m-d H:i:s', $lastActivity),
                    'created_at' => $session['created_at'],
                    'updated_at' => $session['updated_at'],
                    'expires_at' => $session['expires_at'],
                    'device_info' => json_decode($session['device_info'] ?? '{}', true)
                ];
            }
            
            // Get total count
            $stmt = $pdo->query("SELECT COUNT(*) FROM sessions");
            $total = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'sessions' => $sessions,
                'total' => (int)$total,
            ]);
        }
        elseif ($action === 'sync-active-status') {
            // Sync is_active status with current active status (within last hour)
            $stmt = $pdo->prepare("
                UPDATE sessions
                SET is_active = (last_activity >= :hour_ago)
                RETURNING id
            ");
            $stmt->execute([':hour_ago' => $hourAgo]);
            $updated = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'message' => "Synchronized is_active status for $updated sessions",
                'updated_count' => $updated,
            ]);
        }
        else {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified',
            ]);
        }
    } catch (Exception $e) {
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while managing sessions.',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
} else {
    // Return method not allowed response
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Only GET requests are supported.',
    ]);
} 