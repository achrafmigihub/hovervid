<?php

/**
 * Direct script to check user suspension status
 * This bypasses routing and middleware for a direct check
 * Checks if a user is suspended
 */

// Bootstrap the Laravel application
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = Illuminate\Http\Request::capture();

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Session-Check, X-Client-Fingerprint, Cache-Control, Pragma, Expires');
header('Access-Control-Max-Age: 86400');

// Return JSON
header('Content-Type: application/json');

// Add no-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Get the authenticated user
try {
    // Check if this is a session check (coming from SuspendedUserModal)
    $isSessionCheck = !empty($_SERVER['HTTP_X_SESSION_CHECK']);
    $clientFingerprint = $_SERVER['HTTP_X_CLIENT_FINGERPRINT'] ?? null;
    $updateStatus = !empty($_GET['updateStatus']);
    
    // Get user from session
    $user = null;
    $authGuard = \Illuminate\Support\Facades\Auth::guard('web');
    
    if ($authGuard->check()) {
        $user = $authGuard->user();
    } 
    // Try sanctum token if available
    else if (!empty($_SERVER['HTTP_AUTHORIZATION']) && strpos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
        $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
    }
    
    if (!$user) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated',
            'is_suspended' => false
        ]);
        exit;
    }
    
    // Force a fresh database query to get the latest user data
    $freshUser = \App\Models\User::find($user->id);
    
    if (!$freshUser) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found in database',
            'is_suspended' => false
        ]);
        exit;
    }
    
    // Check if user is suspended
    $isSuspended = $freshUser->is_suspended || strtolower($freshUser->status ?? '') === 'suspended';
    
    // Log the check
    \Illuminate\Support\Facades\Log::info('Suspension status check', [
        'user_id' => $freshUser->id,
        'user_email' => $freshUser->email,
        'is_suspended' => $isSuspended,
        'user_status' => $freshUser->status,
        'session_check' => $isSessionCheck,
        'update_status' => $updateStatus
    ]);
    
    // Return the result
    echo json_encode([
        'status' => 'success',
        'timestamp' => time(),
        'is_suspended' => $isSuspended,
        'session_check' => $isSessionCheck,
        'status_updated' => false, // We're not updating status in this script
        'user_data' => [
            'id' => $freshUser->id,
            'email' => $freshUser->email,
            'name' => $freshUser->name,
            'role' => $freshUser->role,
            'status' => $freshUser->status,
            'is_suspended' => $freshUser->is_suspended
        ],
        'message' => $isSuspended ? 
            'Your account has been suspended. Please contact administration for assistance.' : 
            'User account is active.'
    ]);
    
} catch (\Exception $e) {
    // Log the error
    \Illuminate\Support\Facades\Log::error('Error in suspension status check', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check suspension status: ' . $e->getMessage(),
        'is_suspended' => false
    ]);
} 
