<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

echo "Creating test client user...\n";

// Check if user already exists
$user = User::where('email', 'client@example.com')->first();

if (!$user) {
    $user = User::create([
        'name' => 'Test Client',
        'email' => 'client@example.com',
        'password' => bcrypt('password123'),
        'role' => 'client',
        'status' => 'active'
    ]);
    echo "Created test client user with ID: {$user->id}\n";
} else {
    echo "Test client user already exists with ID: {$user->id}\n";
}

echo "User domain_id: " . ($user->domain_id ?? 'null') . "\n";
echo "User role: " . $user->role->value . "\n";
echo "User status: " . $user->status->value . "\n";

echo "Test user ready!\n"; 
