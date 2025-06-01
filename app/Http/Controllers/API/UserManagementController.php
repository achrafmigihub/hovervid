<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the users.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Get parameters for filtering and pagination
            $search = $request->query('q', '');
            $status = $request->query('status');
            $role = $request->query('role');
            $limit = (int)$request->query('limit', 10);
            $page = (int)$request->query('page', 1);
            
            // Start query
            $query = User::query();
            
            // Apply search filter
            if (!empty($search)) {
                $query->search($search);
            }
            
            // Apply status filter
            if (!empty($status)) {
                $query->where('status', $status);
            }
            
            // Apply role filter
            if (!empty($role)) {
                $query->where('role', $role);
            }
            
            // Apply pagination
            $users = $query->paginate($limit, ['*'], 'page', $page);
            
            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error fetching users', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch users',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching users'
            ], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        try {
            // Get the user with subscription data
            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'subscription' => $user->subscriptions()->latest()->first()
            ];
            
            return response()->json($userData);
        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching the user'
            ], 500);
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate(User::updateRules($user->id));
            
            // Update user
            $user->update($validated);
            
            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to update user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while updating the user'
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $user)
    {
        try {
            // Check if user is not deleting themselves
            if (Auth::id() === $user->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account'
                ], 403);
            }
            
            // Delete user
            $user->delete();
            
            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to delete user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while deleting the user'
            ], 500);
        }
    }

    /**
     * Suspend a user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspend(User $user)
    {
        try {
            // Check if user is not suspending themselves
            if (Auth::id() === $user->id) {
                return response()->json([
                    'message' => 'You cannot suspend your own account'
                ], 403);
            }
            
            // Suspend user - ALWAYS set both is_suspended and status
            $user->update([
                'is_suspended' => true,
                'status' => 'suspended'
            ]);
            
            // Force logout all of the user's sessions
            \App\Models\Session::where('user_id', $user->id)->update([
                'is_active' => false
            ]);
            
            // Log the suspension
            Log::info('User suspended', [
                'admin_id' => Auth::id(),
                'suspended_user_id' => $user->id,
                'suspended_user_email' => $user->email
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'User suspended successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error suspending user', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to suspend user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while suspending the user'
            ], 500);
        }
    }
    
    /**
     * Unsuspend a user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsuspend(User $user)
    {
        try {
            // Before unsuspending, check if the user has any active sessions
            $hasActiveSessions = \App\Models\Session::where('user_id', $user->id)
                ->where('is_active', true)
                ->exists();
                
            // Unsuspend user - ALWAYS update both is_suspended and status
            $user->update([
                'is_suspended' => false,
                'status' => $hasActiveSessions ? 'active' : 'inactive' // Set to active only if they have an active session
            ]);
            
            // Log the unsuspension
            Log::info('User unsuspended', [
                'admin_id' => Auth::id(),
                'unsuspended_user_id' => $user->id,
                'unsuspended_user_email' => $user->email,
                'new_status' => $user->status
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'User unsuspended successfully',
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error unsuspending user', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to unsuspend user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while unsuspending the user'
            ], 500);
        }
    }

    /**
     * Change the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request, User $user)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'password' => 'required|string|min:8|confirmed',
            ]);
            
            // Hash the new password
            $hashedPassword = Hash::make($validated['password']);
            
            // Update the user's password
            $user->update([
                'password' => $hashedPassword,
            ]);
            
            // Log the password change
            Log::info('User password changed by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error changing user password', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to change user password',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while changing the password'
            ], 500);
        }
    }
} 
