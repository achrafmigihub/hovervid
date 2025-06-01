<?php

/**
 * This is a simple test script to toggle the suspended state of a user for testing.
 * Remove this file after testing is complete.
 */

// Bootstrap the Laravel application
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Create a request instance
$request = Illuminate\Http\Request::capture();

// Get user ID from the URL
$userId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? 'toggle';

if (!$userId) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required (usage: /test-suspend.php?id=1&action=toggle|suspend|unsuspend)'
    ]);
    exit;
}

try {
    // Find the user
    $user = \App\Models\User::find($userId);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Get current suspend status
    $isSuspended = $user->is_suspended || $user->status === 'suspended';
    
    // Determine action
    $performSuspend = $action === 'toggle' ? !$isSuspended : ($action === 'suspend');
    
    // Update user
    if ($performSuspend) {
        $user->update([
            'is_suspended' => true,
            'status' => 'suspended'
        ]);
        $message = 'User has been suspended';
    } else {
        $user->update([
            'is_suspended' => false,
            'status' => 'active'
        ]);
        $message = 'User has been unsuspended';
    }
    
    // Return result
    echo json_encode([
        'success' => true,
        'message' => $message,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'is_suspended' => $user->is_suspended
        ]
    ]);
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} 
