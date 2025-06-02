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
Route::group(['prefix' => 'auth', 'middleware' => ['web', \App\Http\Middleware\SessionConfig::class]], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
});

// Protected auth routes
Route::group(['middleware' => ['web', 'auth:sanctum', \App\Http\Middleware\SessionConfig::class, 'nocache'], 'prefix' => 'auth'], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'userProfile']);
});

// Special session-only route for user profile (without token requirement)
Route::group(['middleware' => ['web', \App\Http\Middleware\SessionConfig::class, 'nocache'], 'prefix' => 'auth'], function () {
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
    Route::get('/users/{id}', [\App\Http\Controllers\API\AdminUserController::class, 'show']);
    Route::post('/users', [\App\Http\Controllers\API\AdminUserController::class, 'store']);
    Route::put('/users/{id}', [\App\Http\Controllers\API\AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [\App\Http\Controllers\API\AdminUserController::class, 'destroy']);
    
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
    Route::put('/domains/{id}', [DomainApiController::class, 'update']);
    Route::delete('/domains/{id}', [DomainApiController::class, 'destroy']);
});

// Temporary: Public admin domains route for testing (remove after authentication is fixed)
Route::prefix('admin')->group(function () {
    Route::get('/domains', [DomainApiController::class, 'index']);
    Route::post('/domains/{id}/activate', [DomainApiController::class, 'activate']);
    Route::post('/domains/{id}/deactivate', [DomainApiController::class, 'deactivate']);
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

// Debug route for domain checking (temporary for debugging)
Route::middleware(['auth:sanctum'])->get('/debug/user-domain', function (Request $request) {
    $user = $request->user();
    if (!$user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }
    
    // Load fresh user with domain
    $user = $user->fresh();
    $user->load('domain');
    
    return response()->json([
        'user_id' => $user->id,
        'email' => $user->email,
        'role' => $user->role,
        'domain_id' => $user->domain_id,
        'domain_relationship' => $user->domain,
        'has_domain_id' => !empty($user->domain_id),
        'has_domain_relationship' => !empty($user->domain),
        'domain_count' => $user->domains()->count(),
        'active_domains' => $user->domains()->where('is_active', true)->get()
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
Route::middleware(['auth:sanctum', 'web', \App\Http\Middleware\SessionConfig::class, \App\Http\Middleware\SessionManager::class])
    ->prefix('sessions')
    ->group(function () {
        Route::get('/', [UserSessionController::class, 'index']);
        Route::delete('/{id}', [UserSessionController::class, 'destroy']);
        Route::post('/current/refresh', [UserSessionController::class, 'refreshCurrent']);
        Route::delete('/other', [UserSessionController::class, 'revokeOthers']);
        Route::get('/stats', [UserSessionController::class, 'stats']);
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

// Client Domain Management Routes (Protected by hybrid auth - supports both session and token authentication)
Route::middleware(['web', 'auth:sanctum', 'role:client', \App\Http\Middleware\SessionConfig::class])->prefix('client')->group(function () {
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
});

// Fallback route for API 404s - must be at the end of the file
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'code' => 404
    ], 404);
});
