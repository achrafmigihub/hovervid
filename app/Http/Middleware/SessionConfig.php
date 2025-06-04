<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Session;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SessionConfig
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log detailed request info
        Log::info('SessionConfig middleware processing request', [
            'uri' => $request->getPathInfo(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'has_session' => $request->hasSession() ? 'yes' : 'no',
            'has_cookies' => !empty($_COOKIE) ? 'yes' : 'no',
            'session_driver' => config('session.driver')
        ]);
        
        // Make sure session driver is configured
        if (!Config::get('session.driver')) {
            Log::warning('Session driver not configured, setting to database');
            Config::set('session.driver', 'database');
        }

        try {
            // Extract fingerprint if present in request
            $fingerprint = $this->extractFingerprint($request);
            
            // Get or create session
            if (!$request->hasSession()) {
                Log::info('No session on request, creating a new one');
                
                try {
                    $session = app('session');
                    
                    // Generate a new session ID
                    $sessionId = $session->getId() ?: $session->generateSessionId();
                    $session->setId($sessionId);
                    
                    Log::info('New session created', ['session_id' => $sessionId]);
                    
                    // Session record will be created later when user authenticates
                    
                    $request->setLaravelSession($session->driver());
                } catch (\Exception $e) {
                    Log::error('Failed to create new session', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                Log::info('Session exists on request', [
                    'session_id' => $request->session()->getId()
                ]);
                
                // Session exists, update fingerprint if provided and different
                $this->updateSessionFingerprint($request, $fingerprint);
                
                // If user is authenticated and session record doesn't exist, create it
                if ($request->user()) {
                    $sessionId = $request->session()->getId();
                    $sessionRecord = DB::table('sessions')->where('id', $sessionId)->first();
                    
                    if (!$sessionRecord) {
                        Log::info('Creating session record for existing session with authenticated user', [
                            'user_id' => $request->user()->id,
                            'session_id' => $sessionId
                        ]);
                        
                        try {
                            Session::createNewSession([
                                'id' => $sessionId,
                                'user_id' => $request->user()->id,
                                'ip_address' => $request->ip(),
                                'user_agent' => $request->userAgent(),
                                'fingerprint' => $fingerprint,
                                'payload' => '',
                                'last_activity' => time(),
                                'device_info' => [
                                    'browser' => $request->header('User-Agent'),
                                    'platform' => $request->header('Sec-Ch-Ua-Platform'),
                                ],
                            ]);
                            
                            Log::info('Session record created for existing session');
                        } catch (\Exception $e) {
                            Log::error('Failed to create session record for existing session', [
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                }
            }

            // Ensure session is started
            if ($request->hasSession() && !$request->session()->isStarted()) {
                Log::info('Starting session');
                try {
                    $request->session()->start();
                } catch (\Exception $e) {
                    Log::error('Failed to start session', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            }
            
            // Update last activity for active sessions
            if ($request->user()) {
                try {
                    $updated = Session::where('user_id', $request->user()->id)
                        ->where('is_active', true)
                        ->where('expires_at', '>', now())
                        ->update(['last_activity' => time()]);
                    
                    Log::info('Updated last activity for active sessions', [
                        'user_id' => $request->user()->id,
                        'sessions_updated' => $updated
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update last activity', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            // Check for security issues
            $securityIssues = $this->checkSessionSecurity($request, $fingerprint);
            if ($securityIssues) {
                // Store security issues in the session for later use
                if ($request->hasSession()) {
                    $request->session()->put('security_issues', $securityIssues);
                }
                
                // Log the security issues
                Log::warning('Security issues detected in session', [
                    'session_id' => $request->hasSession() ? $request->session()->getId() : 'no_session',
                    'user_id' => $request->user() ? $request->user()->id : null,
                    'issues' => $securityIssues
                ]);
            }
            
            Log::info('Session configured successfully', [
                'session_id' => $request->hasSession() ? $request->session()->getId() : 'no_session'
            ]);
        } catch (\Exception $e) {
            Log::error('Session configuration error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // We'll continue despite the error, to avoid breaking the entire request
            // But we should at least log a critical error
            Log::critical('Continuing after session error - this may cause authentication issues');
        }
        
        return $next($request);
    }
    
    /**
     * Extract fingerprint from request
     * 
     * @param Request $request
     * @return string|null
     */
    protected function extractFingerprint(Request $request): ?string
    {
        // Try to get fingerprint from request input
        $fingerprint = $request->input('fingerprint');
        
        // If not in request input, try to get from JSON body
        if (!$fingerprint && $request->isJson()) {
            $data = $request->json()->all();
            $fingerprint = $data['fingerprint'] ?? null;
        }
        
        // If not in JSON body, try to get from headers
        if (!$fingerprint) {
            $fingerprint = $request->header('X-Fingerprint');
        }
        
        return $fingerprint;
    }
    
    /**
     * Update session fingerprint if different
     * 
     * @param Request $request
     * @param string|null $fingerprint
     * @return void
     */
    protected function updateSessionFingerprint(Request $request, ?string $fingerprint): void
    {
        if (!$fingerprint || !$request->session()) {
            return;
        }
        
        $sessionId = $request->session()->getId();
        
        try {
            // Get current session record
            $session = DB::table('sessions')->where('id', $sessionId)->first();
            
            // Only update if fingerprint is different and not null
            if ($session && (!isset($session->fingerprint) || $session->fingerprint !== $fingerprint)) {
                Log::info('Updating session fingerprint', [
                    'session_id' => $sessionId,
                    'old_fingerprint' => $session->fingerprint ?? 'null',
                    'new_fingerprint' => $fingerprint
                ]);
                
                DB::table('sessions')
                    ->where('id', $sessionId)
                    ->update(['fingerprint' => $fingerprint]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update session fingerprint', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId
            ]);
        }
    }
    
    /**
     * Check for security issues in the session
     * 
     * @param Request $request
     * @param string|null $fingerprint
     * @return array|null
     */
    protected function checkSessionSecurity(Request $request, ?string $fingerprint): ?array
    {
        if (!$request->user() || !$request->session()) {
            return null;
        }
        
        $securityIssues = [];
        $userId = $request->user()->id;
        $sessionId = $request->session()->getId();
        $currentIp = $request->ip();
        
        try {
            // Get current session
            $session = DB::table('sessions')
                ->where('id', $sessionId)
                ->first();
            
            if (!$session) {
                return null;
            }
            
            // Check for IP change
            if ($session->ip_address && $session->ip_address !== $currentIp) {
                $securityIssues['ip_change'] = true;
                $securityIssues['ip_details'] = [
                    'previous' => $session->ip_address,
                    'current' => $currentIp
                ];
                
                Log::warning('IP address change detected', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'previous_ip' => $session->ip_address,
                    'current_ip' => $currentIp
                ]);
            }
            
            // Check for fingerprint mismatch (if both are present)
            if ($fingerprint && isset($session->fingerprint) && $session->fingerprint !== $fingerprint) {
                $securityIssues['fingerprint_mismatch'] = true;
                $securityIssues['fingerprint_details'] = [
                    'stored' => $session->fingerprint,
                    'current' => $fingerprint
                ];
                
                Log::warning('Fingerprint mismatch detected', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'stored_fingerprint' => $session->fingerprint,
                    'current_fingerprint' => $fingerprint
                ]);
            }
            
            // Check for multiple active sessions from different IPs
            $activeSessions = DB::table('sessions')
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->get();
            
            $uniqueIps = collect($activeSessions)->pluck('ip_address')->unique()->filter()->values()->all();
            
            if (count($uniqueIps) > 3) {
                $securityIssues['multiple_ips'] = true;
                $securityIssues['ip_list'] = $uniqueIps;
                
                Log::warning('Multiple IP addresses detected for user sessions', [
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'unique_ips' => $uniqueIps,
                    'active_sessions_count' => count($activeSessions)
                ]);
            }
            
            return !empty($securityIssues) ? $securityIssues : null;
        } catch (\Exception $e) {
            Log::error('Error checking session security', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'session_id' => $sessionId
            ]);
            
            return null;
        }
    }
}
