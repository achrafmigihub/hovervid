<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Session;
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
        // Make sure session driver is configured
        if (!Config::get('session.driver')) {
            Config::set('session.driver', 'database');
        }

        try {
            // Get or create session
            if (!$request->hasSession()) {
                Log::info('No session on request, creating a new one');
                $session = app('session');
                
                // Generate a new session ID
                $sessionId = $session->getId() ?: $session->generateSessionId();
                $session->setId($sessionId);
                
                // Create a new session record
                if ($request->user()) {
                    Session::createNewSession([
                        'id' => $sessionId,
                        'user_id' => $request->user()->id,
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'payload' => '',
                        'last_activity' => time(),
                        'device_info' => [
                            'browser' => $request->header('User-Agent'),
                            'platform' => $request->header('Sec-Ch-Ua-Platform'),
                        ],
                    ]);
                }
                
                $request->setLaravelSession($session->driver());
            }

            // Ensure session is started
            if (!$request->session()->isStarted()) {
                Log::info('Starting session');
                $request->session()->start();
            }
            
            // Update last activity for active sessions
            if ($request->user()) {
                Session::where('user_id', $request->user()->id)
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->update(['last_activity' => time()]);
            }
            
            Log::info('Session configured: ' . $request->session()->getId());
        } catch (\Exception $e) {
            Log::error('Session configuration error: ' . $e->getMessage());
        }
        
        return $next($request);
    }
}
