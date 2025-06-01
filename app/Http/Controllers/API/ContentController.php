<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{
    /**
     * Store fingerprint data from the plugin
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'domain_name' => 'required|string',
                'fingerprint_data' => 'required|array',
                'fingerprint_data.*.text' => 'required|string',
                'fingerprint_data.*.hash' => 'required|string',
                'fingerprint_data.*.context' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $domainName = $request->input('domain_name');
            $fingerprintData = $request->input('fingerprint_data');

            // Find domain record
            $domain = Domain::where('domain', $domainName)->first();

            if (!$domain) {
                return response()->json([
                    'success' => false,
                    'message' => 'Domain not found. Please register the domain first.'
                ], 404);
            }

            $insertedCount = 0;
            $skippedCount = 0;

            // Begin transaction for batch insert
            DB::beginTransaction();

            foreach ($fingerprintData as $item) {
                $textHash = $item['hash'];
                $textContent = $item['text'];
                $context = $item['context'];

                // Check if this content already exists (using hash as ID)
                $existingContent = Content::where('id', $textHash)->first();

                if ($existingContent) {
                    $skippedCount++;
                    continue; // Skip if already exists
                }

                // Create new content record
                Content::create([
                    'id' => $textHash,
                    'domain_id' => $domain->id,
                    'user_id' => $domain->user_id, // Use domain's user_id
                    'content_element' => $textContent,
                    'text' => $textContent, // Store the actual text content
                    'video_url' => null, // Will be populated later when videos are assigned
                    'context' => $context,
                    'url' => $domainName,
                ]);

                $insertedCount++;
            }

            // Commit transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fingerprint data saved successfully',
                'data' => [
                    'domain_id' => $domain->id,
                    'inserted_count' => $insertedCount,
                    'skipped_count' => $skippedCount,
                    'total_processed' => count($fingerprintData)
                ]
            ]);

        } catch (\Exception $e) {
            // Rollback transaction on error
            DB::rollback();

            Log::error('Content storage error: ' . $e->getMessage(), [
                'domain_name' => $request->input('domain_name'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving fingerprint data'
            ], 500);
        }
    }

    /**
     * Get content for a domain
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $domainName = $request->query('domain_name');

            if (!$domainName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name parameter'
                ], 400);
            }

            // Get content for domain
            $content = Content::select('content.id', 'content.domain_id', 'content.content_element', 
                                     'content.text', 'content.video_url', 'content.context', 'content.url', 'domains.domain')
                             ->join('domains', 'content.domain_id', '=', 'domains.id')
                             ->where('domains.domain', $domainName)
                             ->orderBy('content.id')
                             ->get();

            return response()->json([
                'success' => true,
                'content' => $content,
                'total_count' => $content->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Content retrieval error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving content'
            ], 500);
        }
    }

    /**
     * Get content for the authenticated client user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getClientContent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Get content for the logged-in user directly by user_id
            $content = Content::where('user_id', $user->id)
                             ->orderBy('created_at', 'desc')
                             ->get();

            // Get the user's active domain for display purposes
            $domain = Domain::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'content' => $content,
                    'domain' => $domain ? [
                        'id' => $domain->id,
                        'domain' => $domain->domain
                    ] : null
                ],
                'total_count' => $content->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Client content retrieval error: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving content'
            ], 500);
        }
    }

    /**
     * Reject/delete content item for the authenticated client user
     *
     * @param Request $request
     * @param string $contentId
     * @return JsonResponse
     */
    public function rejectContent(Request $request, string $contentId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Find the content item that belongs to the logged-in user
            $content = Content::where('id', $contentId)
                             ->where('user_id', $user->id)
                             ->first();

            if (!$content) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found or you do not have permission to reject this content'
                ], 404);
            }

            // Delete the content
            $content->delete();

            Log::info('Content rejected by client', [
                'user_id' => $user->id,
                'content_id' => $contentId,
                'domain_id' => $content->domain_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Content rejected successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Content rejection error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'content_id' => $contentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while rejecting content'
            ], 500);
        }
    }
}
