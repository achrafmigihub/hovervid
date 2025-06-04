<?php
/**
 * Direct User Update Script
 * 
 * This file provides direct access to update user information
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
header('Access-Control-Max-Age: 86400');
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
        'message' => 'Method not allowed. Use POST.',
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

    // Get request body data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data provided',
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
    error_log("Updating user ID: {$userId} with data: " . json_encode($data));

    // Validate and prepare update data
    $updateData = [];
    
    // Validate name
    if (isset($data['name'])) {
        $name = trim($data['name']);
        if (empty($name)) {
            echo json_encode([
                'success' => false,
                'message' => 'Name cannot be empty',
                'errors' => ['name' => ['Name is required']]
            ]);
            exit;
        }
        $updateData['name'] = $name;
    }

    // Validate email
    if (isset($data['email'])) {
        $email = trim($data['email']);
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email address',
                'errors' => ['email' => ['Please provide a valid email address']]
            ]);
            exit;
        }
        
        // Check if email is already taken by another user
        $existingUser = \App\Models\User::where('email', $email)->where('id', '!=', $userId)->first();
        if ($existingUser) {
            echo json_encode([
                'success' => false,
                'message' => 'Email address already taken',
                'errors' => ['email' => ['This email is already in use']]
            ]);
            exit;
        }
        
        $updateData['email'] = $email;
    }

    // Validate role
    if (isset($data['role'])) {
        $role = trim($data['role']);
        if (!in_array($role, ['admin', 'client'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid role',
                'errors' => ['role' => ['Role must be admin or client']]
            ]);
            exit;
        }
        $updateData['role'] = $role;
    }

    // Validate status (for admin users) or plan (for client users)
    if (isset($data['status']) && $data['role'] !== 'client') {
        $status = trim($data['status']);
        $validStatuses = ['active', 'inactive', 'pending', 'banned', 'suspended'];
        if (!in_array($status, $validStatuses)) {
            echo json_encode([
                'success' => false,
                'message' => 'Invalid status',
                'errors' => ['status' => ['Status must be one of: ' . implode(', ', $validStatuses)]]
            ]);
            exit;
        }
        $updateData['status'] = $status;
        
        // Handle suspension logic
        if ($status === 'suspended') {
            $updateData['is_suspended'] = true;
        } else {
            $updateData['is_suspended'] = false;
        }
    }

    // Handle plan for client users (this might require additional logic based on your plan system)
    if (isset($data['plan']) && $data['role'] === 'client') {
        // For now, we'll just log this as plans might be handled differently
        error_log("Plan update requested for client: " . $data['plan']);
    }

    // Update the user
    $userModel->update($updateData);
    
    // Refresh the model to get updated data
    $userModel->refresh();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully',
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
    error_log('Error in user update process: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the user',
        'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
    ]);
} 
