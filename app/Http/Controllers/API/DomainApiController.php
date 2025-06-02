<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use PDO;
use Exception;
use App\Models\Domain;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DomainApiController extends Controller
{
    /**
     * Get all domains with pagination and filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // Ensure Accept header is set to JSON
            $request->headers->set('Accept', 'application/json');

            // Set default pagination parameters
            $page = $request->query('page', 1);
            $perPage = $request->query('per_page', 10);
            $sortBy = $request->query('sort_by', 'created_at');
            $sortDir = $request->query('sort_dir', 'desc');
            $search = $request->query('search', '');
            $status = $request->query('status');
            
            // Start query builder
            $query = DB::table('domains as d')
                ->select([
                    'd.id',
                    'd.domain',
                    'd.platform',
                    'd.created_at',
                    'd.plugin_status as status',
                    'd.is_active',
                    'd.is_verified',
                    'd.user_id',
                    'u.name as owner_name',
                    'u.email as owner_email',
                    'u.role as owner_role'
                ])
                ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
                ->groupBy(['d.id', 'd.domain', 'd.platform', 'd.created_at', 'd.plugin_status', 'd.is_active', 'd.is_verified', 'd.user_id', 'u.name', 'u.email', 'u.role']);
            
            // Apply search filter if provided
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('d.domain', 'ILIKE', "%{$search}%")
                        ->orWhere('u.name', 'ILIKE', "%{$search}%")
                        ->orWhere('u.email', 'ILIKE', "%{$search}%");
                });
            }
            
            // Apply status filter if provided (now filtering by plugin_status)
            if (!empty($status)) {
                $query->where('d.plugin_status', $status);
            }
            
            // Calculate total count before pagination
            $totalCount = DB::table('domains as d')
                ->leftJoin('users as u', 'u.id', '=', 'd.user_id')
                ->when(!empty($search), function ($q) use ($search) {
                    $q->where(function ($subQuery) use ($search) {
                        $subQuery->where('d.domain', 'ILIKE', "%{$search}%")
                                ->orWhere('u.name', 'ILIKE', "%{$search}%")
                                ->orWhere('u.email', 'ILIKE', "%{$search}%");
                    });
                })
                ->when(!empty($status), function ($q) use ($status) {
                    $q->where('d.plugin_status', $status);
                })
                ->count();
            
            // Apply sorting and pagination
            $sortColumn = $sortBy;
            if ($sortBy === 'domain') {
                $sortColumn = 'domain';
            }
            
            $query->orderBy("d.{$sortColumn}", $sortDir)
                  ->offset(($page - 1) * $perPage)
                  ->limit($perPage);
            
            // Execute query
            $domains = $query->get();
            
            // If no domains found, return empty result with friendly message
            if ($domains->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'total' => 0,
                        'page' => $page,
                        'per_page' => $perPage
                    ],
                    'message' => 'No domains found. Add your first domain to get started.'
                ]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $domains,
                'meta' => [
                    'total' => $totalCount,
                    'page' => $page,
                    'per_page' => $perPage
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch domains: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domains: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Activate a domain
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate($id)
    {
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Update domain plugin_status
            $updated = DB::update("
                UPDATE domains 
                SET plugin_status = 'active', is_active = true, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ", [$id]);
            
            if (!$updated) {
                throw new Exception('Domain not found');
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Domain activated successfully'
            ]);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error activating domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate domain: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Deactivate a domain
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivate($id)
    {
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Update domain plugin_status
            $updated = DB::update("
                UPDATE domains 
                SET plugin_status = 'inactive', is_active = false, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ", [$id]);
            
            if (!$updated) {
                throw new Exception('Domain not found');
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Domain deactivated successfully'
            ]);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error deactivating domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate domain: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete a domain
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Check if domain exists
            $domain = DB::selectOne("SELECT domain FROM domains WHERE id = ?", [$id]);
            
            if (!$domain) {
                throw new Exception('Domain not found');
            }
            
            // Delete domain
            $deleted = DB::delete("DELETE FROM domains WHERE id = ?", [$id]);
            
            if (!$deleted) {
                throw new Exception('Failed to delete domain');
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Domain deleted successfully'
            ]);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error deleting domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete domain: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify a domain
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify($id)
    {
        try {
            // Begin transaction
            DB::beginTransaction();
            
            // Update domain verification status
            $updated = DB::update("
                UPDATE domains 
                SET is_verified = true, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ", [$id]);
            
            if (!$updated) {
                throw new Exception('Domain not found');
            }
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Domain verified successfully'
            ]);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error verifying domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify domain: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Store a new domain
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // For client self-registration, auto-fill user_id and platform if not provided
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Validate the request
            $validated = $request->validate([
                'domain' => 'required|string|max:255|unique:domains,domain',
                'user_id' => 'sometimes|exists:users,id',
                'platform' => 'sometimes|string|max:50',
            ]);

            // Auto-fill missing fields for client self-registration
            if (!isset($validated['user_id'])) {
                $validated['user_id'] = $user->id;
            }
            
            if (!isset($validated['platform'])) {
                $validated['platform'] = 'wordpress'; // default platform
            }
            
            // Begin transaction
            DB::beginTransaction();
            
            // Create the domain
            $domainId = DB::table('domains')->insertGetId([
                'domain' => $validated['domain'],
                'user_id' => $validated['user_id'],
                'platform' => $validated['platform'],
                'status' => 'inactive',
                'is_active' => false,
                'is_verified' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get the newly created domain
            $domain = DB::selectOne("
                SELECT
                    d.id,
                    d.domain AS domain,
                    COALESCE(d.status, 'inactive') AS status,
                    COALESCE(d.is_active, false) AS is_active,
                    COALESCE(d.is_verified, false) AS is_verified,
                    d.created_at,
                    d.user_id,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.role AS user_role,
                    l.expires_at AS license_expiry
                FROM domains d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN licenses l ON l.user_id = d.user_id
                    AND l.status = 'active'
                WHERE d.id = ?
            ", [$domainId]);
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'domain' => $domain,
                'message' => 'Domain created successfully'
            ], 201);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error creating domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create domain: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update a domain
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'domain' => 'sometimes|string|max:255|unique:domains,domain,' . $id . ',id',
                'user_id' => 'sometimes|exists:users,id',
                'platform' => 'sometimes|string|max:50',
                'status' => 'sometimes|string|in:active,inactive,pending,suspended',
                'is_active' => 'sometimes|boolean',
                'is_verified' => 'sometimes|boolean',
            ]);
            
            // Begin transaction
            DB::beginTransaction();
            
            // Build update fields
            $updateFields = [];
            $params = [];
            
            if (isset($validated['domain'])) {
                $updateFields[] = 'domain = ?';
                $params[] = $validated['domain'];
            }
            
            if (isset($validated['user_id'])) {
                $updateFields[] = 'user_id = ?';
                $params[] = $validated['user_id'];
            }
            
            if (isset($validated['platform'])) {
                $updateFields[] = 'platform = ?';
                $params[] = $validated['platform'];
            }
            
            if (isset($validated['status'])) {
                $updateFields[] = 'status = ?';
                $params[] = $validated['status'];
                
                if ($validated['status'] === 'active') {
                    $updateFields[] = 'is_active = true';
                } else if ($validated['status'] === 'inactive' || $validated['status'] === 'suspended') {
                    $updateFields[] = 'is_active = false';
                }
            }
            
            if (isset($validated['is_active'])) {
                $updateFields[] = 'is_active = ?';
                $params[] = $validated['is_active'];
                
                if ($validated['is_active']) {
                    $updateFields[] = 'status = \'active\'';
                } else {
                    $updateFields[] = 'status = \'inactive\'';
                }
            }
            
            if (isset($validated['is_verified'])) {
                $updateFields[] = 'is_verified = ?';
                $params[] = $validated['is_verified'];
            }
            
            $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
            
            // Update domain
            $params[] = $id;
            $updated = DB::update(
                'UPDATE domains SET ' . implode(', ', $updateFields) . ' WHERE id = ?',
                $params
            );
            
            if (!$updated) {
                throw new Exception('Domain not found');
            }
            
            // Get the updated domain
            $domain = DB::selectOne("
                SELECT
                    d.id,
                    d.domain AS domain,
                    COALESCE(d.status, 'inactive') AS status,
                    COALESCE(d.is_active, false) AS is_active,
                    COALESCE(d.is_verified, false) AS is_verified,
                    d.created_at,
                    d.user_id,
                    u.name AS user_name,
                    u.email AS user_email,
                    u.role AS user_role,
                    l.expires_at AS license_expiry
                FROM domains d
                LEFT JOIN users u ON d.user_id = u.id
                LEFT JOIN licenses l ON l.user_id = d.user_id
                    AND l.status = 'active'
                WHERE d.id = ?
            ", [$id]);
            
            // Commit transaction
            DB::commit();
            
            return response()->json([
                'success' => true,
                'domain' => $domain,
                'message' => 'Domain updated successfully'
            ]);
        } catch (Exception $e) {
            // Rollback transaction
            DB::rollBack();
            
            Log::error('Error updating domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update domain: ' . $e->getMessage()
            ], 500);
        }
    }
}
