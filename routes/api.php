<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\API\UserProfileController;
use App\Http\Controllers\API\UserSessionController;
use App\Http\Controllers\API\DomainApiController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\DomainController;
use App\Http\Controllers\API\ClientDomainController;
use App\Http\Controllers\API\PluginDomainController;
use App\Http\Controllers\API\PluginStatusController;
use App\Http\Controllers\Api\PluginController;
use App\Http\Controllers\API\VideoProxyController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Plugin Domain Validation Routes (Public - No Authentication Required)
// These are the main routes the HoverVid plugin will use for domain validation
Route::prefix('plugin')->group(function () {
    // Core domain validation endpoint - this is what the plugin calls during activation
    Route::post('/validate-domain', [PluginDomainController::class, 'checkDomainAuthorization']);
    
    // NEW: HoverVid plugin API endpoints
    Route::post('/verify-domain', [PluginController::class, 'verifyDomain']);
    Route::post('/update-status', [PluginController::class, 'updateStatus']);
    Route::get('/domain-status', [PluginController::class, 'getDomainStatus']);
    
    // Video availability checking endpoints
    Route::post('/check-video', [PluginController::class, 'checkVideoAvailability']);
    Route::post('/get-video', [PluginController::class, 'getVideoByHash']);
    Route::post('/batch-check-videos', [PluginController::class, 'batchCheckVideoAvailability']);
    
    // Plugin status tracking routes
    Route::post('/status/update', [PluginStatusController::class, 'updateStatus']);
    Route::get('/status', [PluginStatusController::class, 'getStatus']);
    Route::post('/status/activate', [PluginStatusController::class, 'activate']);
    Route::post('/status/deactivate', [PluginStatusController::class, 'deactivate']);
    Route::get('/status/history', [PluginStatusController::class, 'getHistory']);
    
    // Health check
    Route::get('/health', [PluginDomainController::class, 'healthCheck']);
});

// Public auth routes
Route::group([
    'prefix' => 'auth', 
    'middleware' => [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\SessionConfig::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        // Note: No CSRF verification for API auth routes
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\ForceJsonResponse::class,
    ]
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
});

// Protected auth routes
Route::group([
    'middleware' => [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\SessionConfig::class,
        'auth:sanctum',
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\ForceJsonResponse::class,
    ], 
    'prefix' => 'auth'
], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'userProfile']);
});

// Special session-only route for user profile (without token requirement)
Route::group([
    'middleware' => [
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \App\Http\Middleware\SessionConfig::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\ForceJsonResponse::class,
    ], 
    'prefix' => 'auth'
], function () {
    Route::get('session-user', [AuthController::class, 'sessionUser']);
});

// Public User Management API routes (no authentication required)
Route::prefix('public')->group(function () {
    Route::get('/users', [\App\Http\Controllers\API\PublicUserController::class, 'index']);
    Route::get('/users/{id}', [\App\Http\Controllers\API\PublicUserController::class, 'show']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Admin dashboard accessed successfully',
            'data' => [
                'statistics' => [
                    'users' => 120,
                    'revenue' => 15000,
                    'growthRate' => 8.5
                ]
            ]
        ]);
    });
    
    // User management routes
    Route::get('/users', [\App\Http\Controllers\API\AdminUserController::class, 'index']);
    Route::get('/users/stats', [\App\Http\Controllers\API\AdminUserController::class, 'stats']);
    Route::get('/users/{user}', [\App\Http\Controllers\API\AdminUserController::class, 'show']);
    Route::post('/users', [\App\Http\Controllers\API\AdminUserController::class, 'store']);
    Route::put('/users/{user}', [\App\Http\Controllers\API\AdminUserController::class, 'update']);
    Route::delete('/users/{user}', [\App\Http\Controllers\API\AdminUserController::class, 'destroy']);
    
    // Session management routes
    Route::get('/sessions', [\App\Http\Controllers\API\AdminSessionController::class, 'index']);
    Route::get('/sessions/statistics', [\App\Http\Controllers\API\AdminSessionController::class, 'statistics']);
    Route::get('/sessions/{id}', [\App\Http\Controllers\API\AdminSessionController::class, 'show']);
    Route::delete('/sessions/{id}', [\App\Http\Controllers\API\AdminSessionController::class, 'destroy']);
    Route::post('/sessions/terminate-user', [\App\Http\Controllers\API\AdminSessionController::class, 'terminateUserSessions']);
    
    // Domain management routes for admin
    Route::get('/domains', [DomainApiController::class, 'index']);
    Route::post('/domains', [DomainApiController::class, 'store']);
    Route::post('/domains/{id}/activate', [DomainApiController::class, 'activate']);
    Route::post('/domains/{id}/deactivate', [DomainApiController::class, 'deactivate']);
    Route::post('/domains/{id}/verify', [DomainApiController::class, 'verify']);
    Route::post('/domains/{id}/unverify', [DomainApiController::class, 'unverify']);
    Route::put('/domains/{id}', [DomainApiController::class, 'update']);
    Route::delete('/domains/{id}', [DomainApiController::class, 'destroy']);
});

// Direct user update endpoint (for frontend compatibility)
Route::middleware(['auth:sanctum', 'role:admin'])->post('/direct-update-user.php', [\App\Http\Controllers\API\AdminUserController::class, 'directUpdate']);

// Temporary: Public admin domains route for testing (remove after authentication is fixed)
Route::prefix('admin')->group(function () {
    Route::get('/users', [\App\Http\Controllers\API\AdminUserController::class, 'index']);
    Route::get('/user-lookup', function (Request $request) {
        $search = $request->query('search', '');
        if (empty($search)) {
            return response()->json(['data' => []]);
        }
        
        $users = DB::table('users')
            ->select('id', 'name', 'email', 'role')
            ->where('email', 'ILIKE', "%{$search}%")
            ->orWhere('name', 'ILIKE', "%{$search}%")
            ->limit(10)
            ->get();
            
        return response()->json(['data' => $users]);
    });
    Route::get('/domains', [DomainApiController::class, 'index']);
    Route::post('/domains', [DomainApiController::class, 'store']);
    Route::post('/domains/{id}/activate', [DomainApiController::class, 'activate']);
    Route::post('/domains/{id}/deactivate', [DomainApiController::class, 'deactivate']);
    Route::post('/domains/{id}/verify', [DomainApiController::class, 'verify']);
    Route::post('/domains/{id}/unverify', [DomainApiController::class, 'unverify']);
    Route::delete('/domains/{id}', [DomainApiController::class, 'destroy']);
});

// Test route for API connectivity
Route::get('/ping', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'API is functioning correctly',
        'server_time' => now()->toIso8601String(),
        'environment' => app()->environment(),
    ]);
});

// User Management Routes
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    // User Management
    Route::get('/users', [\App\Http\Controllers\API\UserManagementController::class, 'index']);
    Route::get('/users/{user}', [\App\Http\Controllers\API\UserManagementController::class, 'show']);
    Route::post('/users/{user}', [\App\Http\Controllers\API\UserManagementController::class, 'update']);
    Route::delete('/users/{user}', [\App\Http\Controllers\API\UserManagementController::class, 'destroy']);
    
    // User Suspension Routes
    Route::post('/users/{user}/suspend', [\App\Http\Controllers\API\UserManagementController::class, 'suspend']);
    Route::post('/users/{user}/unsuspend', [\App\Http\Controllers\API\UserManagementController::class, 'unsuspend']);
    
    // User Password Change Route
    Route::post('/users/{user}/change-password', [\App\Http\Controllers\API\UserManagementController::class, 'changePassword']);
});

// Authentication Routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/register', [AuthController::class, 'register']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Check if the current user is suspended - add explicit middleware to ensure no caching
    Route::get('/check-suspended', [AuthController::class, 'checkSuspendStatus'])
        ->middleware(['nocache']);
});

// User profile routes
Route::middleware(['auth:sanctum'])->prefix('profile')->group(function () {
    Route::get('/me', [UserProfileController::class, 'getCurrentUserProfile']);
    Route::get('/{id}', [UserProfileController::class, 'getUserProfile']);
});

// User session management routes
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SessionConfig::class,
    'auth:sanctum',
    \App\Http\Middleware\SessionManager::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\ForceJsonResponse::class,
])
    ->prefix('sessions')
    ->group(function () {
        Route::get('/', [UserSessionController::class, 'index']);
        Route::post('/current/refresh', [UserSessionController::class, 'refreshCurrent']);
        Route::delete('/other', [UserSessionController::class, 'revokeOthers']);
        Route::get('/stats', [UserSessionController::class, 'stats']);
        Route::delete('/{id}', [UserSessionController::class, 'destroy']);
    });

// Domain routes (legacy - kept for backward compatibility)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/client/domain-legacy', [DomainController::class, 'getDomain']);
    Route::put('/client/domain-legacy', [DomainController::class, 'updateDomain']);
});

// Content API routes for plugin fingerprint data
Route::prefix('content')->group(function () {
    Route::post('/', [App\Http\Controllers\API\ContentController::class, 'store']);
    Route::get('/', [App\Http\Controllers\API\ContentController::class, 'index']);
});

// Invoice API routes for admin dashboard
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SessionConfig::class,
    'auth:sanctum',
    'role:admin',
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\ForceJsonResponse::class,
])->prefix('apps')->group(function () {
    Route::get('/invoice', [\App\Http\Controllers\API\InvoiceController::class, 'index']);
    Route::post('/invoice', [\App\Http\Controllers\API\InvoiceController::class, 'store']);
    Route::get('/invoice/{invoice}', [\App\Http\Controllers\API\InvoiceController::class, 'show']);
    Route::put('/invoice/{invoice}', [\App\Http\Controllers\API\InvoiceController::class, 'update']);
    Route::delete('/invoice/{invoice}', [\App\Http\Controllers\API\InvoiceController::class, 'destroy']);
});

// Client Domain Management Routes (Protected by hybrid auth - supports both session and token authentication)
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SessionConfig::class,
    'auth:sanctum',
    'role:client',
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\ForceJsonResponse::class,
])->prefix('client')->group(function () {
    Route::get('/dashboard', [ClientController::class, 'dashboard']);
    Route::get('/dashboard-stats', [ClientController::class, 'dashboardStats']);
    
    // Domain management for popup card
    Route::post('/set-domain', [ClientDomainController::class, 'setDomain']);
    Route::get('/domain', [ClientDomainController::class, 'getDomain']);
    Route::put('/domain', [ClientDomainController::class, 'updateDomain']);
    Route::delete('/domain', [ClientDomainController::class, 'removeDomain']);
    
    // Content management for client users
    Route::get('/content', [\App\Http\Controllers\API\ContentController::class, 'getClientContent']);
    Route::delete('/content/{contentId}', [\App\Http\Controllers\API\ContentController::class, 'rejectContent']);
    Route::post('/content/{contentId}/upload-video', [\App\Http\Controllers\API\ContentController::class, 'uploadVideo']);
    Route::patch('/content/{contentId}/video-url', [\App\Http\Controllers\API\ContentController::class, 'updateVideoUrl']);
});

// Admin User Management Routes (Session-based for admin dashboard)
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SessionConfig::class,
    'auth:sanctum',
    'role:admin',
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\ForceJsonResponse::class,
])->prefix('admin')->group(function () {
    // User suspension routes (session-based)
    Route::post('/users/{user}/suspend', [\App\Http\Controllers\API\UserManagementController::class, 'suspend']);
    Route::post('/users/{user}/unsuspend', [\App\Http\Controllers\API\UserManagementController::class, 'unsuspend']);
    Route::post('/users/{user}/change-password', [\App\Http\Controllers\API\UserManagementController::class, 'changePassword']);
});

// Public API routes (no authentication required) for the plugin
Route::middleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \App\Http\Middleware\SessionConfig::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
    \App\Http\Middleware\ForceJsonResponse::class,
])->group(function () {
    Route::post('/store-content', [\App\Http\Controllers\API\ContentController::class, 'store']);
    Route::get('/get-content', [\App\Http\Controllers\API\ContentController::class, 'index']);
    
    // New route for plugin to get video for specific content
    Route::get('/content/{contentId}/video', [\App\Http\Controllers\API\ContentController::class, 'getContentVideo']);
});

// Simple video proxy route (no middleware for CORS simplicity)
Route::get('/video-proxy/{encodedUrl}', function($encodedUrl) {
    try {
        $videoUrl = base64_decode(urldecode($encodedUrl));
        
        if (!str_contains($videoUrl, 'wasabisys.com')) {
            return response('Unauthorized', 403);
        }
        
        // Extract the file path from the full URL for Storage facade
        $parsedUrl = parse_url($videoUrl);
        $path = ltrim($parsedUrl['path'], '/');
        
        // Remove bucket name from path if present
        if (strpos($path, 'hovervid/') === 0) {
            $path = substr($path, 9); // Remove 'hovervid/' prefix
        }
        
        // Use Storage facade with Wasabi credentials
        if (\Illuminate\Support\Facades\Storage::disk('wasabi')->exists($path)) {
            $fileContent = \Illuminate\Support\Facades\Storage::disk('wasabi')->get($path);
            
            return response($fileContent)
                ->header('Content-Type', 'video/mp4')
                ->header('Accept-Ranges', 'bytes')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cache-Control', 'public, max-age=3600');
        } else {
            return response('Video not found', 404);
        }
            
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Video proxy error: ' . $e->getMessage());
        return response('Error', 500);
    }
});

// Fallback route for API 404s - must be at the end of the file
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'code' => 404
    ], 404);
});
