<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing user repository functionality...\n";

// Get an instance of the UserRepository
$userRepository = app(\App\Repositories\UserRepository::class);

try {
    // Test fetching users with various filters
    $allUsers = $userRepository->getUsers([]);
    echo "Total users: " . $allUsers['totalUsers'] . "\n";
    
    // Fetch admin users
    $adminUsers = $userRepository->getUsers(['role' => 'admin']);
    echo "Admin users: " . $adminUsers['totalUsers'] . "\n";
    
    // Fetch inactive users
    $inactiveUsers = $userRepository->getUsers(['status' => 'inactive']);
    echo "Inactive users: " . $inactiveUsers['totalUsers'] . "\n";
    
    // Fetch with search
    $searchUsers = $userRepository->getUsers(['search' => 'Test']);
    echo "Users matching 'Test': " . $searchUsers['totalUsers'] . "\n";
    
    // Output the first user details
    if (count($allUsers['users']) > 0) {
        $firstUser = $allUsers['users'][0];
        echo "\nFirst user details:\n";
        echo "Name: " . $firstUser->resource->name . "\n";
        echo "Email: " . $firstUser->resource->email . "\n";
        
        // Handle role as enum
        $roleValue = is_object($firstUser->resource->role) ? $firstUser->resource->role->value : $firstUser->resource->role;
        echo "Role: " . $roleValue . "\n";
        
        // Handle status as enum
        $statusValue = is_object($firstUser->resource->status) ? $firstUser->resource->status->value : $firstUser->resource->status;
        echo "Status: " . $statusValue . "\n";
    }
    
    // Show all users
    echo "\nAll users:\n";
    foreach ($allUsers['users'] as $index => $user) {
        $roleValue = is_object($user->resource->role) ? $user->resource->role->value : $user->resource->role;
        $statusValue = is_object($user->resource->status) ? $user->resource->status->value : $user->resource->status;
        
        echo ($index + 1) . ". " . $user->resource->name . " (" . $user->resource->email . ") - Role: " . $roleValue . ", Status: " . $statusValue . "\n";
    }
    
    echo "\nTest completed successfully.";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString();
} 