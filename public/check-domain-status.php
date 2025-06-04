<?php

/**
 * Direct script to check user domain status
 * This bypasses routing and middleware for a direct check
 * Checks if a client user has an active domain in the domains table
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
    // Check if this is a session check (coming from DomainStatusChecker)
    $isSessionCheck = !empty($_SERVER['HTTP_X_SESSION_CHECK']);
    $clientFingerprint = $_SERVER['HTTP_X_CLIENT_FINGERPRINT'] ?? null;
    
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
    
    if (!$user) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not authenticated',
            'needs_domain' => false
        ]);
        exit;
    }
    
    // Force a fresh database query to get the latest user data
    $freshUser = \App\Models\User::find($user->id);
    
    if (!$freshUser) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found in database',
            'needs_domain' => false
        ]);
        exit;
    }
    
    // Check if user is a client and needs a domain
    $isClient = strtolower($freshUser->role) === 'client';
    
    // SIMPLE LOGIC: Just check if ANY domain exists for this user in domains table
    $userDomains = \App\Models\Domain::where('user_id', $freshUser->id)->get();
    $hasDomainInTable = $userDomains->count() > 0;
    
    // User needs domain if they are a client AND don't have any domain in domains table
    $needsDomain = $isClient && !$hasDomainInTable;
    
    // Clean up orphaned domain_id if user has domain_id but no actual domain record
    if ($freshUser->domain_id && !$hasDomainInTable) {
        $freshUser->domain_id = null;
        $freshUser->save();
        
        \Illuminate\Support\Facades\Log::info('Cleared orphaned domain_id - no domains in table', [
            'user_id' => $freshUser->id,
            'cleared_domain_id' => $freshUser->domain_id
        ]);
    }
    
    // Set domain_id if user doesn't have one but has domains
    if (!$freshUser->domain_id && $hasDomainInTable) {
        $latestDomain = $userDomains->sortByDesc('created_at')->first();
        $freshUser->domain_id = $latestDomain->id;
        $freshUser->save();
        
        \Illuminate\Support\Facades\Log::info('Set domain_id to latest domain', [
            'user_id' => $freshUser->id,
            'domain_id' => $latestDomain->id
        ]);
    }
    
    // Get all domains for this user for debugging
    $allDomains = \App\Models\Domain::where('user_id', $freshUser->id)->get();
    $activeDomains = $allDomains->where('is_active', true)->where('status', 'active');
    
    // Log the check
    \Illuminate\Support\Facades\Log::info('Domain status check', [
        'user_id' => $freshUser->id,
        'user_role' => $freshUser->role,
        'domain_id' => $freshUser->domain_id,
        'has_domain_in_table' => $hasDomainInTable,
        'needs_domain' => $needsDomain,
        'session_check' => $isSessionCheck,
        'total_domains' => $allDomains->count()
    ]);
    
    // Return the result
    echo json_encode([
        'status' => 'success',
        'timestamp' => time(),
        'needs_domain' => $needsDomain,
        'session_check' => $isSessionCheck,
        'user_data' => [
            'id' => $freshUser->id,
            'email' => $freshUser->email,
            'name' => $freshUser->name,
            'role' => $freshUser->role,
            'domain_id' => $freshUser->domain_id
        ],
        'domain_info' => [
            'is_client' => $isClient,
            'has_domain_in_table' => $hasDomainInTable,
            'total_domains' => $allDomains->count(),
            'domains_list' => $userDomains->map(function($domain) {
                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'status' => $domain->status,
                    'is_active' => $domain->is_active,
                    'created_at' => $domain->created_at
                ];
            })->values()
        ],
        'message' => $needsDomain ? 
            'User needs to set up a domain to continue using the dashboard.' : 
            'User has domains in the table.'
    ]);
    
} catch (\Exception $e) {
    // Log the error
    \Illuminate\Support\Facades\Log::error('Error in domain status check', [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Return error response
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to check domain status: ' . $e->getMessage(),
        'needs_domain' => false
    ]);
} 