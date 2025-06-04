<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Repositories\UserRepository;

class AdminUserController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    protected $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
        // Middleware is applied in routes file
    }

    /**
     * Display a listing of users with filtering, sorting, and pagination.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Authorize the request using policy
            $this->authorize('viewAny', User::class);
            
            // Extract query parameters
            $params = [
                'search' => $request->query('q', ''),
                'role' => $request->query('role'),
                'status' => $request->query('status'),
                'sort' => $this->mapFrontendToBackendSort($request->query('sortBy')),
                'order' => $request->query('orderBy', 'desc'),
                'itemsPerPage' => (int)$request->query('itemsPerPage', 10),
                'page' => (int)$request->query('page', 1),
            ];
            
            // Log the request for debugging
            Log::info('Fetching users with params', $params);
            
            // Use the repository to get users
            $result = $this->userRepository->getUsers($params);
            
            return response()->json([
                'users' => $result['users'],
                'totalUsers' => $result['totalUsers'],
                'page' => $result['page'],
                'lastPage' => $result['totalPages'],
            ]);
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
     * Map frontend sort parameters to database column names
     *
     * @param  string|null  $sortBy
     * @return string|null
     */
    private function mapFrontendToBackendSort(?string $sortBy): ?string
    {
        if (empty($sortBy)) {
            return 'created_at';
        }
        
        $sortMap = [
            'user' => 'name',
            'role' => 'role',
            'status' => 'status',
            'billing' => 'created_at', // Fallback since we don't have a billing column
            'plan' => 'created_at',    // Fallback since we don't have a plan column
        ];
        
        return $sortMap[$sortBy] ?? 'created_at';
    }

    /**
     * Store a newly created user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Authorize the request using policy
            $this->authorize('create', User::class);
            
            // Validate the request data
            $validator = Validator::make($request->all(), User::createRules());
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Get validated data
            $requestData = $validator->validated();
            
            // Prepare user data
            $userData = [
                'name' => $requestData['name'],
                'email' => $requestData['email'],
                'role' => $requestData['role'] ?? UserRoleEnum::CLIENT->value,
                'status' => $requestData['status'] ?? UserStatusEnum::ACTIVE->value,
            ];
            
            // Generate password if not provided
            if (empty($requestData['password'])) {
                $password = Str::random(10);
                $userData['password'] = Hash::make($password);
            } else {
                $userData['password'] = Hash::make($requestData['password']);
                $password = $requestData['password'];
            }
            
            // Create user
            $user = User::create($userData);
            
            Log::info('User created by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            return response()->json([
                'message' => 'User created successfully',
                'user' => new UserResource($user),
                'generated_password' => empty($requestData['password']) ? $password : null
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating user', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to create user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while creating the user'
            ], 500);
        }
    }

    /**
     * Display user details.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        try {
            // Authorize the request using policy
            $this->authorize('view', $user);
            
            return response()->json([
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'id' => $user->id,
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
     * Update user details.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, User $user)
    {
        try {
            // Authorize the request using policy
            $this->authorize('update', $user);
            
            // Handle frontend format (fullName -> name)
            $requestData = $request->all();
            if (isset($requestData['fullName']) && !isset($requestData['name'])) {
                $requestData['name'] = $requestData['fullName'];
            }
            
            // Use dynamic rules based on the user ID
            $validator = Validator::make($requestData, User::updateRules($user->id));

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Prevent role escalation (e.g., if current user is not admin)
            $authUser = Auth::user();
            if (
                isset($requestData['role']) &&
                $requestData['role'] === UserRoleEnum::ADMIN->value &&
                $authUser->role !== UserRoleEnum::ADMIN
            ) {
                return response()->json([
                    'message' => 'You do not have permission to assign admin role'
                ], 403);
            }
            
            // Update fields that are present in the request
            if (isset($requestData['name'])) {
                $user->name = $requestData['name'];
            }
            
            if (isset($requestData['email'])) {
                $user->email = $requestData['email'];
            }
            
            if (isset($requestData['role'])) {
                $user->role = $requestData['role'];
            }
            
            if (isset($requestData['status'])) {
                $user->status = $requestData['status'];
            }
            
            if (isset($requestData['password'])) {
                $user->password = Hash::make($requestData['password']);
            }
            
            $user->save();
            
            Log::info('User updated by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'User updated successfully',
                'user' => new UserResource($user)
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating user', [
                'id' => $user->id,
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
     * Soft delete a user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(User $user)
    {
        try {
            // Authorize the request using policy
            $this->authorize('delete', $user);
            
            // Prevent self-deletion
            if (Auth::id() == $user->id) {
                return response()->json([
                    'message' => 'You cannot delete your own account'
                ], 403);
            }
            
            // Soft delete the user
            $user->delete();
            
            Log::info('User deleted by admin', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id
            ]);

            // Return 204 No Content as per requirements
            return response()->json(null, 204);
        } catch (\Exception $e) {
            Log::error('Error deleting user', [
                'id' => $user->id,
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
     * Display user statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        try {
            // Authorize the request using policy
            $this->authorize('viewAny', User::class);
            
            // Get user statistics from repository
            $stats = $this->userRepository->getUserStats();
            
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error fetching user statistics', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to fetch user statistics',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'An error occurred while fetching user statistics'
            ], 500);
        }
    }

    /**
     * Direct user update endpoint (for frontend compatibility).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function directUpdate(Request $request)
    {
        try {
            // Get the user ID from query parameter
            $userId = $request->query('id');
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required',
                ], 400);
            }

            // Find the user
            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            // Authorize the request using policy
            $this->authorize('update', $user);

            // Get all input data
            $data = $request->all();
            $updateData = [];
            $errors = [];

            // Validate name
            if (isset($data['name'])) {
                $name = trim($data['name']);
                if (empty($name)) {
                    $errors['name'] = ['Name is required'];
                } else {
                    $updateData['name'] = $name;
                }
            }

            // Validate email
            if (isset($data['email'])) {
                $email = trim($data['email']);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = ['Please provide a valid email address'];
                } else {
                    // Check if email is already taken by another user
                    $existingUser = User::where('email', $email)->where('id', '!=', $userId)->first();
                    if ($existingUser) {
                        $errors['email'] = ['This email is already in use'];
                    } else {
                        $updateData['email'] = $email;
                    }
                }
            }

            // Validate role
            if (isset($data['role'])) {
                $role = trim($data['role']);
                if (!in_array($role, ['admin', 'client'])) {
                    $errors['role'] = ['Role must be admin or client'];
                } else {
                    $updateData['role'] = $role;
                }
            }

            // Validate status
            if (isset($data['status'])) {
                $status = trim($data['status']);
                $validStatuses = ['active', 'inactive', 'pending', 'banned', 'suspended'];
                if (!in_array($status, $validStatuses)) {
                    $errors['status'] = ['Status must be one of: ' . implode(', ', $validStatuses)];
                } else {
                    $updateData['status'] = $status;
                    
                    // Handle suspension logic
                    if ($status === 'suspended') {
                        $updateData['is_suspended'] = true;
                    } else {
                        $updateData['is_suspended'] = false;
                    }
                }
            }

            // If there are validation errors, return them
            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ], 422);
            }

            // Update the user
            if (!empty($updateData)) {
                $user->update($updateData);
                $user->refresh();
            }

            Log::info('User updated via direct endpoint', [
                'admin_id' => Auth::id(),
                'user_id' => $user->id,
                'updated_fields' => array_keys($updateData)
            ]);

            // Return success response in the format expected by frontend
            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'status' => $user->status,
                    'is_suspended' => $user->is_suspended,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in direct user update', [
                'user_id' => $request->query('id'),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the user',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
} 