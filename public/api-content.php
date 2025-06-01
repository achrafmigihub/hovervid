<?php

// Set response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Authorization');

// Handle OPTIONS preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Bootstrap Laravel application
require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

// Create a kernel instance
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create a Laravel request from the current request
    $request = Illuminate\Http\Request::capture();
    
    // Override the request URI to point to our API endpoint
    $request->server->set('REQUEST_URI', '/api/content');
    $request->server->set('PATH_INFO', '/api/content');
    
    try {
        // Process the request through Laravel
        $response = $kernel->handle($request);
        
        // Send the response
        http_response_code($response->getStatusCode());
        
        // Set headers
        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        
        echo $response->getContent();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing the request'
        ]);
    }
}

elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Create a Laravel request from the current request
    $request = Illuminate\Http\Request::capture();
    
    // Override the request URI to point to our API endpoint
    $request->server->set('REQUEST_URI', '/api/content?' . ($_SERVER['QUERY_STRING'] ?? ''));
    $request->server->set('PATH_INFO', '/api/content');
    
    try {
        // Process the request through Laravel
        $response = $kernel->handle($request);
        
        // Send the response
        http_response_code($response->getStatusCode());
        
        // Set headers
        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        
        echo $response->getContent();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred while processing the request'
        ]);
    }
}

else {
    // Method not allowed
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?> 
