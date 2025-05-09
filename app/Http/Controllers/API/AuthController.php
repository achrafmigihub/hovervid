<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
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
            // Revoke the token that was used to authenticate the current request
            if ($request->user()) {
                $request->user()->tokens()->delete();
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
            Log::info('User profile request', [
                'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                'authorization' => $request->hasHeader('Authorization') ? 'Present' : 'Missing',
                'request_session_id' => $request->input('session_id')
            ]);
            
            // Try to get user from request (Sanctum token auth)
            $user = $request->user();
            
            // If no user found but we have a session
            if (!$user && $request->hasSession()) {
                // Try to get user from session auth
                $user = Auth::guard('web')->user();
                Log::info('Attempted to get user from session', [
                    'user_found' => $user ? 'Yes' : 'No',
                    'session_id' => $request->session()->getId()
                ]);
            }
            
            // If we have a session_id parameter, try to find the user by that session
            if (!$user && $request->has('session_id')) {
                // Find session in the database
                $sessionId = $request->input('session_id');
                $session = DB::table('sessions')->where('id', $sessionId)->first();
                
                if ($session && $session->user_id) {
                    try {
                        // Get user from session user_id - ensure we get a single user instance
                        $user = User::findOrFail((int)$session->user_id);
                        Log::info('Found user from provided session_id', [
                            'session_id' => $sessionId,
                            'user_id' => $user->id
                        ]);
                        
                        // Login the user to the session
                        if ($request->hasSession()) {
                            Auth::guard('web')->login($user);
                        }
                    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                        Log::warning('User ID from session not found in database', [
                            'session_id' => $sessionId,
                            'user_id' => $session->user_id
                        ]);
                        // Continue without user - will return 401 later
                    }
                }
            }
            
            if (!$user) {
                Log::warning('No authenticated user found');
                return response()->json(['status' => 'error', 'message' => 'Unauthenticated'], 401);
            }
            
            Log::info('User profile found', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);
            
            // Make sure the role is always returned in a consistent format
            $userData = $user->toArray();
            $userData['role'] = strtolower($userData['role'] ?? 'client');
            
            // Generate a fresh token for SPA usage
            $token = null;
            try {
                // First revoke any existing tokens if needed
                // $user->tokens()->delete();
                
                // Create new Sanctum token
                $token = $user->createToken('auth-token')->plainTextToken;
            } catch (\Exception $e) {
                Log::error('Failed to create token during profile fetch', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);
                // Continue without token
            }
            
            return response()->json([
                'status' => 'success',
                'user' => $userData,
                'access_token' => $token,
                'token_type' => 'bearer',
                'session_id' => $request->hasSession() ? $request->session()->getId() : null
            ]);
        } catch (\Exception $e) {
            Log::error('User profile error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error', 
                'message' => 'Could not retrieve user profile',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
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
}
