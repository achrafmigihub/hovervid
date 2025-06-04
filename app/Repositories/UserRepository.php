<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    /**
     * Get users with optional filtering and pagination
     *
     * @param array $params
     * @return array
     */
    public function getUsers(array $params = []): array
    {
        $query = User::query();

        // Apply search filter if provided
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Apply role filter if provided
        if (!empty($params['role'])) {
            $query->where('role', $params['role']);
        }

        // Apply status filter if provided
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Apply suspension filter if provided
        if (isset($params['is_suspended'])) {
            $query->where('is_suspended', $params['is_suspended']);
        }

        // Get total count before pagination
        $total = $query->count();

        // Apply sorting - fix parameter mapping
        $sortBy = $params['sort'] ?? 'created_at';
        $sortDir = $params['order'] ?? 'desc';
        
        $allowedSortFields = ['id', 'name', 'email', 'role', 'status', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortDir);
        }

        // Apply pagination - fix parameter mapping
        $page = max(1, intval($params['page'] ?? 1));
        $perPage = min(100, max(1, intval($params['itemsPerPage'] ?? 10)));
        
        $users = $query->offset(($page - 1) * $perPage)
                      ->limit($perPage)
                      ->get();

        // Return structure matching AdminUserController expectations
        return [
            'users' => $users,
            'totalUsers' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $perPage)
        ];
    }

    /**
     * Get a user by ID
     *
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Create a new user
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        return User::create($data);
    }

    /**
     * Update a user
     *
     * @param int $id
     * @param array $data
     * @return User|null
     */
    public function updateUser(int $id, array $data): ?User
    {
        $user = User::find($id);
        if ($user) {
            $user->update($data);
            return $user;
        }
        return null;
    }

    /**
     * Delete a user
     *
     * @param int $id
     * @return bool
     */
    public function deleteUser(int $id): bool
    {
        $user = User::find($id);
        if ($user) {
            return $user->delete();
        }
        return false;
    }

    /**
     * Get users by role
     *
     * @param string $role
     * @return Collection
     */
    public function getUsersByRole(string $role): Collection
    {
        return User::where('role', $role)->get();
    }

    /**
     * Get suspended users
     *
     * @return Collection
     */
    public function getSuspendedUsers(): Collection
    {
        return User::where('is_suspended', true)
                   ->orWhere('status', 'suspended')
                   ->get();
    }

    /**
     * Get active users
     *
     * @return Collection
     */
    public function getActiveUsers(): Collection
    {
        return User::where('status', 'active')
                   ->where('is_suspended', false)
                   ->get();
    }

    /**
     * Search users by query
     *
     * @param string $query
     * @param int $limit
     * @return Collection
     */
    public function searchUsers(string $query, int $limit = 10): Collection
    {
        return User::where('name', 'ILIKE', "%{$query}%")
                   ->orWhere('email', 'ILIKE', "%{$query}%")
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get user statistics
     *
     * @return array
     */
    public function getUserStats(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'suspended_users' => User::where('is_suspended', true)->count(),
            'admin_users' => User::where('role', 'admin')->count(),
            'client_users' => User::where('role', 'client')->count(),
        ];
    }
} 