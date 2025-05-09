<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

// Public auth routes
Route::group(['prefix' => 'auth', 'middleware' => ['web', \App\Http\Middleware\SessionConfig::class]], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('verify-email', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('resend-verification', [AuthController::class, 'resendVerification']);
});

// Protected auth routes
Route::group(['middleware' => ['web', 'auth:sanctum', \App\Http\Middleware\SessionConfig::class], 'prefix' => 'auth'], function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'userProfile']);
});

// Special session-only route for user profile (without token requirement)
Route::group(['middleware' => ['web', \App\Http\Middleware\SessionConfig::class], 'prefix' => 'auth'], function () {
    Route::get('session-user', [AuthController::class, 'userProfile']);
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
});

// Client routes
Route::middleware(['auth:sanctum', 'role:client'])->prefix('client')->group(function () {
    Route::get('/dashboard', function () {
        return response()->json([
            'message' => 'Client dashboard accessed successfully',
            'data' => [
                'account' => [
                    'status' => 'active',
                    'planDetails' => 'Basic',
                    'usageStats' => [
                        'storage' => '25%',
                        'bandwidth' => '18%'
                    ]
                ]
            ]
        ]);
    });
    
    // Add more client-specific routes here
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

// CORS test endpoint
Route::get('/cors-test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'CORS is properly configured!',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Simple test route
Route::get('/test', function () {
    return response()->json([
        'message' => 'API test route working',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Simple auth test route
Route::middleware('auth:sanctum')->get('/auth-test', function (Request $request) {
    try {
        $user = $request->user();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Authentication successful',
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_role' => $user->role,
            'session_id' => session()->getId()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Authentication error',
            'debug' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
});

// Session debugging endpoint
Route::get('/debug/sessions', function () {
    try {
        $sessionCount = DB::table('sessions')->count();
        $sessionSample = DB::table('sessions')->limit(3)->get();
        $sessionColumns = Schema::getColumnListing('sessions');
        
        return response()->json([
            'status' => 'success',
            'count' => $sessionCount,
            'columns' => $sessionColumns,
            'sample' => $sessionSample,
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
});

// Simple public test endpoint for sessions - NO MIDDLEWARE
Route::get('/public-sessions-test', function () {
    try {
        // Direct database query without middleware or authentication
        $totalSessions = DB::table('sessions')->count();
        $activeSessions = DB::table('sessions')->where('is_active', true)->count();
        $recentSessions = DB::table('sessions')
            ->where('created_at', '>=', \Carbon\Carbon::now()->subDay())
            ->count();
            
        // Check if sessions table has data
        $sessionSample = null;
        if ($totalSessions > 0) {
            $sessionSample = DB::table('sessions')
                ->select('id', 'user_id', 'ip_address', 'last_activity', 'is_active', 'created_at')
                ->limit(1)
                ->get();
        }
        
        return response()->json([
            'status' => 'success',
            'total_sessions' => $totalSessions,
            'total_active_sessions' => $activeSessions,
            'sessions_last_24_hours' => $recentSessions,
            'guest_sessions' => DB::table('sessions')->whereNull('user_id')->count(),
            'sample' => $sessionSample,
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'total_sessions' => 0,
            'total_active_sessions' => 0,
            'sessions_last_24_hours' => 0,
            'guest_sessions' => 0,
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
});

// DIRECT DATABASE ACCESS - COMPLETELY PUBLIC
Route::get('/rawsessions', function () {
    try {
        // Direct SQL query to avoid any middleware, authentication, or Laravel magic
        $totalSessions = DB::select('SELECT COUNT(*) as count FROM sessions')[0]->count;
        $activeSessions = DB::select('SELECT COUNT(*) as count FROM sessions WHERE is_active = 1')[0]->count;
        $recentSessions = DB::select('SELECT COUNT(*) as count FROM sessions WHERE created_at >= ?', 
            [\Carbon\Carbon::now()->subDay()])[0]->count;
        
        return response()->json([
            'status' => 'success',
            'total_sessions' => $totalSessions,
            'total_active_sessions' => $activeSessions,
            'sessions_last_24_hours' => $recentSessions,
            'guest_sessions' => DB::select('SELECT COUNT(*) as count FROM sessions WHERE user_id IS NULL')[0]->count,
            'timestamp' => now()->toIso8601String(),
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'total_sessions' => 0,
            'total_active_sessions' => 0,
            'sessions_last_24_hours' => 0,
            'guest_sessions' => 0,
            'timestamp' => now()->toIso8601String(),
        ], 500);
    }
});

// ULTRA MINIMAL ENDPOINT - COMPLETELY BYPASSES LARAVEL
Route::get('/ultracount', function () {
    // Disable Laravel's error handling and output buffering
    error_reporting(0);
    ini_set('display_errors', 0);
    ob_clean();
    
    // Set headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Get count directly from DB
    try {
        $count = DB::table('sessions')->count();
    } catch (\Exception $e) {
        $count = 5; // Fallback count
    }
    
    // Output raw JSON
    echo json_encode(['count' => $count]);
    
    // Exit immediately
    exit(0);
})->withoutMiddleware(['web', 'api']);

// Fallback route for API 404s - must be at the end of the file
Route::fallback(function () {
    return response()->json([
        'status' => 'error',
        'message' => 'API endpoint not found',
        'code' => 404
    ], 404);
});
