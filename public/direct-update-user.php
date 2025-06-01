<?php
/**
 * Direct User Update Script
 * 
 * This file provides direct access to update user information in the database
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
    
    // Get input data 
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    if (!$inputData) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input data',
        ]);
        exit;
    }
    
    // Debug info
    error_log('Processing user update for ID: ' . $userId);
    error_log('Input data: ' . json_encode($inputData));
    
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
    
    error_log('Found user: ' . $userModel->name . ' (' . $userModel->email . ')');
    
    // Validate input using Laravel validation
    $updateRules = \App\Models\User::updateRules($userId);
    error_log('Validation rules: ' . json_encode($updateRules));
    
    $validator = \Illuminate\Support\Facades\Validator::make($inputData, $updateRules);
    
    if ($validator->fails()) {
        $errors = $validator->errors()->toArray();
        error_log('Validation failed: ' . json_encode($errors));
        
        echo json_encode([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $errors,
        ]);
        exit;
    }
    
    // Get validated data
    $validatedData = $validator->validated();
    error_log('Validated data: ' . json_encode($validatedData));
    
    // Update user with validated data
    try {
        $userModel->update($validatedData);
        error_log('User basic info updated successfully');
    } catch (\Exception $e) {
        error_log('Error updating user basic info: ' . $e->getMessage());
        throw $e;
    }
    
    // Handle plan update for client users
    if ($userModel->role === 'client' && isset($inputData['plan'])) {
        try {
            error_log('Updating plan for client user to: ' . $inputData['plan']);
            
            // Get or create Plan record
            $planModel = \App\Models\Plan::where('name', $inputData['plan'])->first();
            
            if (!$planModel) {
                error_log('Plan not found, creating new Plan record');
                $planModel = new \App\Models\Plan();
                $planModel->name = $inputData['plan'];
                $planModel->price = 0; // Default price
                $planModel->duration = 'month'; // Default duration
                $planModel->features = json_encode(["Basic features"]); // Default features
                $planModel->save();
                error_log('Created new Plan with ID: ' . $planModel->id);
            } else {
                error_log('Found existing Plan: ' . $planModel->id);
            }
            
            // Find or create subscription
            $subscription = $userModel->subscriptions()->latest()->first();
            
            if (!$subscription) {
                error_log('No existing subscription found, creating new one');
                // Create new subscription if none exists
                $subscription = new \App\Models\Subscription();
                $subscription->user_id = $userModel->id;
                $subscription->plan_id = $planModel->id;
                $subscription->status = 'active';
                $subscription->started_at = now();
                $subscription->expires_at = now()->addMonth();
            } else {
                error_log('Found existing subscription: ' . $subscription->id);
                $subscription->plan_id = $planModel->id;
            }
            
            // Save subscription
            $subscription->save();
            error_log('Subscription updated successfully');
            
        } catch (\Exception $e) {
            // Log error but continue with user update
            error_log('Error updating subscription plan: ' . $e->getMessage());
            error_log('Trace: ' . $e->getTraceAsString());
        }
    }
    
    // Get updated user with subscription data
    $updatedUser = \App\Models\User::with(['subscriptions.plan'])->find($userId);
    $latestSubscription = $updatedUser->subscriptions()
        ->with('plan')
        ->orderBy('started_at', 'desc')
        ->first();
    
    // Prepare plan data for response
    $planData = null;
    if ($latestSubscription && $latestSubscription->plan) {
        $planData = [
            'name' => $latestSubscription->plan->name,
            'price' => $latestSubscription->plan->price,
            'duration' => $latestSubscription->plan->duration,
            'features' => is_array($latestSubscription->plan->features) 
                ? $latestSubscription->plan->features 
                : json_decode($latestSubscription->plan->features, true)
        ];
    }
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'User updated successfully',
        'user' => [
            'id' => $updatedUser->id,
            'name' => $updatedUser->name,
            'email' => $updatedUser->email,
            'role' => $updatedUser->role,
            'status' => $updatedUser->status,
            'created_at' => $updatedUser->created_at,
            'updated_at' => $updatedUser->updated_at,
            'plan' => $planData
        ],
    ];
    
    error_log('Sending success response: ' . json_encode($response));
    echo json_encode($response);
    
} catch (\Exception $e) {
    // Log detailed error
    error_log('Exception in direct-update-user.php: ' . $e->getMessage());
    error_log('Error file: ' . $e->getFile() . ' on line ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating user information: ' . $e->getMessage(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ]);
} 
