<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session as SessionFacade;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        // No middleware here - middleware is applied in routes file
    }

    /**
     * Authenticate the user and start a session.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        try {
            // Log the incoming request for debugging
            Log::info('Login attempt', [
                'email' => $request->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'remember' => true, // Always use remember me
                'has_password' => !empty($request->password),
                'has_session' => $request->hasSession()
            ]);

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if user exists
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'These credentials do not match our records.'
                ], 401);
            }
            
            // Check if user is suspended
            if ($user->is_suspended) {
                Log::warning('Suspended user attempted to login', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Your account has been suspended. Please contact administration for assistance.'
                ], 403);
            }
            
            // Check email verification if required
            if (config('auth.email_verification_required') && !$user->hasVerifiedEmail()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email not verified. Please check your email for verification link.'
                ], 401);
            }

            // Manually verify credentials
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // Login the user
            Auth::login($user, true); // true = remember me

            // Handle session (safely wrapping everything in try/catch)
            $sessionId = null;
            try {
                if ($request->hasSession()) {
                    // Regenerate session to prevent session fixation
                    $request->session()->regenerate();
                    $sessionId = $request->session()->getId();
                    Log::info('Session regenerated during login', ['session_id' => $sessionId]);
                } else {
                    Log::warning('No session available on request during login');
                }
            } catch (\Exception $e) {
                Log::error('Session error during login', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue login process even if session handling fails
            }
            
            // User is now logged in
            /** @var \App\Models\User $user */
            $user = Auth::user();
            
            // Create an access token
            $token = null;
            try {
                // First revoke any existing tokens
                $user->tokens()->delete();
                
                // Create Sanctum token
                $token = $user->createToken('auth-token')->plainTextToken;
                
                if (!$token) {
                    Log::error('Failed to create Sanctum token', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create token', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
                // Just continue - token might be null but authentication succeeded
            }

            // Log successful login
            Log::info('User logged in successfully', [
                'email' => $request->email,
                'user_id' => $user->id,
                'ip' => $request->ip(),
                'session_id' => $sessionId
            ]);

            // Return token and user info
            return response()->json([
                'status' => 'success',
                'access_token' => $token, // For compatibility with old clients
                'token_type' => 'bearer',
                'session_id' => $sessionId,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication error occurred',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => 'required|string|min:6|confirmed',
                'role' => 'sometimes|string|in:client,admin',
                'agree_terms' => 'required|accepted',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Create user with default role 'client' if not specified
            /** @var \App\Models\User $user */
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role ?? 'client', // Set 'client' as default role
            ]);

            // Determine if email verification is required
            $requiresEmailVerification = config('auth.email_verification_required', false);
            
            if ($requiresEmailVerification) {
                // Generate verification URL
                $verificationUrl = $this->generateVerificationUrl($user);
                
                // Send verification email
                $this->sendVerificationEmail($user, $verificationUrl);
                
                event(new Registered($user));
            } else {
                // Mark email as verified if verification is not required
                $user->markEmailAsVerified();
            }

            // Log in the user after registration - use true for remember me
            Auth::login($user, true);
            
            // Handle session (safely wrapping everything in try/catch)
            $sessionId = null;
            try {
                if ($request->hasSession()) {
                    // Regenerate session to prevent session fixation
                    $request->session()->regenerate();
                    $sessionId = $request->session()->getId();
                    Log::info('Session regenerated during registration', ['session_id' => $sessionId]);
                } else {
                    Log::warning('No session available on request during registration');
                }
            } catch (\Exception $e) {
                Log::error('Session error during registration', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue registration process even if session handling fails
            }
            
            // Create an access token
            $token = null;
            try {
                // First revoke any existing tokens
                $user->tokens()->delete();
                
                // Create Sanctum token
                $token = $user->createToken('auth-token')->plainTextToken;
                
                if (!$token) {
                    Log::error('Failed to create Sanctum token', [
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to create token during registration', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
                // Just continue - token might be null but registration succeeded
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User successfully registered',
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'bearer',
                'session_id' => $sessionId,
                'requires_email_verification' => $requiresEmailVerification
            ], 201);
        } catch (\Exception $e) {
            Log::error('Registration error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Log the user out (Invalidate the session and token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Get the user before logout
            $user = $request->user();
            
            // Revoke the token that was used to authenticate the current request
            if ($user) {
                $user->tokens()->delete();
                
                // Set user status to inactive if they have no active sessions
                $hasOtherActiveSessions = \App\Models\Session::where('user_id', $user->id)
                    ->where('id', '<>', $request->session()->getId())
                    ->where('is_active', true)
                    ->where('expires_at', '>', now())
                    ->exists();
                
                if (!$hasOtherActiveSessions) {
                    // Update user status to inactive
                    $user->update(['status' => \App\Enums\UserStatusEnum::INACTIVE->value]);
                }
            }
            
            // Invalidate the session
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Exception $e) {
            Log::error('Logout error', ['message' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Logout failed'], 500);
        }
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }
            
            // Refresh user from database to get latest data
            $user = $user->fresh();
            
            // Load the user with domain relationship
            $user->load('domain');
            
            // Make sure the role is always returned in a consistent format
            $userData = $user->toArray();
            $userData['role'] = strtolower($userData['role'] ?? 'client');
            
            // Explicitly ensure domain_id is included in the response
            $userData['domain_id'] = $user->domain_id;
            
            // Get security issues from session if available
            $securityIssues = null;
            if ($request->hasSession()) {
                $securityIssues = $request->session()->get('security_issues');
                
                // Check for fingerprint mismatch if fingerprint provided
                $fingerprint = $request->input('fingerprint');
                if ($fingerprint) {
                    // Get the stored fingerprint for this user/session from the database
                    try {
                        $sessionRecord = DB::table('sessions')
                            ->where('user_id', $user->id)
                            ->where('id', $request->session()->getId())
                            ->first();
                        
                        if ($sessionRecord && isset($sessionRecord->fingerprint)) {
                            $storedFingerprint = $sessionRecord->fingerprint;
                            if ($storedFingerprint !== $fingerprint) {
                                $securityIssues = $securityIssues ?? [];
                                $securityIssues[] = 'fingerprint_mismatch';
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to check fingerprint for session', [
                            'error' => $e->getMessage(),
                            'user_id' => $user->id
                        ]);
                    }
                }
            }
            
            return response()->json([
                'status' => 'success',
                'user' => $userData,
                'session_id' => $request->session()->getId(),
                'security_issues' => $securityIssues
            ]);
        } catch (\Exception $e) {
            Log::error('Error in userProfile: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user profile'
            ], 500);
        }
    }

    /**
     * Generate a signed URL for email verification.
     */
    protected function generateVerificationUrl($user)
    {
        $verifyUrl = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );
        
        // Convert to frontend URL
        $frontendUrl = config('app.frontend_url', config('app.url'));
        $verificationUrl = str_replace(config('app.url'), $frontendUrl, $verifyUrl);
        
        return $verificationUrl;
    }

    /**
     * Send verification email to user.
     */
    protected function sendVerificationEmail($user, $verificationUrl)
    {
        // In a real application, you would send an email here
        // For now, we'll just log the URL
        Log::info('Verification URL for ' . $user->email, ['url' => $verificationUrl]);
    }

    /**
     * Verify email address.
     */
    public function verifyEmail(Request $request)
    {
        try {
            $user = User::findOrFail($request->id);
            
            // Check if URL is valid
            if (!hash_equals(sha1($user->getEmailForVerification()), $request->hash)) {
                return response()->json(['status' => 'error', 'message' => 'Invalid verification link'], 400);
            }
            
            // Check if URL has not expired
            if (!$request->hasValidSignature()) {
                return response()->json(['status' => 'error', 'message' => 'Verification link has expired'], 400);
            }
            
            // Mark as verified
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }
            
            return response()->json(['status' => 'success', 'message' => 'Email verified successfully']);
        } catch (\Exception $e) {
            Log::error('Email verification error', ['message' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Verification failed'], 500);
        }
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
            ]);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
            
            $user = User::where('email', $request->email)->first();
            
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
            }
            
            if ($user->hasVerifiedEmail()) {
                return response()->json(['status' => 'error', 'message' => 'Email already verified'], 400);
            }
            
            // Generate new verification URL
            $verificationUrl = $this->generateVerificationUrl($user);
            
            // Send verification email
            $this->sendVerificationEmail($user, $verificationUrl);
            
            return response()->json(['status' => 'success', 'message' => 'Verification email sent']);
        } catch (\Exception $e) {
            Log::error('Resend verification error', ['message' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => 'Failed to resend verification email'], 500);
        }
    }

    /**
     * Get the authenticated User from session only.
     * This endpoint is specifically for handling session authentication without tokens.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sessionUser(Request $request)
    {
        try {
            // Log detailed request info for debugging
            Log::info('Session user request details', [
                'session_id' => $request->hasSession() ? $request->session()->getId() : 'no_session',
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_fingerprint' => $request->input('fingerprint', 'none'),
                'has_cookies' => !empty($_COOKIE) ? 'yes' : 'no',
                'session_driver' => config('session.driver'),
                'session_cookie' => config('session.cookie')
            ]);
            
            // Check if session is active
            if ($request->hasSession()) {
                try {
                    // Try to get data from session to test it's working
                    $sessionId = $request->session()->getId();
                    Log::info('Session appears to be active', [
                        'session_id' => $sessionId
                    ]);
                    
                    // CRITICAL: Check if this session was explicitly logged out
                    $sessionRecord = DB::table('sessions')->where('id', $sessionId)->first();
                    if ($sessionRecord && isset($sessionRecord->is_active) && !$sessionRecord->is_active) {
                        Log::info('Session was deactivated (user logged out), rejecting session recovery', [
                            'session_id' => $sessionId
                        ]);
                        
                        // Invalidate the session completely to prevent future attempts
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                        
                        return response()->json([
                            'status' => 'error', 
                            'message' => 'Session was terminated',
                            'code' => 'SESSION_TERMINATED'
                        ], 401);
                    }
                    
                    // Also check if session has expired
                    if ($sessionRecord && isset($sessionRecord->expires_at)) {
                        $expiresAt = \Carbon\Carbon::parse($sessionRecord->expires_at);
                        if ($expiresAt->isPast()) {
                            Log::info('Session has expired, rejecting session recovery', [
                                'session_id' => $sessionId,
                                'expires_at' => $expiresAt->toDateTimeString()
                            ]);
                            
                            // Invalidate the session completely
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();
                            
                            return response()->json([
                                'status' => 'error', 
                                'message' => 'Session has expired',
                                'code' => 'SESSION_EXPIRED'
                            ], 401);
                        }
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Session exists but cannot be accessed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Session driver error: ' . $e->getMessage());
                }
            } else {
                Log::warning('Request has no session');
            }
            
            // Only try to get user from session auth
            $user = Auth::guard('web')->user();
            
            // If we have a session but no user, try to recover from session data
            if (!$user && $request->hasSession()) {
                // Get session ID
                $sessionId = $request->session()->getId();
                
                // Find session in the database - wrap in try/catch for detailed errors
                try {
                    $session = DB::table('sessions')->where('id', $sessionId)->first();
                    
                    if ($session) {
                        Log::info('Found session record', [
                            'session_id' => $sessionId,
                            'user_id' => $session->user_id ?? 'null',
                            'is_active' => $session->is_active ?? 'unknown',
                            'ip_address' => $session->ip_address ?? 'unknown'
                        ]);
                        
                        // Double-check session is active and not expired
                        if (!isset($session->is_active) || !$session->is_active) {
                            Log::info('Session record found but marked as inactive, rejecting recovery', [
                                'session_id' => $sessionId
                            ]);
                            
                            // Invalidate the session completely
                            $request->session()->invalidate();
                            $request->session()->regenerateToken();
                            
                            return response()->json([
                                'status' => 'error', 
                                'message' => 'Session is not active',
                                'code' => 'SESSION_INACTIVE'
                            ], 401);
                        }
                        
                        if ($session->user_id) {
                            try {
                                // Try to get user from session user_id
                                $user = User::findOrFail((int)$session->user_id);
                                Log::info('Successfully recovered user from session data', [
                                    'session_id' => $sessionId,
                                    'user_id' => $user->id
                                ]);
                                
                                // Login the user to the session
                                Auth::guard('web')->login($user);
                            } catch (\Exception $e) {
                                Log::error('Failed to recover user from session data', [
                                    'error' => $e->getMessage(),
                                    'session_id' => $sessionId,
                                    'user_id' => $session->user_id ?? 'null'
                                ]);
                                throw new \Exception('User recovery error: ' . $e->getMessage());
                            }
                        } else {
                            Log::info('Session exists but has no user_id', [
                                'session_id' => $sessionId
                            ]);
                        }
                    } else {
                        Log::info('No session record found for ID', [
                            'session_id' => $sessionId
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Database error when checking session', [
                        'error' => $e->getMessage(),
                        'session_id' => $sessionId,
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Database error: ' . $e->getMessage());
                }
            }
            
            if (!$user) {
                return response()->json([
                    'status' => 'error', 
                    'message' => 'No authenticated session found',
                    'debug' => [
                        'has_session' => $request->hasSession(),
                        'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                        'session_driver' => config('session.driver')
                    ]
                ], 401);
            }
            
            // Process fingerprint if provided
            $fingerprint = $request->input('fingerprint');
            $securityIssues = null;
            
            if ($fingerprint) {
                // Get the stored fingerprint for this user/session from the database
                $storedFingerprint = null;
                try {
                    $sessionRecord = DB::table('sessions')
                        ->where('user_id', $user->id)
                        ->where('id', $request->session()->getId())
                        ->first();
                    
                    if ($sessionRecord && isset($sessionRecord->fingerprint)) {
                        $storedFingerprint = $sessionRecord->fingerprint;
                    }
                    
                    // If fingerprints don't match, flag security issue
                    if ($storedFingerprint && $fingerprint !== $storedFingerprint) {
                        $securityIssues = [
                            'fingerprint_mismatch' => true,
                            'fingerprint_details' => [
                                'stored' => $storedFingerprint,
                                'current' => $fingerprint
                            ]
                        ];
                        
                        Log::warning('Fingerprint mismatch detected', [
                            'user_id' => $user->id,
                            'session_id' => $request->session()->getId(),
                            'stored_fingerprint' => $storedFingerprint,
                            'provided_fingerprint' => $fingerprint
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Error checking fingerprint', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Don't throw here, just continue without fingerprint check
                }
            }
            
            Log::info('Session user profile found', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);
            
            // Load the user with domain relationship
            $user->load('domain');
            
            // Make sure the role is always returned in a consistent format
            $userData = $user->toArray();
            $userData['role'] = strtolower($userData['role'] ?? 'client');
            
            // Generate a fresh token for SPA usage
            $token = null;
            try {
                // Create new Sanctum token
                $token = $user->createToken('auth-token')->plainTextToken;
            } catch (\Exception $e) {
                Log::error('Failed to create token during session user fetch', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue without token
            }
            
            return response()->json([
                'status' => 'success',
                'user' => $userData,
                'access_token' => $token,
                'token_type' => 'bearer',
                'session_id' => $request->session()->getId(),
                'security_issues' => $securityIssues
            ]);
        } catch (\Exception $e) {
            Log::error('Session user error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error', 
                'message' => 'Internal server error',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Check if the current user is suspended.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkSuspendStatus(Request $request)
    {
        try {
            // Get the authenticated user
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated',
                    'is_suspended' => false
                ], 401);
            }
            
            // Get a fresh instance of the user to ensure we have the latest data
            $freshUser = User::find($user->id);
            
            if (!$freshUser) {
                Log::error('User not found in database', [
                    'user_id' => $user->id
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found in database',
                    'is_suspended' => false
                ], 404);
            }
            
            // Check if user is suspended using fresh data
            $isSuspended = $freshUser->is_suspended || $freshUser->status === 'suspended';
            
            Log::info('User suspension check', [
                'user_id' => $freshUser->id,
                'is_suspended' => $isSuspended
            ]);
            
            return response()->json([
                'status' => 'success',
                'is_suspended' => $isSuspended,
                'message' => $isSuspended ? 'Your account has been suspended. Please contact administration for assistance.' : 'Account is active'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error checking user suspend status', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'message' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to check suspend status',
                'is_suspended' => false
            ], 500);
        }
    }
}
