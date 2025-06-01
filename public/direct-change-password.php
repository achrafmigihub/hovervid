<?php
/**
 * Direct Password Change Script
 * 
 * This script provides a direct way to change a user's password
 * bypassing the Laravel API routes for compatibility across environments.
 */

// Bootstrap Laravel application
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
    
    // Get JSON payload
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    
    // Validate request data
    if (!isset($data['password']) || empty($data['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Password is required',
        ]);
        exit;
    }
    
    if (!isset($data['password_confirmation']) || $data['password'] !== $data['password_confirmation']) {
        echo json_encode([
            'success' => false,
            'message' => 'Password confirmation does not match',
        ]);
        exit;
    }
    
    if (strlen($data['password']) < 8) {
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 8 characters',
        ]);
        exit;
    }
    
    // Initialize Laravel application
    $app->instance('request', $request);
    $app->boot();
    
    // Check if admin is authenticated
    $currentUser = \Illuminate\Support\Facades\Auth::user();
    if (!$currentUser) {
        echo json_encode([
            'success' => false,
            'message' => 'You must be logged in to change a password',
        ]);
        exit;
    }
    
    if ($currentUser->role !== 'admin') {
        echo json_encode([
            'success' => false,
            'message' => 'Only administrators can change user passwords',
        ]);
        exit;
    }
    
    // Get the user model
    $userModel = \App\Models\User::find($userId);
    
    if (!$userModel) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found',
        ]);
        exit;
    }
    
    // Hash the new password
    $hashedPassword = \Illuminate\Support\Facades\Hash::make($data['password']);
    
    // Update the user's password
    $userModel->update([
        'password' => $hashedPassword,
    ]);
    
    // Log the password change
    \Illuminate\Support\Facades\Log::info('User password changed by admin via direct script', [
        'admin_id' => $currentUser->id,
        'user_id' => $userModel->id,
        'user_email' => $userModel->email
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully',
        'user' => [
            'id' => $userModel->id,
            'name' => $userModel->name,
            'email' => $userModel->email,
            'role' => $userModel->role,
            'status' => $userModel->status
        ],
    ]);
    
} catch (\Exception $e) {
    // Log error
    \Illuminate\Support\Facades\Log::error('Error in direct password change process: ' . $e->getMessage(), [
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while changing the password',
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
    ]);
} 
