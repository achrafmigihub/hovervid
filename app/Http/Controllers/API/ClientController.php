<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ClientController extends Controller
{
    /**
     * Get client dashboard data
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            
            // Cache key for this user's dashboard data
            $cacheKey = "dashboard_data_{$user->id}";
            
            // Try to get cached data first
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                return response()->json([
                    'success' => true,
                    'message' => 'Client dashboard data retrieved from cache',
                    'data' => $cachedData
                ]);
            }
            
            // If no cache, fetch fresh data
            $data = [
                'totalViews' => 1250,
                'activePlugins' => 1,
                'monthlyGrowth' => 15,
                'lastUpdate' => now()->format('M d, Y'),
                'account' => [
                    'status' => 'active',
                    'planDetails' => 'Basic',
                    'usageStats' => [
                        'storage' => '25%',
                        'bandwidth' => '18%'
                    ]
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'domain_id' => $user->domain_id,
                    'domain' => $user->domain ? $user->domain->domain : null
                ]
            ];
            
            // Cache the data for 5 minutes
            Cache::put($cacheKey, $data, now()->addMinutes(5));
            
            return response()->json([
                'success' => true,
                'message' => 'Client dashboard accessed successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching client dashboard data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data'
            ], 500);
        }
    }

    /**
     * Get client dashboard statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dashboardStats(Request $request)
    {
        try {
            $user = $request->user();
            
            // Cache key for this user's dashboard stats
            $cacheKey = "dashboard_stats_{$user->id}";
            
            // Try to get cached data first
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                return response()->json($cachedData);
            }
            
            // Calculate actual stats from user's domain and usage
            $domain = $user->domain;
            $totalViews = 1250; // In future, calculate from actual data
            $activePlugins = 0; // Plugin status tracking has been removed
            $monthlyGrowth = 15; // In future, calculate from historical data
            
            $stats = [
                'totalViews' => $totalViews,
                'activePlugins' => $activePlugins,
                'monthlyGrowth' => $monthlyGrowth,
                'lastUpdate' => now()->format('M d, Y')
            ];
            
            // Cache the stats for 5 minutes
            Cache::put($cacheKey, $stats, now()->addMinutes(5));
            
            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Error fetching client dashboard stats: ' . $e->getMessage());
            return response()->json([
                'totalViews' => 0,
                'activePlugins' => 0,
                'monthlyGrowth' => 0,
                'lastUpdate' => now()->format('M d, Y')
            ], 500);
        }
    }

    /**
     * Set domain for the authenticated client
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setDomain(Request $request)
    {
        try {
            $user = $request->user();
            
            // Validate the request
            $validator = Validator::make($request->all(), [
                'domain' => 'required|string|max:255|regex:/^[a-zA-Z0-9][a-zA-Z0-9-]*[a-zA-Z0-9]*\.[a-zA-Z]{2,}$/'
            ], [
                'domain.regex' => 'Please enter a valid domain name (e.g., example.com)'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $domainName = strtolower(trim($request->domain));

            // Start a database transaction
            DB::beginTransaction();

            try {
                // Check if domain already exists
                $existingDomain = Domain::where('domain', $domainName)->first();
                
                if ($existingDomain) {
                    // Check if it's already owned by another user
                    if ($existingDomain->user_id && $existingDomain->user_id !== $user->id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'This domain is already registered by another user'
                        ], 409);
                    }
                    
                    // If it's not owned by anyone or owned by current user, assign it
                    $domain = $existingDomain;
                    $domain->user_id = $user->id;
                    $domain->save();
                } else {
                    // Create new domain
                    $domain = Domain::create([
                        'domain' => $domainName,
                        'user_id' => $user->id,
                        'platform' => 'wordpress', // Default platform
                        'status' => 'active',
                        'is_active' => true,
                        'is_verified' => false
                    ]);
                }

                // Update user's domain_id
                $user->domain_id = $domain->id;
                $user->save();

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Domain set successfully',
                    'data' => [
                        'domain' => [
                            'id' => $domain->id,
                            'domain' => $domain->domain,
                            'status' => $domain->status
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error setting domain for user: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'domain' => $request->domain ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while setting the domain'
            ], 500);
        }
    }

    /**
     * Get client's domain information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDomain(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user->domain_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'No domain associated with this account',
                    'data' => null
                ]);
            }

            $domain = Domain::find($user->domain_id);
            
            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found'
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
                        'updated_at' => $domain->updated_at
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching client domain: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch domain information'
            ], 500);
        }
    }
} 
