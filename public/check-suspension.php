<?php

/**
 * Direct script to check user suspension status
 * This bypasses routing and middleware for a direct check
 * Now also handles user status updates and session tracking
 */

// Bootstrap the Laravel application
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$request = Illuminate\Http\Request::capture();

// Add CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Session-Check, X-Client-Fingerprint');
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
    $shouldUpdateStatus = isset($_GET['updateStatus']) && $_GET['updateStatus'] === 'true';
    
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
    
    $statusUpdated = false;
    
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
    $isSuspended = $freshUser->is_suspended || $freshUser->status === 'suspended';
    
    // If not suspended and this is a session check with an update request, update the user status
    if (!$isSuspended && $isSessionCheck && $shouldUpdateStatus) {
        // Only update status to active if it's not already active
        if ($freshUser->status !== 'active') {
            $freshUser->status = 'active';
            $freshUser->save();
            $statusUpdated = true;
            
            // Log the status update
            \Illuminate\Support\Facades\Log::info('User status updated via check-suspension.php', [
                'user_id' => $freshUser->id,
                'status' => 'active'
            ]);
        }
        
        // Record the session fingerprint if provided
        if ($clientFingerprint) {
            try {
                // Get the current session ID
                $sessionId = \Illuminate\Support\Facades\Session::getId();
                
                // Update the session record in the sessions table
                \Illuminate\Support\Facades\DB::table('sessions')
                    ->where('id', $sessionId)
                    ->update([
                        'fingerprint' => $clientFingerprint,
                        'last_activity' => time(),
                        'is_active' => true
                    ]);
                
                // Log session update
                \Illuminate\Support\Facades\Log::info('Session fingerprint updated', [
                    'session_id' => $sessionId,
                    'user_id' => $freshUser->id
                ]);
            } catch (\Exception $e) {
                // Log the error but continue
                \Illuminate\Support\Facades\Log::warning('Failed to update session fingerprint', [
                    'error' => $e->getMessage(),
                    'user_id' => $freshUser->id
                ]);
            }
        }
    }
    
    // Log the check
    \Illuminate\Support\Facades\Log::info('Suspension check', [
        'user_id' => $freshUser->id,
        'is_suspended' => $isSuspended,
        'session_check' => $isSessionCheck,
        'status_updated' => $statusUpdated
    ]);
    
    // Return the result
    echo json_encode([
        'status' => 'success',
        'timestamp' => time(),
        'is_suspended' => $isSuspended,
        'status_updated' => $statusUpdated,
        'session_check' => $isSessionCheck,
        'user_data' => [
            'id' => $freshUser->id,
            'email' => $freshUser->email,
            'name' => $freshUser->name,
            'status' => $freshUser->status,
            'is_suspended' => $freshUser->is_suspended,
            'role' => $freshUser->role
        ],
        'message' => $isSuspended ? 'Your account has been suspended. Please contact administration for assistance.' : 'Account is active'
    ]);
    
} catch (\Exception $e) {
    // Log the error
    \Illuminate\Support\Facades\Log::error('Error in suspension check', [
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
