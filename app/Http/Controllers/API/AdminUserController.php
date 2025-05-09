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
    public function show($id)
    {
        try {
            // Get the user
            $user = User::findOrFail($id);
            
            // Authorize the request using policy
            $this->authorize('view', $user);
            
            // Use the repository to get user details
            $userData = $this->userRepository->getUserById($id);
            
            return response()->json([
                'user' => $userData
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'User not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error fetching user', [
                'id' => $id,
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
} 