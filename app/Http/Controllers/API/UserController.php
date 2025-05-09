<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            // Get parameters for filtering and pagination
            $search = $request->query('q', '');
            $role = $request->query('role');
            $itemsPerPage = (int)$request->query('itemsPerPage', 10);
            $page = (int)$request->query('page', 1);
            $sortBy = $request->query('sortBy');
            $orderBy = $request->query('orderBy', 'asc');
            
            // Start query
            $query = User::query();
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }
            
            // Apply role filter
            if (!empty($role)) {
                $query->where('role', $role);
            }
            
            // Apply sorting
            if (!empty($sortBy)) {
                $query->orderBy($sortBy, $orderBy);
            } else {
                $query->orderBy('created_at', 'desc');
            }
            
            // Count total users after filtering
            $totalUsers = $query->count();
            
            // Apply pagination
            if ($itemsPerPage > 0) {
                $users = $query->skip(($page - 1) * $itemsPerPage)
                              ->take($itemsPerPage)
                              ->get();
            } else {
                $users = $query->get();
            }
            
            // Transform users for frontend display
            $transformedUsers = $users->map(function($user) {
                return [
                    'id' => $user->id,
                    'fullName' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'currentPlan' => 'Basic', // Placeholder - you can add a plan/subscription relationship
                    'status' => 'active', // Placeholder - you can add status field to user model
                    'avatar' => null, // Placeholder for avatar
                    'billing' => 'Auto Debit', // Placeholder
                    'company' => 'HoverVid', // Placeholder
                    'country' => 'USA', // Placeholder
                    'contact' => '(123) 456-7890', // Placeholder
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ];
            });
            
            return response()->json([
                'users' => $transformedUsers,
                'totalUsers' => $totalUsers,
                'page' => $page,
                'totalPages' => $itemsPerPage > 0 ? ceil($totalUsers / $itemsPerPage) : 1,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch users',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fullName' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'role' => 'required|string|in:admin,client',
                'password' => 'sometimes|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $user = new User();
            $user->name = $request->fullName;
            $user->email = $request->email;
            $user->role = $request->role;
            $user->password = Hash::make($request->password ?? 'password123'); // Default password if not provided
            $user->email_verified_at = now(); // Auto verify email for admin created users
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'user' => $user
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Transform user for frontend display
            $userData = [
                'id' => $user->id,
                'fullName' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'currentPlan' => 'Basic', // Placeholder
                'status' => 'active', // Placeholder
                'avatar' => null, // Placeholder
                'billing' => 'Auto Debit', // Placeholder
                'company' => 'HoverVid', // Placeholder
                'country' => 'USA', // Placeholder
                'contact' => '(123) 456-7890', // Placeholder
                'language' => 'English', // Placeholder
                'taskDone' => 5, // Placeholder
                'projectDone' => 2, // Placeholder
                'taxId' => 'TAX-' . $user->id, // Placeholder
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
            
            return response()->json($userData);
            
        } catch (\Exception $e) {
            Log::error('Error fetching user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'fullName' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
                'role' => 'sometimes|required|string|in:admin,client',
                'password' => 'sometimes|string|min:6',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Update user fields
            if ($request->has('fullName')) {
                $user->name = $request->fullName;
            }
            
            if ($request->has('email')) {
                $user->email = $request->email;
            }
            
            if ($request->has('role')) {
                $user->role = $request->role;
            }
            
            if ($request->has('password')) {
                $user->password = Hash::make($request->password);
            }
            
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Prevent deleting self
            if (Auth::id() == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot delete your own account'
                ], 403);
            }
            
            $user->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user',
                'debug' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }
} 