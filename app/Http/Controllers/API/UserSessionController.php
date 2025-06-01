<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Session;

class UserSessionController extends Controller
{
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
     * List all active sessions for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $currentSessionId = $request->session()->getId();

            // Get all active sessions for the current user
            $sessions = Session::where('user_id', $user->id)
                ->where('expires_at', '>', now())
                ->orderBy('last_activity', 'desc')
                ->get();
            
            // Format the sessions data
            $formattedSessions = $sessions->map(function ($session) use ($currentSessionId) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                    'device_info' => $session->device_info,
                    'is_current' => $session->id === $currentSessionId,
                    'is_active' => $session->is_active,
                    'created_at' => $session->created_at ? $session->created_at->toIso8601String() : null,
                ];
            });
            
            return response()->json([
                'sessions' => $formattedSessions,
                'current_session_id' => $currentSessionId
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user sessions', [
                'user_id' => $request->user() ? $request->user()->id : null,
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
     * Revoke a specific session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $currentSessionId = $request->session()->getId();
            
            // Check if trying to revoke current session
            if ($id === $currentSessionId) {
                return response()->json([
                    'message' => 'Cannot revoke your current session. Use the logout endpoint instead.'
                ], 400);
            }
            
            // Find the session and ensure it belongs to the current user
            $session = Session::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$session) {
                return response()->json([
                    'message' => 'Session not found or does not belong to you'
                ], 404);
            }
            
            // Deactivate the session
            $session->is_active = false;
            $session->expires_at = Carbon::now();
            $session->save();
            
            Log::info('Session revoked by user', [
                'user_id' => $user->id,
                'session_id' => $id
            ]);
            
            return response()->json([
                'message' => 'Session revoked successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error revoking session', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'session_id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to revoke session',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while revoking the session'
            ], 500);
        }
    }

    /**
     * Refresh current session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshCurrent(Request $request)
    {
        try {
            $user = $request->user();
            $currentSessionId = $request->session()->getId();
            
            // Update session last_activity and expires_at
            $session = Session::where('id', $currentSessionId)
                ->where('user_id', $user->id)
                ->first();
            
            if (!$session) {
                // Session record not found, which is unusual
                Log::warning('Session record not found during refresh', [
                    'user_id' => $user->id,
                    'session_id' => $currentSessionId
                ]);
                
                // Regenerate session ID
                $request->session()->regenerate();
                $newSessionId = $request->session()->getId();
                
                return response()->json([
                    'message' => 'Session refreshed with new ID',
                    'session_id' => $newSessionId
                ]);
            }
            
            // Update the existing session
            $session->last_activity = time();
            $session->expires_at = now()->addMinutes(config('session.lifetime', 120));
            $session->is_active = true;
            $session->save();
            
            Log::info('Session refreshed', [
                'user_id' => $user->id,
                'session_id' => $currentSessionId
            ]);
            
            return response()->json([
                'message' => 'Session refreshed successfully',
                'session_id' => $currentSessionId,
                'expires_at' => $session->expires_at->toIso8601String()
            ]);
        } catch (\Exception $e) {
            Log::error('Error refreshing session', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to refresh session',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while refreshing the session'
            ], 500);
        }
    }

    /**
     * Revoke all other sessions except current.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeOthers(Request $request)
    {
        try {
            $user = $request->user();
            $currentSessionId = $request->session()->getId();
            
            // Deactivate all other sessions
            Session::where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'expires_at' => Carbon::now()
                ]);
            
            Log::info('All other sessions revoked by user', [
                'user_id' => $user->id,
                'current_session_id' => $currentSessionId
            ]);
            
            return response()->json([
                'message' => 'All other sessions revoked successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error revoking other sessions', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to revoke other sessions',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while revoking other sessions'
            ], 500);
        }
    }

    /**
     * Get session statistics for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Get basic session statistics
            $totalActive = Session::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->count();
            
            $totalInactive = Session::where('user_id', $user->id)
                ->where(function($query) {
                    $query->where('is_active', false)
                          ->orWhere('expires_at', '<=', now());
                })
                ->count();
            
            // Get recent activity
            $recentActivity = Session::where('user_id', $user->id)
                ->where('last_activity', '>=', Carbon::now()->subDays(7)->timestamp)
                ->orderBy('last_activity', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'ip_address' => $session->ip_address,
                        'last_activity' => Carbon::createFromTimestamp($session->last_activity)->toIso8601String(),
                        'device_info' => $session->device_info,
                        'is_active' => $session->is_active,
                    ];
                });
            
            // Get unique devices
            $uniqueDevices = Session::where('user_id', $user->id)
                ->distinct('user_agent')
                ->count('user_agent');
            
            return response()->json([
                'total_active_sessions' => $totalActive,
                'total_inactive_sessions' => $totalInactive,
                'unique_devices' => $uniqueDevices,
                'recent_activity' => $recentActivity
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching session stats', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch session statistics',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching session statistics'
            ], 500);
        }
    }
} 
