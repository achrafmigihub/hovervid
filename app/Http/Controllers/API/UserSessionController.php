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

            // Get all sessions from the database
            $allSessions = DB::table('sessions')
                ->where('last_activity', '>', now()->subMinutes(config('session.lifetime', 120))->timestamp)
                ->get();
            
            $userSessions = [];
            
            foreach ($allSessions as $sessionRecord) {
                try {
                    // Decode the session payload
                    $payload = $this->decodeSessionPayload($sessionRecord->payload);
                    
                    // Check if this session belongs to the current user
                    if ($this->sessionBelongsToUser($payload, $user->id)) {
                        $userSessions[] = [
                            'id' => $sessionRecord->id,
                            'ip_address' => $sessionRecord->ip_address,
                            'user_agent' => $sessionRecord->user_agent,
                            'last_activity' => Carbon::createFromTimestamp($sessionRecord->last_activity)->toIso8601String(),
                            'is_current' => $sessionRecord->id === $currentSessionId,
                            'is_active' => true, // If it's in our filtered list, it's active
                            'created_at' => isset($sessionRecord->created_at) ? Carbon::parse($sessionRecord->created_at)->toIso8601String() : null,
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip sessions we can't decode
                    Log::debug('Could not decode session payload', [
                        'session_id' => $sessionRecord->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Sort by last activity (most recent first)
            usort($userSessions, function($a, $b) {
                return strtotime($b['last_activity']) <=> strtotime($a['last_activity']);
            });
            
            return response()->json([
                'sessions' => $userSessions,
                'current_session_id' => $currentSessionId,
                'total_sessions' => count($userSessions)
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
     * Decode Laravel session payload
     *
     * @param string $payload
     * @return array
     */
    private function decodeSessionPayload($payload)
    {
        // Laravel session payload is base64 encoded serialized data
        $data = base64_decode($payload);
        
        if ($data === false) {
            throw new \Exception('Failed to decode base64 payload');
        }
        
        $unserialized = unserialize($data);
        
        if ($unserialized === false) {
            throw new \Exception('Failed to unserialize session data');
        }
        
        return $unserialized;
    }

    /**
     * Check if session belongs to the given user
     *
     * @param array $sessionData
     * @param int $userId
     * @return bool
     */
    private function sessionBelongsToUser($sessionData, $userId)
    {
        // Check if user is logged in via web guard
        if (isset($sessionData['login_web_' . sha1('App\\Models\\User')])) {
            $loggedInUserId = $sessionData['login_web_' . sha1('App\\Models\\User')];
            return $loggedInUserId == $userId;
        }
        
        // Check alternative auth session keys
        foreach ($sessionData as $key => $value) {
            if (str_starts_with($key, 'login_') && $value == $userId) {
                return true;
            }
        }
        
        return false;
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
            
            // Find the session in the database
            $sessionRecord = DB::table('sessions')->where('id', $id)->first();
            
            if (!$sessionRecord) {
                return response()->json([
                    'message' => 'Session not found'
                ], 404);
            }
            
            // Check if this session belongs to the current user
            try {
                $payload = $this->decodeSessionPayload($sessionRecord->payload);
                if (!$this->sessionBelongsToUser($payload, $user->id)) {
                    return response()->json([
                        'message' => 'Session not found or does not belong to you'
                    ], 404);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'message' => 'Invalid session data'
                ], 400);
            }
            
            // Delete the session record (this will effectively log out that session)
            DB::table('sessions')->where('id', $id)->delete();
            
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
            
            // Update session last_activity in the database
            $updated = DB::table('sessions')
                ->where('id', $currentSessionId)
                ->update([
                    'last_activity' => now()->timestamp
                ]);
            
            if ($updated === 0) {
                // Session record not found, which is unusual but not critical
                Log::warning('Session record not found during refresh', [
                    'user_id' => $user->id,
                    'session_id' => $currentSessionId
                ]);
                
                // Regenerate session ID as a fallback
                $request->session()->regenerate();
                $newSessionId = $request->session()->getId();
                
                return response()->json([
                    'message' => 'Session refreshed with new ID',
                    'session_id' => $newSessionId
                ]);
            }
            
            Log::info('Session refreshed', [
                'user_id' => $user->id,
                'session_id' => $currentSessionId
            ]);
            
            $sessionLifetime = (int)config('session.lifetime', 120);
            $expiresAt = now()->addMinutes($sessionLifetime);
            
            return response()->json([
                'message' => 'Session refreshed successfully',
                'session_id' => $currentSessionId,
                'expires_at' => $expiresAt->toIso8601String(),
                'lifetime_minutes' => $sessionLifetime
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
            
            // Get all sessions from the database
            $allSessions = DB::table('sessions')->get();
            
            $revokedCount = 0;
            
            foreach ($allSessions as $sessionRecord) {
                // Skip current session
                if ($sessionRecord->id === $currentSessionId) {
                    continue;
                }
                
                try {
                    // Decode the session payload
                    $payload = $this->decodeSessionPayload($sessionRecord->payload);
                    
                    // Check if this session belongs to the current user
                    if ($this->sessionBelongsToUser($payload, $user->id)) {
                        // Delete this session (revoke it)
                        DB::table('sessions')->where('id', $sessionRecord->id)->delete();
                        $revokedCount++;
                    }
                } catch (\Exception $e) {
                    // Skip sessions we can't decode
                    Log::debug('Could not decode session payload during revoke others', [
                        'session_id' => $sessionRecord->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            Log::info('All other sessions revoked by user', [
                'user_id' => $user->id,
                'current_session_id' => $currentSessionId,
                'revoked_count' => $revokedCount
            ]);
            
            return response()->json([
                'message' => 'All other sessions revoked successfully',
                'revoked_count' => $revokedCount
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
            $currentSessionId = $request->session()->getId();
            
            // Get all sessions from the database
            $allSessions = DB::table('sessions')->get();
            
            $userSessions = [];
            $uniqueUserAgents = [];
            $uniqueIpAddresses = [];
            
            foreach ($allSessions as $sessionRecord) {
                try {
                    // Decode the session payload
                    $payload = $this->decodeSessionPayload($sessionRecord->payload);
                    
                    // Check if this session belongs to the current user
                    if ($this->sessionBelongsToUser($payload, $user->id)) {
                        $isActive = $sessionRecord->last_activity > now()->subMinutes(config('session.lifetime', 120))->timestamp;
                        
                        $userSessions[] = [
                            'id' => $sessionRecord->id,
                            'ip_address' => $sessionRecord->ip_address,
                            'user_agent' => $sessionRecord->user_agent,
                            'last_activity' => $sessionRecord->last_activity,
                            'is_current' => $sessionRecord->id === $currentSessionId,
                            'is_active' => $isActive,
                            'created_at' => $sessionRecord->created_at ?? null,
                        ];
                        
                        // Collect unique user agents and IPs
                        if ($sessionRecord->user_agent && !in_array($sessionRecord->user_agent, $uniqueUserAgents)) {
                            $uniqueUserAgents[] = $sessionRecord->user_agent;
                        }
                        if ($sessionRecord->ip_address && !in_array($sessionRecord->ip_address, $uniqueIpAddresses)) {
                            $uniqueIpAddresses[] = $sessionRecord->ip_address;
                        }
                    }
                } catch (\Exception $e) {
                    // Skip sessions we can't decode
                    Log::debug('Could not decode session payload during stats', [
                        'session_id' => $sessionRecord->id,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
            
            // Calculate statistics
            $totalActive = count(array_filter($userSessions, function($session) {
                return $session['is_active'];
            }));
            
            $totalInactive = count($userSessions) - $totalActive;
            
            // Get recent activity (last 5 sessions within 7 days)
            $recentActivity = array_filter($userSessions, function($session) {
                return $session['last_activity'] >= Carbon::now()->subDays(7)->timestamp;
            });
            
            // Sort by last activity (most recent first)
            usort($recentActivity, function($a, $b) {
                return $b['last_activity'] <=> $a['last_activity'];
            });
            
            $recentActivity = array_slice($recentActivity, 0, 5);
            
            // Format recent activity
            $recentActivity = array_map(function($session) {
                return [
                    'id' => $session['id'],
                    'ip_address' => $session['ip_address'],
                    'last_activity' => Carbon::createFromTimestamp($session['last_activity'])->toIso8601String(),
                    'user_agent' => $session['user_agent'],
                    'is_active' => $session['is_active'],
                    'is_current' => $session['is_current'],
                ];
            }, $recentActivity);
            
            return response()->json([
                'total_active_sessions' => $totalActive,
                'total_inactive_sessions' => $totalInactive,
                'total_sessions' => count($userSessions),
                'unique_devices' => count($uniqueUserAgents),
                'unique_ip_addresses' => count($uniqueIpAddresses),
                'recent_activity' => $recentActivity,
                'current_session_id' => $currentSessionId
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
