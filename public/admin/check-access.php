<?php
// Simple file to check direct PHP access
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'PHP access check successful',
    'time' => date('c'),
    'path' => $_SERVER['REQUEST_URI'],
    'method' => $_SERVER['REQUEST_METHOD'],
    'dir' => __DIR__,
    'php_version' => phpversion()
]); 
