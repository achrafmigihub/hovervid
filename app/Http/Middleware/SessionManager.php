<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Session;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SessionManager
{
    /**
     * Maximum number of concurrent sessions per user
     * 
     * @var int
     */
    protected $maxConcurrentSessions;

    /**
     * Time window in minutes to check for suspicious activity
     * 
     * @var int
     */
    protected $suspiciousActivityWindow;

    /**
     * Create a new SessionManager middleware instance.
     */
    public function __construct()
    {
        $this->maxConcurrentSessions = config('session.max_concurrent_sessions', 5);
        $this->suspiciousActivityWindow = config('session.suspicious_activity_window', 30);
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            // Get the current session ID
            $currentSessionId = $request->session()->getId();
            $user = $request->user();

            // Log this session activity
            $this->logSessionActivity($request, $currentSessionId, $user->id);
            
            // Check for and enforce session limits
            $this->enforceConcurrentSessionLimits($user->id, $currentSessionId);
            
            // Detect suspicious activity
            $this->detectSuspiciousActivity($request, $user->id, $currentSessionId);
        }

        return $next($request);
    }

    /**
     * Log session activity for the current request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $sessionId
     * @param  int  $userId
     * @return void
     */
    protected function logSessionActivity(Request $request, string $sessionId, int $userId): void
    {
        try {
            $routeName = $request->route() ? ($request->route()->getName() ?? 'unnamed') : 'no-route';
            $routePath = $request->path();
            $method = $request->method();
            
            Log::info('Session activity', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $routeName,
                'path' => $routePath,
                'method' => $method
            ]);
            
            // You could also log to database if needed
            // ActivityLog::create(['user_id' => $userId, ...]);
        } catch (\Exception $e) {
            Log::error('Failed to log session activity: ' . $e->getMessage(), [
                'user_id' => $userId,
                'session_id' => $sessionId
            ]);
        }
    }

    /**
     * Enforce the maximum number of concurrent sessions per user.
     *
     * @param  int  $userId
     * @param  string  $currentSessionId
     * @return void
     */
    protected function enforceConcurrentSessionLimits(int $userId, string $currentSessionId): void
    {
        try {
            // Get all active sessions for this user
            $activeSessions = Session::where('user_id', $userId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->orderBy('last_activity', 'desc')
                ->get();
            
            // If under the limit, no action needed
            if ($activeSessions->count() <= $this->maxConcurrentSessions) {
                return;
            }
            
            // Keep track of the current session and the most recent sessions
            $keepSessions = collect([$currentSessionId]);
            
            // Add the most recent sessions up to the limit (minus the current one)
            $recentSessions = $activeSessions
                ->where('id', '!=', $currentSessionId)
                ->take($this->maxConcurrentSessions - 1)
                ->pluck('id');
            
            $keepSessions = $keepSessions->merge($recentSessions);
            
            // Deactivate older sessions beyond the limit
            Session::where('user_id', $userId)
                ->where('is_active', true)
                ->whereNotIn('id', $keepSessions)
                ->update([
                    'is_active' => false,
                    'expires_at' => Carbon::now()
                ]);
            
            Log::info('Enforced session limits', [
                'user_id' => $userId,
                'current_session' => $currentSessionId,
                'kept_sessions' => $keepSessions->toArray(),
                'max_allowed' => $this->maxConcurrentSessions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to enforce session limits: ' . $e->getMessage(), [
                'user_id' => $userId,
                'session_id' => $currentSessionId
            ]);
        }
    }

    /**
     * Detect suspicious activity based on IP changes or unusual behavior.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $userId
     * @param  string  $currentSessionId
     * @return void
     */
    protected function detectSuspiciousActivity(Request $request, int $userId, string $currentSessionId): void
    {
        try {
            // Get the current session
            $currentSession = Session::where('id', $currentSessionId)->first();
            if (!$currentSession) {
                return;
            }
            
            $currentIp = $request->ip();
            $timeWindow = Carbon::now()->subMinutes($this->suspiciousActivityWindow);
            
            // Get recent sessions within the time window
            $recentSessions = Session::where('user_id', $userId)
                ->where('id', '!=', $currentSessionId)
                ->where('last_activity', '>=', $timeWindow->timestamp)
                ->get();
            
            // Check for IP address changes
            $uniqueIps = $recentSessions->pluck('ip_address')->unique()->filter();
            $uniqueIps->push($currentIp);
            
            $suspiciousActivityDetected = false;
            $suspiciousReason = '';
            
            // Too many IPs in a short time window
            if ($uniqueIps->count() > 3) {
                $suspiciousActivityDetected = true;
                $suspiciousReason = 'Multiple IP addresses used in a short time period';
            }
            
            // Geographical IP change detection (simple version)
            // For a more advanced implementation, you would use a GeoIP service
            foreach ($recentSessions as $session) {
                // Skip if the IP is the same
                if ($session->ip_address === $currentIp) {
                    continue;
                }
                
                // Check for drastically different IP (simple check, just first octet)
                $previousIpFirstOctet = explode('.', $session->ip_address)[0] ?? '';
                $currentIpFirstOctet = explode('.', $currentIp)[0] ?? '';
                
                if ($previousIpFirstOctet && $currentIpFirstOctet && 
                    $previousIpFirstOctet !== $currentIpFirstOctet) {
                    $suspiciousActivityDetected = true;
                    $suspiciousReason = 'IP address changed significantly';
                    break;
                }
            }
            
            // User-agent change detection
            $currentUserAgent = $request->userAgent();
            $userAgents = $recentSessions->pluck('user_agent')->unique()->filter();
            $userAgents->push($currentUserAgent);
            
            if ($userAgents->count() > 2) {
                $suspiciousActivityDetected = true;
                $suspiciousReason = 'Multiple user agents used in a short time period';
            }
            
            // Log suspicious activity
            if ($suspiciousActivityDetected) {
                Log::warning('Suspicious activity detected', [
                    'user_id' => $userId,
                    'session_id' => $currentSessionId,
                    'reason' => $suspiciousReason,
                    'current_ip' => $currentIp,
                    'recent_ips' => $uniqueIps->toArray(),
                    'user_agents' => $userAgents->toArray()
                ]);
                
                // Optionally notify the user or security team
                // Notification::send(...);
                
                // Set a flag in the session for showing a security notice to the user
                $request->session()->put('security_notice', [
                    'type' => 'suspicious_activity',
                    'message' => 'Unusual activity has been detected on your account.',
                    'detected_at' => Carbon::now()->toDateTimeString()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to detect suspicious activity: ' . $e->getMessage(), [
                'user_id' => $userId,
                'session_id' => $currentSessionId,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
} 
