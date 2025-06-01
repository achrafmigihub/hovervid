<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PluginDomainValidationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class PluginDomainController extends Controller
{
    protected $domainValidationService;

    public function __construct(PluginDomainValidationService $domainValidationService)
    {
        $this->domainValidationService = $domainValidationService;
    }

    /**
     * Check if a domain is authorized to use the HoverVid plugin
     * This is the main endpoint the plugin will call during activation
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDomainAuthorization(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'domain' => 'required|string|max:255',
            ]);

            $domain = $request->input('domain');

            // Validate domain format
            if (!$this->domainValidationService->isValidDomainFormat($domain)) {
                return response()->json([
                    'success' => false,
                    'authorized' => false,
                    'domain_exists' => false,
                    'is_active' => false,
                    'message' => 'Invalid domain format provided.',
                    'code' => 'INVALID_DOMAIN_FORMAT'
                ], 400);
            }

            // Validate domain authorization
            $validation = $this->domainValidationService->validateDomain($domain);

            if (!$validation['authorized']) {
                // Return appropriate error response
                $statusCode = $validation['reason'] === 'validation_error' ? 500 : 403;
                
                return response()->json([
                    'success' => false,
                    'authorized' => false,
                    'domain_exists' => $validation['domain_exists'],
                    'is_active' => $validation['is_active'],
                    'message' => $validation['message'],
                    'code' => strtoupper($validation['reason'])
                ], $statusCode);
            }

            // Domain is authorized
            return response()->json([
                'success' => true,
                'authorized' => true,
                'domain_exists' => $validation['domain_exists'],
                'is_active' => $validation['is_active'],
                'is_verified' => $validation['is_verified'],
                'status' => $validation['status'],
                'message' => $validation['message'],
                'code' => 'DOMAIN_AUTHORIZED'
            ]);

        } catch (Exception $e) {
            Log::error("Plugin Domain Authorization Error: " . $e->getMessage(), [
                'domain' => $request->input('domain', 'unknown'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'authorized' => false,
                'domain_exists' => false,
                'is_active' => false,
                'message' => 'System error occurred during domain validation.',
                'code' => 'SYSTEM_ERROR'
            ], 500);
        }
    }

    /**
     * Health check endpoint for plugin API
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function healthCheck()
    {
        return response()->json([
            'success' => true,
            'message' => 'Plugin domain validation API is healthy.',
            'timestamp' => now()->toISOString(),
            'version' => '1.0.0'
        ]);
    }
} 
