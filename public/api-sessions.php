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
        
        // Get current timestamp and 24 hours ago timestamp
        $now = time();
        $hourAgo = $now - 3600;
        $yesterday = $now - 86400; // 24 hours ago
        $weekAgo = $now - (86400 * 7); // Last 7 days
        
        // Calculate total sessions
        $stmt = $pdo->query("SELECT COUNT(*) FROM sessions");
        $totalSessions = $stmt->fetchColumn();
        
        // Calculate active sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE is_active = TRUE");
        $stmt->execute();
        $activeSessions = $stmt->fetchColumn();
        
        // Get count of active users from users table status column
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE LOWER(status) = 'active'");
        $stmt->execute();
        $activeUsers = $stmt->fetchColumn();
        
        // Recent sessions in last 24 hours
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE last_activity >= ?");
        $stmt->execute([$yesterday]);
        $recentSessions = $stmt->fetchColumn();
        
        // Get sessions grouped by user role
        $sessionsByRole = [];
        try {
            $stmt = $pdo->query("
                SELECT users.role, COUNT(*) as count 
                FROM sessions 
                JOIN users ON sessions.user_id = users.id 
                WHERE sessions.user_id IS NOT NULL 
                GROUP BY users.role
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $sessionsByRole[$row['role']] = (int)$row['count'];
            }
        } catch (Exception $e) {
            // If joining with users table fails, continue with empty sessions by role
        }
        
        // Get guest sessions (sessions without user_id)
        $stmt = $pdo->query("SELECT COUNT(*) FROM sessions WHERE user_id IS NULL");
        $guestSessions = $stmt->fetchColumn();
        
        // Get device statistics from device_info column 
        $deviceStats = [
            'browsers' => [],
            'operating_systems' => [],
            'device_types' => []
        ];
        
        try {
            // Get browser statistics
            $stmt = $pdo->query("
                SELECT 
                    jsonb_extract_path_text(device_info::jsonb, 'browser') as browser,
                    COUNT(*) as count
                FROM sessions
                WHERE device_info IS NOT NULL
                GROUP BY browser
                ORDER BY count DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['browser']) {
                    $deviceStats['browsers'][$row['browser']] = (int)$row['count']; 
                }
            }
            
            // Get OS statistics
            $stmt = $pdo->query("
                SELECT 
                    jsonb_extract_path_text(device_info::jsonb, 'os') as os,
                    COUNT(*) as count
                FROM sessions
                WHERE device_info IS NOT NULL
                GROUP BY os
                ORDER BY count DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['os']) {
                    $deviceStats['operating_systems'][$row['os']] = (int)$row['count'];
                }
            }
            
            // Get device type statistics
            $stmt = $pdo->query("
                SELECT 
                    jsonb_extract_path_text(device_info::jsonb, 'type') as type,
                    COUNT(*) as count
                FROM sessions
                WHERE device_info IS NOT NULL
                GROUP BY type
                ORDER BY count DESC
            ");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['type']) {
                    $deviceStats['device_types'][$row['type']] = (int)$row['count'];
                }
            }
        } catch (Exception $e) {
            // If device info statistics fail, continue with empty stats
            error_log("Error fetching device statistics: " . $e->getMessage());
        }
        
        // Get session by date for a chart
        $stmt = $pdo->prepare("
            SELECT 
                to_char(to_timestamp(last_activity), 'YYYY-MM-DD') as date, 
                COUNT(*) as count 
            FROM sessions 
            WHERE last_activity >= ? 
            GROUP BY date 
            ORDER BY date ASC
        ");
        $stmt->execute([$weekAgo]);
        $sessionsByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return session statistics
        echo json_encode([
            'success' => true,
            'total_sessions' => (int)$totalSessions,
            'total_active_sessions' => (int)$activeSessions,
            'active_users' => (int)$activeUsers,
            'sessions_last_24_hours' => (int)$recentSessions,
            'guest_sessions' => (int)$guestSessions,
            'sessions_by_role' => $sessionsByRole,
            'sessions_by_date' => $sessionsByDate,
            'device_stats' => $deviceStats,
            'timestamp' => $now,
        ]);
        
    } catch (Exception $e) {
        // Return error response
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while fetching session statistics.',
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
