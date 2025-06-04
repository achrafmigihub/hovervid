<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\DomainRequest;
use App\Models\Domain;
use App\Models\User;
use App\Enums\UserRoleEnum;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ClientDomainController extends Controller
{
    /**
     * Set domain for the authenticated client (used by popup card)
     * 
     * @param DomainRequest $request
     * @return JsonResponse
     */
    public function setDomain(DomainRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get user role - handle both string and enum values
            $userRole = is_object($user->role) ? $user->role->value : $user->role;
            $requiredRole = UserRoleEnum::CLIENT->value;
            
            if (!$user || $userRole !== $requiredRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only client users can register domains'
                ], 403);
            }

            // Start database transaction
            DB::beginTransaction();

            // Get validated data
            $validatedData = $request->getValidatedData();
            $domainName = $validatedData['domain'];

            // Deactivate any existing active domains for this user
            Domain::where('user_id', $user->id)
                  ->where('is_active', true)
                  ->update([
                      'is_active' => false,
                      'status' => 'inactive'
                  ]);

            // Check if domain already exists
            $existingDomain = Domain::where('domain', $domainName)->first();
            
            if ($existingDomain) {
                // Check if it's owned by another user and is active
                if ($existingDomain->user_id && $existingDomain->user_id !== $user->id && $existingDomain->is_active) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This domain is already registered by another user'
                    ], 409);
                }
                
                // If it's unassigned or belongs to current user, claim it
                $domain = $existingDomain;
                $domain->user_id = $user->id;
                $domain->status = 'active';
                $domain->is_active = true;
                $domain->save();
            } else {
                // Create new domain
                $domain = Domain::create([
                    'user_id' => $user->id,
                    'domain' => $domainName,
                    'platform' => $validatedData['platform'],
                    'status' => 'active',
                    'is_active' => true,
                    'is_verified' => false
                ]);
            }

            // Update user's domain_id to link the primary domain
            User::where('id', $user->id)->update(['domain_id' => $domain->id]);

            // Commit the transaction
            DB::commit();

            // Clear cache for this user to ensure fresh data on next request
            Cache::forget("dashboard_data_{$user->id}");
            Cache::forget("dashboard_stats_{$user->id}");

            // Log successful domain registration
            Log::info('Domain registered successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'domain_id' => $domain->id,
                'domain_name' => $domain->domain
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain registered successfully',
                'data' => [
                    'domain' => [
                        'id' => $domain->id,
                        'domain' => $domain->domain,
                        'platform' => $domain->platform,
                        'status' => $domain->status,
                        'is_active' => $domain->is_active,
                        'is_verified' => $domain->is_verified,
                        'created_at' => $domain->created_at,
                        'url' => $domain->url
                    ],
                    'user' => [
                        'id' => $user->id,
                        'domain_id' => $domain->id
                    ]
                ]
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            // Log the error
            Log::error('Error registering domain', [
                'user_id' => Auth::id(),
                'domain' => $request->input('domain'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while registering the domain. Please try again.'
            ], 500);
        }
    }

    /**
     * Get domain information for the authenticated client
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getDomain(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $domain = Domain::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->first();
            
            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active domain registered for this account'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'domain' => [
                        'id' => $domain->id,
                        'domain' => $domain->domain,
                        'platform' => $domain->platform,
                        'status' => $domain->status,
                        'is_active' => $domain->is_active,
                        'is_verified' => $domain->is_verified,
                        'created_at' => $domain->created_at,
                        'updated_at' => $domain->updated_at,
                        'last_checked_at' => $domain->last_checked_at,
                        'url' => $domain->url,
                        'is_ready' => $domain->isReady()
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Error retrieving domain', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving domain information'
            ], 500);
        }
    }

    /**
     * Update domain settings for the authenticated client
     * 
     * @param DomainRequest $request
     * @return JsonResponse
     */
    public function updateDomain(DomainRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $domain = Domain::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->first();
            
            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active domain found for this account'
                ], 404);
            }

            // Start database transaction
            DB::beginTransaction();

            // Get validated data from the request
            $validatedData = $request->validated();

            // Update the domain
            $domain->update($validatedData);

            // Commit the transaction
            DB::commit();

            // Clear cache for this user to ensure fresh data on next request
            Cache::forget("dashboard_data_{$user->id}");
            Cache::forget("dashboard_stats_{$user->id}");

            // Log successful domain update
            Log::info('Domain updated successfully', [
                'user_id' => $user->id,
                'domain_id' => $domain->id,
                'updated_fields' => array_keys($validatedData)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain updated successfully',
                'data' => [
                    'domain' => [
                        'id' => $domain->id,
                        'domain' => $domain->domain,
                        'platform' => $domain->platform,
                        'status' => $domain->status,
                        'is_active' => $domain->is_active,
                        'is_verified' => $domain->is_verified,
                        'updated_at' => $domain->updated_at,
                        'url' => $domain->url,
                        'is_ready' => $domain->isReady()
                    ]
                ]
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            Log::error('Error updating domain', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the domain'
            ], 500);
        }
    }

    /**
     * Remove domain association for the authenticated client
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function removeDomain(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $domain = Domain::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->first();
            
            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active domain found for this account'
                ], 404);
            }

            // Start database transaction
            DB::beginTransaction();

            // Deactivate the domain (keep it in database for records)
            $domain->deactivate();

            // Clear the user's domain_id since the domain is no longer active
            User::where('id', $user->id)->update(['domain_id' => null]);

            // Commit the transaction
            DB::commit();

            // Clear cache for this user to ensure fresh data on next request
            Cache::forget("dashboard_data_{$user->id}");
            Cache::forget("dashboard_stats_{$user->id}");

            Log::info('Domain deactivated', [
                'user_id' => $user->id,
                'domain_id' => $domain->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Domain deactivated successfully'
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            DB::rollBack();
            
            Log::error('Error removing domain association', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while removing the domain association'
            ], 500);
        }
    }
} 
