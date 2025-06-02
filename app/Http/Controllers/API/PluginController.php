<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;

class PluginController extends Controller
{
    /**
     * Verify domain status for WordPress plugin
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function verifyDomain(Request $request): JsonResponse
    {
        try {
            $domain = $request->input('domain');
            
            if (empty($domain)) {
                return response()->json([
                    'success' => false,
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'No domain provided',
                    'error' => true
                ], 400);
            }

            // Log the verification request
            Log::info("HoverVid Plugin: Domain verification request for {$domain}");

            // Find domain in database
            $domainRecord = Domain::where('domain', $domain)->first();

            if (!$domainRecord) {
                Log::info("HoverVid Plugin: Domain {$domain} not found in database");
                return response()->json([
                    'success' => false,
                    'is_verified' => false,
                    'domain_exists' => false,
                    'message' => 'This domain is not authorized to use the HoverVid plugin. Please contact the plugin provider.',
                    'error' => false
                ]);
            }

            // Check verification status
            $isVerified = (bool) $domainRecord->is_verified;
            
            Log::info("HoverVid Plugin: Domain {$domain} verification status: " . ($isVerified ? 'verified' : 'not verified'));

            return response()->json([
                'success' => true,
                'is_verified' => $isVerified,
                'domain_exists' => true,
                'message' => $isVerified 
                    ? 'Domain is verified and active.' 
                    : 'Your subscription or license has expired. Please contact support to renew your access.',
                'error' => false,
                'domain_data' => [
                    'id' => $domainRecord->id,
                    'domain' => $domainRecord->domain,
                    'is_verified' => $isVerified,
                    'platform' => $domainRecord->platform,
                    'updated_at' => $domainRecord->updated_at->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('HoverVid Plugin API Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'is_verified' => false,
                'domain_exists' => false,
                'message' => 'System error occurred. Please contact the plugin provider.',
                'error' => true
            ], 500);
        }
    }

    /**
     * Update plugin status for a domain
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            $domain = $request->input('domain');
            $status = $request->input('status', 'active');

            if (empty($domain)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No domain provided'
                ], 400);
            }

            $domainRecord = Domain::where('domain', $domain)->first();

            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found'
                ], 404);
            }

            // Update plugin status
            $domainRecord->plugin_status = $status;
            $domainRecord->save();

            Log::info("HoverVid Plugin: Status updated for {$domain} to {$status}");

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'domain' => $domain,
                'status' => $status
            ]);

        } catch (\Exception $e) {
            Log::error('HoverVid Plugin Status Update Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Get real-time domain status (for periodic checks)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getDomainStatus(Request $request): JsonResponse
    {
        $domain = $request->input('domain');
        
        if (empty($domain)) {
            return response()->json([
                'success' => false,
                'message' => 'Domain parameter required'
            ], 400);
        }

        $domainRecord = Domain::where('domain', $domain)->first();

        if (!$domainRecord) {
            return response()->json([
                'success' => true,
                'is_verified' => false,
                'domain_exists' => false,
                'timestamp' => now()->toISOString()
            ]);
        }

        return response()->json([
            'success' => true,
            'is_verified' => (bool) $domainRecord->is_verified,
            'domain_exists' => true,
            'plugin_status' => $domainRecord->plugin_status,
            'timestamp' => now()->toISOString()
        ]);
    }
} 
