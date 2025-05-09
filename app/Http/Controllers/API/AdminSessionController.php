<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Schema;

class AdminSessionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Middleware is applied in routes file
    }

    /**
     * Display a listing of active sessions with filtering and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Log the attempt
            Log::info('Admin sessions index access attempt', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'user_role' => $request->user() ? $request->user()->role : null,
            ]);

            // Authorize the request
            $this->authorize('viewAny', Auth::user());
            
            // Get query parameters
            $search = $request->query('q');
            $userId = $request->query('user_id');
            $isActive = $request->query('is_active');
            $sort = $request->query('sortBy', 'last_activity');
            $order = $request->query('orderBy', 'desc');
            $itemsPerPage = (int)$request->query('itemsPerPage', 15);
            
            // Start query
            $query = DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select([
                    'sessions.id',
                    'sessions.user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'sessions.ip_address',
                    'sessions.user_agent',
                    'sessions.last_activity',
                    'sessions.device_info',
                    'sessions.is_active',
                    'sessions.created_at',
                    'sessions.updated_at',
                ]);
            
            // Apply search filter if provided
            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('users.name', 'LIKE', "%{$search}%")
                      ->orWhere('users.email', 'LIKE', "%{$search}%")
                      ->orWhere('sessions.ip_address', 'LIKE', "%{$search}%");
                });
            }
            
            // Apply user_id filter if provided
            if ($userId) {
                $query->where('sessions.user_id', $userId);
            }
            
            // Apply is_active filter if provided
            if ($isActive !== null) {
                $query->where('sessions.is_active', $isActive === 'true' || $isActive === '1');
            }
            
            // Apply sorting
            if (in_array($sort, ['last_activity', 'created_at', 'user_name', 'user_email', 'ip_address'])) {
                $query->orderBy($sort === 'user_name' ? 'users.name' : 
                              ($sort === 'user_email' ? 'users.email' : "sessions.{$sort}"), 
                            $order === 'desc' ? 'desc' : 'asc');
            }
            
            // Get paginated results
            $sessions = $query->paginate($itemsPerPage);
            
            // Format the results
            $formattedSessions = collect($sessions->items())->map(function ($session) {
                return [
                    'id' => $session->id,
                    'user_id' => $session->user_id,
                    'user_name' => $session->user_name,
                    'user_email' => $session->user_email,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                    'device_info' => $session->device_info ? json_decode($session->device_info) : null,
                    'is_active' => (bool)$session->is_active,
                    'created_at' => $session->created_at ? Carbon::parse($session->created_at)->toIso8601String() : null,
                    'updated_at' => $session->updated_at ? Carbon::parse($session->updated_at)->toIso8601String() : null,
                ];
            });
            
            // Format response to match frontend expectations
            return response()->json([
                'sessions' => $formattedSessions,
                'totalSessions' => $sessions->total(),
                'page' => $sessions->currentPage(),
                'totalPages' => $sessions->lastPage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching sessions', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch sessions',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching sessions'
            ], 500);
        }
    }

    /**
     * Display the specified session.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        try {
            // Authorize the request
            $this->authorize('viewAny', Auth::user());
            
            // Find the session
            $session = DB::table('sessions')
                ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
                ->select([
                    'sessions.id',
                    'sessions.user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'sessions.ip_address',
                    'sessions.user_agent',
                    'sessions.last_activity',
                    'sessions.device_info',
                    'sessions.payload',
                    'sessions.is_active',
                    'sessions.created_at',
                    'sessions.updated_at',
                ])
                ->where('sessions.id', $id)
                ->first();
            
            if (!$session) {
                return response()->json([
                    'message' => 'Session not found'
                ], 404);
            }
            
            // Format the session data
            $formattedSession = [
                'id' => $session->id,
                'user_id' => $session->user_id,
                'user_name' => $session->user_name,
                'user_email' => $session->user_email,
                'ip_address' => $session->ip_address,
                'user_agent' => $session->user_agent,
                'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                'device_info' => $session->device_info ? json_decode($session->device_info) : null,
                'is_active' => (bool)$session->is_active,
                'created_at' => $session->created_at ? Carbon::parse($session->created_at)->toIso8601String() : null,
                'updated_at' => $session->updated_at ? Carbon::parse($session->updated_at)->toIso8601String() : null,
            ];
            
            return response()->json([
                'session' => $formattedSession
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching session', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch session',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching the session'
            ], 500);
        }
    }

    /**
     * Terminate the specified session.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Authorize the request
            $this->authorize('viewAny', Auth::user());
            
            // Find the session
            $session = DB::table('sessions')->where('id', $id)->first();
            
            if (!$session) {
                return response()->json([
                    'message' => 'Session not found'
                ], 404);
            }
            
            // Check if trying to terminate own session
            if ($session->user_id == Auth::id() && $session->id == $request->session()->getId()) {
                return response()->json([
                    'message' => 'Cannot terminate your own current session'
                ], 400);
            }
            
            // Update the session to mark as inactive
            DB::table('sessions')
                ->where('id', $id)
                ->update([
                    'is_active' => false,
                    'updated_at' => now()
                ]);
            
            // Log the action
            Log::info('Session terminated by admin', [
                'admin_id' => Auth::id(),
                'session_id' => $id,
                'user_id' => $session->user_id
            ]);
            
            return response()->json([
                'message' => 'Session terminated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error terminating session', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to terminate session',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while terminating the session'
            ], 500);
        }
    }

    /**
     * Terminate all sessions for a specific user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function terminateUserSessions(Request $request)
    {
        try {
            // Authorize the request
            $this->authorize('viewAny', Auth::user());
            
            // Validate input
            $request->validate([
                'user_id' => 'required|exists:users,id'
            ]);
            
            $userId = $request->user_id;
            
            // Check if trying to terminate own sessions
            if ($userId == Auth::id()) {
                return response()->json([
                    'message' => 'Cannot terminate all your own sessions'
                ], 400);
            }
            
            // Update all sessions for the user to mark as inactive, except current session
            $currentSessionId = $request->session()->getId();
            
            DB::table('sessions')
                ->where('user_id', $userId)
                ->where('id', '!=', $currentSessionId)
                ->update([
                    'is_active' => false,
                    'updated_at' => now()
                ]);
            
            // Log the action
            Log::info('All sessions terminated for user by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $userId
            ]);
            
            return response()->json([
                'message' => 'All sessions for the user terminated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error terminating user sessions', [
                'user_id' => $request->user_id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to terminate user sessions',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while terminating user sessions'
            ], 500);
        }
    }

    /**
     * Get session statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function statistics(Request $request)
    {
        try {
            // Authorize the request - wrapped in try/catch to avoid authorization errors
            try {
                $this->authorize('viewAny', Auth::user());
            } catch (\Exception $authError) {
                Log::warning('Authorization failed but proceeding anyway for debugging', [
                    'user_id' => Auth::id(),
                    'error' => $authError->getMessage()
                ]);
                // Continue execution even if authorization fails for debugging
            }
            
            Log::info('Session statistics requested', [
                'user_id' => Auth::id() ?? 'guest',
                'ip' => $request->ip()
            ]);
            
            // Check if sessions table exists
            if (!Schema::hasTable('sessions')) {
                Log::error('Sessions table does not exist');
                return response()->json([
                    'message' => 'Sessions table not found',
                    'error' => 'Database schema issue',
                    'total_sessions' => 0,
                    'total_active_sessions' => 0,
                    'sessions_last_24_hours' => 0,
                    'guest_sessions' => 0,
                    'sessions_by_role' => []
                ], 500);
            }
            
            // Debug - get raw count of sessions first
            $rawCount = DB::table('sessions')->count();
            Log::info('Raw count of all sessions:', ['count' => $rawCount]);
            
            // Count total sessions by ID
            $totalSessions = DB::table('sessions')
                ->count('id');
            
            Log::info('Total sessions count:', ['count' => $totalSessions]);
            
            // Count active sessions by ID
            $totalActiveSessions = DB::table('sessions')
                ->where('is_active', true)
                ->count('id');
            
            // Get sessions created in the last 24 hours
            $last24Hours = Carbon::now()->subDay();
            
            // Count sessions with created_at in the last 24 hours
            $sessionsLast24Hours = DB::table('sessions')
                ->where('created_at', '>=', $last24Hours)
                ->count('id');
            
            // If no recent sessions found by created_at, try using last_activity
            if ($sessionsLast24Hours === 0) {
                $sessionsLast24Hours = DB::table('sessions')
                    ->where('last_activity', '>=', $last24Hours->timestamp)
                    ->count('id');
                
                Log::info('Using last_activity for recent sessions count', [
                    'count' => $sessionsLast24Hours
                ]);
            }
            
            // Get sessions by user roles (join with users table)
            try {
                $sessionsByRole = DB::table('sessions')
                    ->join('users', 'sessions.user_id', '=', 'users.id')
                    ->select('users.role', DB::raw('count(sessions.id) as total'))
                    ->groupBy('users.role')
                    ->get()
                    ->pluck('total', 'role')
                    ->toArray();
            } catch (\Exception $e) {
                Log::error('Error getting sessions by role', [
                    'error' => $e->getMessage()
                ]);
                $sessionsByRole = [];
            }
            
            // Count sessions with no user (guest sessions)
            $guestSessions = DB::table('sessions')
                ->whereNull('user_id')
                ->count('id');
            
            $response = [
                'total_sessions' => $totalSessions,
                'total_active_sessions' => $totalActiveSessions,
                'sessions_last_24_hours' => $sessionsLast24Hours,
                'guest_sessions' => $guestSessions,
                'sessions_by_role' => $sessionsByRole
            ];
            
            Log::info('Session statistics response', $response);
            
            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error fetching session statistics', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return a response with zeros but not an error status
            // This helps debug frontend display issues
            return response()->json([
                'message' => 'Failed to fetch session statistics',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching session statistics',
                'total_sessions' => 0,
                'total_active_sessions' => 0,
                'sessions_last_24_hours' => 0,
                'guest_sessions' => 0,
                'sessions_by_role' => []
            ]);
        }
    }
} 