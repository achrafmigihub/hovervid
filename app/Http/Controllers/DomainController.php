<?php

namespace App\Http\Controllers;

use PDO;
use Exception;
use App\Models\Domain;
use App\Models\License;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DomainController extends Controller
{
    /**
     * Constructor - initialize the controller
     */
    public function __construct()
    {
        // No middleware here
    }
    
    /**
     * Get all domains with pagination and filtering
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Ensure the response is treated as JSON
        $request->headers->set('Accept', 'application/json');
        
        try {
            // Parse query parameters
            $search = $request->query('q', '');
            $page = max(1, intval($request->query('page', 1)));
            $perPage = max(1, min(100, intval($request->query('itemsPerPage', 10))));
            $sortBy = $request->query('sortBy', 'created_at');
            $orderBy = strtoupper($request->query('orderBy', 'desc')) === 'DESC' ? 'DESC' : 'ASC';
            
            // Calculate offset
            $offset = ($page - 1) * $perPage;
            
            // Build base query
            $query = "
                SELECT
                    d.id,
                    d.domain_name AS domain,
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
            ";
            
            // Add search condition if provided
            $params = [];
            if (!empty($search)) {
                $query .= " WHERE d.domain_name ILIKE :search OR u.name ILIKE :search OR u.email ILIKE :search";
                $params['search'] = "%$search%";
            }
            
            // Add sorting
            $allowedColumns = ['id', 'domain', 'status', 'is_active', 'created_at', 'user_name', 'license_expiry'];
            $sortColumn = in_array($sortBy, $allowedColumns) ? $sortBy : 'created_at';
            
            // Map frontend column names to database columns
            $columnMap = [
                'user_name' => 'u.name',
                'license_expiry' => 'l.expires_at',
                'created_at' => 'd.created_at',
                'id' => 'd.id',
                'domain' => 'd.domain_name',
                'status' => 'd.status',
                'is_active' => 'd.is_active'
            ];
            
            $sortColumnDb = $columnMap[$sortColumn] ?? 'd.created_at';
            $query .= " ORDER BY $sortColumnDb $orderBy";
            
            // Count total before adding limit
            $countQuery = preg_replace('/SELECT.*?FROM/s', 'SELECT COUNT(*) FROM', $query);
            $countQuery = preg_replace('/ORDER BY.*$/s', '', $countQuery);
            
            // Execute count query
            $stmt = DB::getPdo()->prepare($countQuery);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->execute();
            $totalDomains = $stmt->fetchColumn();
            
            // Add pagination
            $query .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $perPage;
            $params['offset'] = $offset;
            
            // Execute main query
            $stmt = DB::getPdo()->prepare($query);
            foreach ($params as $key => $value) {
                if ($key === 'limit' || $key === 'offset') {
                    $stmt->bindValue(":$key", $value, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(":$key", $value);
                }
            }
            $stmt->execute();
            $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return response()->json([
                'success' => true,
                'domains' => $domains,
                'totalDomains' => $totalDomains,
                'page' => $page,
                'perPage' => $perPage,
                'lastPage' => ceil($totalDomains / $perPage)
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching domains: ' . $e->getMessage());
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
            
            // Update domain status
            $updated = DB::update("
                UPDATE domains 
                SET status = 'active', is_active = true, updated_at = CURRENT_TIMESTAMP 
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
            
            // Update domain status
            $updated = DB::update("
                UPDATE domains 
                SET status = 'inactive', is_active = false, updated_at = CURRENT_TIMESTAMP 
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
            $domain = DB::selectOne("SELECT domain_name FROM domains WHERE id = ?", [$id]);
            
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
            // Validate the request
            $validated = $request->validate([
                'domain' => 'required|string|max:255|unique:domains,domain_name',
                'user_id' => 'required|exists:users,id',
                'platform' => 'required|string|max:50',
            ]);
            
            // Begin transaction
            DB::beginTransaction();
            
            // Create the domain
            $domainId = DB::table('domains')->insertGetId([
                'domain_name' => $validated['domain'],
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
                    d.domain_name AS domain,
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
                'domain' => 'sometimes|string|max:255|unique:domains,domain_name,' . $id . ',id',
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
                $updateFields[] = 'domain_name = ?';
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
                    d.domain_name AS domain,
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
 