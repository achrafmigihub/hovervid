<?php
/**
 * Direct User Suspension Script
 * 
 * This file provides direct access to suspend/unsuspend users
 * bypassing Laravel routing to ensure compatibility across environments.
 */

// Bootstrap Laravel
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = \Illuminate\Http\Request::capture()
);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Auth-Token, Authorization, X-Requested-With, X-CSRF-TOKEN');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 200 OK');
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed',
    ]);
    exit;
}

try {
    // Get the user ID from the request
    $userId = $_GET['id'] ?? null;
    
    if (!$userId) {
        echo json_encode([
            'success' => false,
            'message' => 'User ID is required',
        ]);
        exit;
    }
    
    // Get action (suspend or unsuspend)
    $action = $_GET['action'] ?? 'suspend';
    if (!in_array($action, ['suspend', 'unsuspend'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action. Must be "suspend" or "unsuspend"',
        ]);
        exit;
    }
    
    // Initialize Laravel application
    $app->instance('request', $request);
    $app->boot();
    
    // Get the User model
    $userModel = \App\Models\User::find($userId);
    
    if (!$userModel) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
        ]);
        exit;
    }
    
    // Log action
    error_log("Processing {$action} for user ID: {$userId}");
    
    // Check if user is trying to suspend themselves
    $currentUser = \Illuminate\Support\Facades\Auth::user();
    if ($currentUser && $currentUser->id == $userId && $action == 'suspend') {
        echo json_encode([
            'success' => false,
            'message' => 'You cannot suspend your own account',
        ]);
        exit;
    }
    
    // Perform the action
    if ($action === 'suspend') {
        try {
            // Force logout all of the user's sessions
            \App\Models\Session::where('user_id', $userId)->update([
                'is_active' => false
            ]);
        } catch (\Exception $e) {
            // Log the error but continue with suspension
            error_log('Error updating sessions: ' . $e->getMessage());
        }
        
        $userModel->update([
            'is_suspended' => true,
            'status' => 'suspended'
        ]);
        $message = 'User suspended successfully';
    } else {
        // Default to inactive status unless we confirm active sessions
        $status = 'inactive';
        
        try {
            // Before unsuspending, check if the user has any active sessions
            $hasActiveSessions = \App\Models\Session::where('user_id', $userId)
                ->where('is_active', true)
                ->exists();
                
            // Only set to active if they have confirmed active sessions
            if ($hasActiveSessions) {
                $status = 'active';
            }
        } catch (\Exception $e) {
            // Log the error but continue with unsuspension using default inactive status
            error_log('Error checking sessions: ' . $e->getMessage());
        }
            
        $userModel->update([
            'is_suspended' => false,
            'status' => $status
        ]);
        $message = 'User unsuspended successfully';
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $message,
        'user' => [
            'id' => $userModel->id,
            'name' => $userModel->name,
            'email' => $userModel->email,
            'role' => $userModel->role,
            'status' => $userModel->status,
            'is_suspended' => $userModel->is_suspended,
            'created_at' => $userModel->created_at,
            'updated_at' => $userModel->updated_at
        ],
    ]);
    
} catch (\Exception $e) {
    // Log error
    error_log('Error in user suspension process: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing the user suspension',
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
    ]);
} 
