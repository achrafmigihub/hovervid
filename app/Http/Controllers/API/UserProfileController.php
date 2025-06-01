<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    /**
     * Get the profile for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentUserProfile(Request $request)
    {
        Log::info('Fetching current user profile');

        // Check if user is authenticated
        if (!Auth::check()) {
            Log::warning('User not authenticated when trying to fetch profile');
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $user = Auth::user();
        Log::info('User authenticated', ['user_id' => $user->id, 'email' => $user->email]);

        try {
            // Format the user profile data
            $profileData = $this->formatUserProfile($user);
            Log::info('User profile prepared', ['profile' => $profileData]);

            // Return simple format without nested data
            return response()->json($profileData);
        } catch (\Exception $e) {
            Log::error('Error fetching user profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error fetching user profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a user's profile by ID
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserProfile(Request $request, $id)
    {
        Log::info('Fetching user profile by ID', ['id' => $id]);
        
        try {
            // Make sure we're getting a User model by using first() on the query
            $user = User::where('id', $id)->first();
            
            if (!$user) {
                Log::warning('User not found', ['id' => $id]);
                return response()->json([
                    'message' => 'User not found'
                ], 404);
            }
            
            // Check if the authenticated user has permission to view this profile
            // Only the user themselves or an admin can view a profile
            $currentUser = Auth::user();
            
            if (!$currentUser || ($currentUser->id != $user->id && $currentUser->role !== 'admin')) {
                Log::warning('Permission denied to view user profile', [
                    'requested_user_id' => $id,
                    'current_user_id' => $currentUser ? $currentUser->id : null,
                    'current_user_role' => $currentUser ? $currentUser->role : null
                ]);
                
                return response()->json([
                    'message' => 'You do not have permission to view this profile'
                ], 403);
            }
            
            // Format the user profile data
            $profileData = $this->formatUserProfile($user);
            Log::info('User profile prepared', ['profile' => $profileData]);
            
            // Return simple format without nested data
            return response()->json($profileData);
        } catch (\Exception $e) {
            Log::error('Error fetching user profile', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error fetching user profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format user profile data
     *
     * @param  \App\Models\User  $user
     * @return array
     */
    private function formatUserProfile($user)
    {
        // Start with basic user data
        $profileData = [
            'id' => $user->id,
            'name' => $user->name ?? 'Unknown User',
            'email' => $user->email,
            'role' => $user->role ?? 'client',
            'status' => $user->status,
            'is_suspended' => $user->is_suspended ?? false,
            'created_at' => $user->created_at,
        ];
        
        // Get subscription plan data if available
        try {
            // Example plan data - replace with actual logic to get user's subscription
            $planData = null;
            if ($user->role === 'client') {
                $planData = [
                    'name' => 'Basic',
                    'price' => 9.99,
                    'duration' => 'month',
                    'features' => [
                        'Up to 10 users',
                        'Basic analytics',
                        'Email support'
                    ]
                ];
            }
            
            $profileData['plan'] = $planData;
        } catch (\Exception $e) {
            Log::warning('Failed to get plan data for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
        
        // Get avatar URL if available
        try {
            $avatarUrl = null;
            
            // If user has avatar_url field, use it
            if (!empty($user->avatar_url)) {
                $avatarUrl = $user->avatar_url;
            }
            
            $profileData['avatar'] = $avatarUrl;
        } catch (\Exception $e) {
            Log::warning('Failed to get avatar URL for user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return $profileData;
    }
} 
