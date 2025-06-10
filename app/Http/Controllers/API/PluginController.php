<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Domain;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
    
    /**
     * Check if video is available for specific content by hash
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function checkVideoAvailability(Request $request): JsonResponse
    {
        try {
            $domain = $request->input('domain_name');
            $contentHash = $request->input('content_hash');
            
            if (empty($domain) || empty($contentHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name or content_hash'
                ], 400);
            }

            // Find domain in database
            $domainRecord = Domain::where('domain', $domain)->first();

            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found or not authorized'
                ], 404);
            }

            // Check if content exists and has video (try both original hash and domain-specific hash)
            $domainSpecificHash = $contentHash . '_' . $domainRecord->id;
            
            $content = \App\Models\Content::where('domain_id', $domainRecord->id)
                ->where(function($query) use ($contentHash, $domainSpecificHash) {
                    $query->where('id', $contentHash)
                          ->orWhere('id', $domainSpecificHash);
                })
                ->first();

            $hasVideo = $content && !empty($content->video_url);

            return response()->json([
                'success' => true,
                'data' => [
                    'has_video' => $hasVideo,
                    'content_hash' => $contentHash,
                    'matched_hash' => $content ? $content->id : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('HoverVid Plugin Video Check Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'System error occurred while checking video availability'
            ], 500);
        }
    }
    
    /**
     * Get video URL for specific content by hash
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getVideoByHash(Request $request): JsonResponse
    {
        try {
            $domain = $request->input('domain_name');
            $contentHash = $request->input('content_hash');
            
            if (empty($domain) || empty($contentHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name or content_hash'
                ], 400);
            }

            // Find domain in database
            $domainRecord = Domain::where('domain', $domain)->first();

            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found or not authorized'
                ], 404);
            }

            // Find content and get video URL (try both original hash and domain-specific hash)
            $domainSpecificHash = $contentHash . '_' . $domainRecord->id;
            
            $content = \App\Models\Content::where('domain_id', $domainRecord->id)
                ->where(function($query) use ($contentHash, $domainSpecificHash) {
                    $query->where('id', $contentHash)
                          ->orWhere('id', $domainSpecificHash);
                })
                ->first();

            if (!$content) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found'
                ], 404);
            }

            if (empty($content->video_url)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No video available for this content'
                ], 404);
            }

            // Ensure the video URL is properly formatted with bucket name
            $videoUrl = $content->video_url;
            
            // If URL doesn't include bucket, add it
            if (!str_contains($videoUrl, '/hovervid/')) {
                // Extract the path after the domain
                $parsedUrl = parse_url($videoUrl);
                $path = ltrim($parsedUrl['path'], '/');
                
                // Rebuild URL with bucket
                $videoUrl = 'https://s3.ca-central-1.wasabisys.com/hovervid/' . $path;
            }

            // Create proxy URL to avoid CORS issues
            $proxyUrl = url('/api/video-proxy/' . urlencode(base64_encode($videoUrl)));

            return response()->json([
                'success' => true,
                'data' => [
                    'video_url' => $proxyUrl,
                    'original_url' => $videoUrl,
                    'content_id' => $content->id,
                    'content_hash' => $contentHash,
                    'matched_hash' => $content->id
                ]
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');

        } catch (\Exception $e) {
            Log::error('HoverVid Plugin Get Video Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'System error occurred while fetching video'
            ], 500);
        }
    }
    
    /**
     * Batch check video availability for multiple content hashes
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function batchCheckVideoAvailability(Request $request): JsonResponse
    {
        try {
            $domain = $request->input('domain_name');
            $contentHashes = $request->input('content_hashes');
            
            if (empty($domain) || empty($contentHashes) || !is_array($contentHashes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name or content_hashes array'
                ], 400);
            }

            // Find domain in database
            $domainRecord = Domain::where('domain', $domain)->first();

            if (!$domainRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found or not authorized'
                ], 404);
            }

            // Create array of all possible hashes (original + domain-specific)
            $allPossibleHashes = [];
            foreach ($contentHashes as $hash) {
                $allPossibleHashes[] = $hash;
                $allPossibleHashes[] = $hash . '_' . $domainRecord->id;
            }

            // Batch check video availability
            $contents = \App\Models\Content::where('domain_id', $domainRecord->id)
                ->whereIn('id', $allPossibleHashes)
                ->get(['id', 'video_url']);

            // Create availability map
            $videoAvailability = [];
            foreach ($contentHashes as $hash) {
                $domainSpecificHash = $hash . '_' . $domainRecord->id;
                
                // Check both original hash and domain-specific hash
                $content = $contents->firstWhere('id', $hash) ?: $contents->firstWhere('id', $domainSpecificHash);
                $videoAvailability[$hash] = $content && !empty($content->video_url);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'video_availability' => $videoAvailability,
                    'total_checked' => count($contentHashes)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('HoverVid Plugin Batch Video Check Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'System error occurred while batch checking videos'
            ], 500);
        }
    }
} 
