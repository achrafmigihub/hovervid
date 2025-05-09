<?php

namespace App\Repositories;

use App\Models\User;
use App\Http\Resources\UserResource;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    /**
     * Get users with filtering, sorting, and pagination.
     *
     * @param  array  $params
     * @return array
     */
    public function getUsers(array $params = []): array
    {
        try {
            // Extract parameters
            $search = $params['search'] ?? null;
            $role = $params['role'] ?? null;
            $status = $params['status'] ?? null;
            $sort = $params['sort'] ?? 'created_at';
            $order = $params['order'] ?? 'desc';
            $itemsPerPage = (int)($params['itemsPerPage'] ?? 15);
            $page = (int)($params['page'] ?? 1);
            
            // Start query
            $query = User::query();
            
            // Apply search filter if provided
            if ($search) {
                $query->search($search);
            }
            
            // Apply role filter if provided
            if ($role) {
                if ($role === 'admin') {
                    $query->admin();
                } elseif (in_array($role, UserRoleEnum::values())) {
                    $query->where('role', $role);
                }
            }
            
            // Apply status filter if provided
            if ($status && in_array($status, UserStatusEnum::values())) {
                $query->where('status', $status);
            }
            
            // Apply sorting
            if (in_array($sort, ['name', 'email', 'role', 'status', 'created_at'])) {
                $query->orderBy($sort, $order === 'desc' ? 'desc' : 'asc');
            }
            
            // Handle special case for -1 (all items)
            if ($itemsPerPage === -1) {
                $users = $query->get();
                $userResources = UserResource::collection($users);
                
                return [
                    'users' => $userResources,
                    'totalUsers' => $users->count(),
                    'page' => 1,
                    'totalPages' => 1,
                ];
            }
            
            // Debug: Log the actual SQL query being executed
            DB::enableQueryLog();
            
            // Get paginated results
            $users = $query->paginate($itemsPerPage, ['*'], 'page', $page);
            
            // Get collection of resources
            $userResources = UserResource::collection($users);
            
            // Log the SQL query for debugging
            $queryLog = DB::getQueryLog();
            Log::info('User query executed', [
                'query' => end($queryLog),
                'resultCount' => $users->count(),
                'totalCount' => $users->total()
            ]);
            
            // Format response
            return [
                'users' => $userResources,
                'totalUsers' => $users->total(),
                'page' => $users->currentPage(),
                'totalPages' => $users->lastPage(),
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching users in repository', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get a single user by ID.
     *
     * @param  int  $id
     * @return \App\Http\Resources\UserResource
     */
    public function getUserById(int $id): UserResource
    {
        try {
            $user = User::findOrFail($id);
            
            // Load relationships if needed
            $user->load(['subscriptions', 'payments']);
            
            return new UserResource($user);
        } catch (\Exception $e) {
            Log::error('Error fetching user by ID in repository', [
                'id' => $id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
} 