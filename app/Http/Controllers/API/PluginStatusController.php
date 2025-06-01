<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PluginStatusController extends Controller
{
    /**
     * Update plugin status for a domain
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'domain_name' => 'required|string',
                'status' => 'required|string|in:active,inactive,not_installed,pending_activation,pending_deactivation,suspended,error',
                'reason' => 'nullable|string|max:500',
                'user_agent' => 'nullable|string',
                'ip_address' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $domainName = $request->input('domain_name');
            $status = $request->input('status');
            $reason = $request->input('reason', '');
            $userAgent = $request->input('user_agent');
            $ipAddress = $request->input('ip_address', $request->ip());

            // Find domain record
            $domain = Domain::where('domain', $domainName)->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found. Please register the domain first.'
                ], 404);
            }

            // Get the old status before updating
            $oldStatus = $domain->plugin_status;

            // Update the plugin status
            $domain->plugin_status = $status;
            $domain->updated_at = now();
            $domain->save();

            // Log the status change
            Log::info('Plugin status updated', [
                'domain_id' => $domain->id,
                'domain_name' => $domainName,
                'old_status' => $oldStatus,
                'new_status' => $status,
                'reason' => $reason,
                'user_agent' => $userAgent,
                'ip_address' => $ipAddress,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Plugin status updated successfully',
                'data' => [
                    'domain_id' => $domain->id,
                    'domain_name' => $domainName,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'updated_at' => $domain->updated_at->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Plugin status update error: ' . $e->getMessage(), [
                'domain_name' => $request->input('domain_name'),
                'status' => $request->input('status'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating plugin status'
            ], 500);
        }
    }

    /**
     * Get plugin status for a domain
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            $domainName = $request->query('domain_name');

            if (!$domainName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name parameter'
                ], 400);
            }

            // Find domain record
            $domain = Domain::where('domain', $domainName)->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found',
                    'plugin_status' => 'not_found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'domain_id' => $domain->id,
                    'domain_name' => $domain->domain,
                    'plugin_status' => $domain->plugin_status,
                    'is_active' => $domain->is_active,
                    'is_verified' => $domain->is_verified,
                    'status' => $domain->status,
                    'last_updated' => $domain->updated_at?->toISOString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Plugin status retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving plugin status'
            ], 500);
        }
    }

    /**
     * Plugin activation endpoint
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function activate(Request $request): JsonResponse
    {
        $request->merge(['status' => 'active', 'reason' => 'Plugin activated via API']);
        return $this->updateStatus($request);
    }

    /**
     * Plugin deactivation endpoint
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deactivate(Request $request): JsonResponse
    {
        $request->merge(['status' => 'inactive', 'reason' => 'Plugin deactivated via API']);
        return $this->updateStatus($request);
    }

    /**
     * Get plugin status history for a domain
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getHistory(Request $request): JsonResponse
    {
        try {
            $domainName = $request->query('domain_name');
            $limit = $request->query('limit', 20);

            if (!$domainName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name parameter'
                ], 400);
            }

            // Find domain record
            $domain = Domain::where('domain', $domainName)->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found'
                ], 404);
            }

            // Get plugin status logs if the table exists
            $history = [];
            
            try {
                $history = DB::table('plugin_status_logs')
                    ->where('domain_id', $domain->id)
                    ->orderBy('changed_at', 'desc')
                    ->limit($limit)
                    ->get();
            } catch (\Exception $e) {
                // If plugin_status_logs table doesn't exist, return empty history
                Log::info('Plugin status logs table not found or accessible: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'domain_id' => $domain->id,
                    'domain_name' => $domain->domain,
                    'current_status' => $domain->plugin_status,
                    'history' => $history,
                    'total_records' => count($history)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Plugin status history retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving plugin status history'
            ], 500);
        }
    }
}
