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
use Illuminate\Support\Facades\Storage;

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
                'fingerprint_data.*.page_name' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                Log::error('Content storage validation failed', [
                    'domain_name' => $request->input('domain_name'),
                    'errors' => $validator->errors()->toArray(),
                    'sample_data' => $request->input('fingerprint_data')[0] ?? 'no data'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $domainName = $request->input('domain_name');
            $fingerprintData = $request->input('fingerprint_data');

            // Debug: Log incoming data
            Log::info('Fingerprint data received', [
                'domain_name' => $domainName,
                'data_count' => count($fingerprintData),
                'sample_data' => count($fingerprintData) > 0 ? [
                    'text' => $fingerprintData[0]['text'] ?? 'missing',
                    'hash' => $fingerprintData[0]['hash'] ?? 'missing', 
                    'context' => $fingerprintData[0]['context'] ?? 'missing',
                    'page_name' => $fingerprintData[0]['page_name'] ?? 'MISSING PAGE NAME'
                ] : 'no data'
            ]);

            // Find domain record
            $domain = Domain::where('domain', $domainName)->first();

            if (!$domain) {
                Log::error('Domain not found for content storage', [
                    'domain_name' => $domainName,
                    'available_domains' => Domain::pluck('domain')->toArray()
                ]);
                
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
                $textContent = trim($item['text']);
                $context = $item['context'];
                $pageName = trim($item['page_name'] ?? 'Unknown Page');

                // Normalize text content to prevent duplicates from whitespace differences
                $normalizedText = trim(preg_replace('/\s+/', ' ', strtolower($textContent)));
                
                // Skip empty or very short content
                if (empty($normalizedText) || strlen($normalizedText) < 3) {
                    $skippedCount++;
                    continue;
                }

                // FIRST: Check for domain-specific duplicates by normalized text
                // This catches cases where the same text might have different hashes
                $similarContent = Content::where('domain_id', $domain->id)
                    ->whereRaw('LOWER(TRIM(REGEXP_REPLACE(text, \'\s+\', \' \', \'g\'))) = ?', [$normalizedText])
                    ->first();
                    
                if ($similarContent) {
                    $skippedCount++;
                    Log::info('Skipped duplicate content for this domain', [
                        'domain_id' => $domain->id,
                        'domain_name' => $domainName,
                        'original_hash' => $textHash,
                        'existing_hash' => $similarContent->id,
                        'text' => $normalizedText
                    ]);
                    continue;
                }

                // SECOND: Check if this exact hash already exists for this domain
                $existingContent = Content::where('id', $textHash)
                    ->where('domain_id', $domain->id)
                    ->first();

                if ($existingContent) {
                    $skippedCount++;
                    Log::info('Skipped duplicate hash for this domain', [
                        'domain_id' => $domain->id,
                        'domain_name' => $domainName,
                        'hash' => $textHash
                    ]);
                    continue; // Skip if already exists for this domain
                }
                
                // THIRD: If hash exists globally but not for this domain, generate a domain-specific hash
                $globalHashExists = Content::where('id', $textHash)->first();
                $finalHash = $textHash;
                
                if ($globalHashExists) {
                    // Generate domain-specific hash to avoid collision
                    $finalHash = $textHash . '_' . $domain->id;
                    
                    // Check if this domain-specific hash exists
                    $domainSpecificExists = Content::where('id', $finalHash)->first();
                    if ($domainSpecificExists) {
                        $skippedCount++;
                        Log::info('Skipped duplicate domain-specific hash', [
                            'domain_id' => $domain->id,
                            'domain_name' => $domainName,
                            'original_hash' => $textHash,
                            'domain_specific_hash' => $finalHash
                        ]);
                        continue;
                    }
                    
                    Log::info('Created domain-specific hash to avoid global collision', [
                        'domain_id' => $domain->id,
                        'domain_name' => $domainName,
                        'original_hash' => $textHash,
                        'domain_specific_hash' => $finalHash
                    ]);
                }

                try {
                    // Create new content record
                    Content::create([
                        'id' => $finalHash,
                        'domain_id' => $domain->id,
                        'user_id' => $domain->user_id, // Use domain's user_id
                        'content_element' => $textContent,
                        'text' => $textContent, // Store the actual text content
                        'video_url' => null, // Will be populated later when videos are assigned
                        'context' => $context,
                        'url' => $domainName,
                        'page_name' => $pageName,
                    ]);

                    $insertedCount++;
                    
                    Log::info('Successfully stored content', [
                        'domain_id' => $domain->id,
                        'domain_name' => $domainName,
                        'hash' => $finalHash,
                        'page_name' => $pageName,
                        'text_preview' => substr($normalizedText, 0, 50) . '...'
                    ]);
                    
                } catch (\Exception $e) {
                    // Handle duplicate key violation or other database errors
                    if (str_contains($e->getMessage(), 'duplicate key') || str_contains($e->getMessage(), 'Duplicate entry')) {
                        $skippedCount++;
                        Log::info('Database duplicate key detected', [
                            'domain_id' => $domain->id,
                            'domain_name' => $domainName,
                            'hash' => $finalHash,
                            'text' => $normalizedText
                        ]);
                    } else {
                        throw $e; // Re-throw if it's not a duplicate error
                    }
                }
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
                                     'content.text', 'content.video_url', 'content.context', 'content.url', 'content.page_name', 'domains.domain')
                             ->join('domains', 'content.domain_id', '=', 'domains.id')
                             ->where('domains.domain', $domainName)
                             ->orderBy('content.page_name', 'asc')
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

            // Get content for the logged-in user grouped by page names
            $content = Content::where('user_id', $user->id)
                             ->orderBy('page_name', 'asc')
                             ->orderBy('created_at', 'desc')
                             ->get();

            // Group content by page names
            $groupedContent = $content->groupBy('page_name')->map(function ($pageContent, $pageName) {
                return [
                    'page_name' => $pageName ?: 'Unknown Page',
                    'content_count' => $pageContent->count(),
                    'items' => $pageContent->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'text' => $item->text,
                            'content_element' => $item->content_element,
                            'context' => $item->context,
                            'video_url' => $item->video_url,
                            'has_video' => !empty($item->video_url),
                            'created_at' => $item->created_at,
                            'url' => $item->url
                        ];
                    })
                ];
            })->values(); // Reset array keys

            // Get the user's active domain for display purposes
            $domain = Domain::where('user_id', $user->id)
                           ->where('is_active', true)
                           ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'pages' => $groupedContent,
                    'total_pages' => $groupedContent->count(),
                    'total_content' => $content->count(),
                    'domain' => $domain ? [
                        'id' => $domain->id,
                        'domain' => $domain->domain
                    ] : null
                ]
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

    /**
     * Upload video for content item
     *
     * @param Request $request
     * @param string $contentId
     * @return JsonResponse
     */
    public function uploadVideo(Request $request, string $contentId): JsonResponse
    {
        try {
            Log::info('=== VIDEO UPLOAD DEBUG START ===', [
                'content_id' => $contentId,
                'user_id' => Auth::id(),
                'request_data' => [
                    'has_file' => $request->hasFile('video'),
                    'file_count' => $request->allFiles() ? count($request->allFiles()) : 0,
                    'request_size' => $request->header('Content-Length'),
                ]
            ]);
            
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('Upload attempt without authentication', ['content_id' => $contentId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            Log::info('User authenticated for upload', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'content_id' => $contentId
            ]);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'video' => 'required|file|mimes:mp4,avi,mov,wmv,flv,webm|max:10240' // 10MB max
            ]);

            if ($validator->fails()) {
                Log::error('Video upload validation failed', [
                    'user_id' => $user->id,
                    'content_id' => $contentId,
                    'errors' => $validator->errors()->toArray(),
                    'has_file' => $request->hasFile('video'),
                    'files' => $request->allFiles()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            Log::info('Validation passed', ['user_id' => $user->id, 'content_id' => $contentId]);

            // Find the content item that belongs to the logged-in user
            $content = Content::where('id', $contentId)
                             ->where('user_id', $user->id)
                             ->first();

            if (!$content) {
                Log::error('Content not found or permission denied', [
                    'user_id' => $user->id,
                    'content_id' => $contentId,
                    'content_exists' => Content::where('id', $contentId)->exists(),
                    'user_content_count' => Content::where('user_id', $user->id)->count()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found or you do not have permission to upload video for this content'
                ], 404);
            }

            Log::info('Content found', [
                'user_id' => $user->id,
                'content_id' => $contentId,
                'current_video_url' => $content->video_url
            ]);

            // Generate unique filename to prevent conflicts
            $videoFile = $request->file('video');
            $extension = $videoFile->getClientOriginalExtension();
            $uniqueFileName = 'video_' . uniqid() . '_' . time() . '_' . $contentId . '.' . $extension;
            
            Log::info('File details', [
                'original_name' => $videoFile->getClientOriginalName(),
                'size' => $videoFile->getSize(),
                'mime_type' => $videoFile->getMimeType(),
                'extension' => $extension,
                'unique_filename' => $uniqueFileName
            ]);
            
            // Upload to Wasabi in user-specific folder
            $path = 'videos/' . $user->id . '/' . $uniqueFileName;
            
            Log::info('Starting Wasabi upload', [
                'path' => $path,
                'disk' => 'wasabi',
                'wasabi_config' => [
                    'endpoint' => config('filesystems.disks.wasabi.endpoint'),
                    'bucket' => config('filesystems.disks.wasabi.bucket'),
                    'region' => config('filesystems.disks.wasabi.region'),
                    'key_set' => !empty(config('filesystems.disks.wasabi.key')),
                    'secret_set' => !empty(config('filesystems.disks.wasabi.secret'))
                ]
            ]);
            
            // Upload the file to Wasabi
            $uploaded = Storage::disk('wasabi')->put($path, file_get_contents($videoFile), 'public');
            
            Log::info('Wasabi upload result', [
                'uploaded' => $uploaded,
                'path' => $path
            ]);
            
            if (!$uploaded) {
                Log::error('Wasabi upload failed', [
                    'path' => $path,
                    'user_id' => $user->id,
                    'content_id' => $contentId
                ]);
                throw new \Exception('Failed to upload video to Wasabi storage');
            }

            // Generate the public URL for the video
            $wasabiEndpoint = env('WASABI_ENDPOINT', 'https://s3.wasabisys.com');
            $wasabiBucket = env('WASABI_BUCKET');
            $videoUrl = $wasabiEndpoint . '/' . $wasabiBucket . '/' . $path;
            
            Log::info('Generated video URL', [
                'video_url' => $videoUrl,
                'endpoint' => $wasabiEndpoint,
                'bucket' => $wasabiBucket,
                'path' => $path
            ]);
            
            // Update content with video URL
            $content->video_url = $videoUrl;
            $content->save();

            Log::info('Video uploaded to Wasabi for content', [
                'user_id' => $user->id,
                'content_id' => $contentId,
                'wasabi_path' => $path,
                'video_url' => $videoUrl,
                'content_updated' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video uploaded successfully to Wasabi storage',
                'data' => [
                    'content_id' => $contentId,
                    'video_url' => $videoUrl,
                    'file_path' => $path
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Video upload to Wasabi error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'content_id' => $contentId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading video to cloud storage',
                'debug' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get video URL for a specific content item (used by plugin)
     *
     * @param Request $request
     * @param string $contentId
     * @return JsonResponse
     */
    public function getContentVideo(Request $request, string $contentId): JsonResponse
    {
        try {
            $domainName = $request->query('domain_name');

            if (!$domainName) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing domain_name parameter'
                ], 400);
            }

            // Find the content item for the specified domain
            $content = Content::select('content.id', 'content.video_url', 'content.text', 'content.context')
                             ->join('domains', 'content.domain_id', '=', 'domains.id')
                             ->where('content.id', $contentId)
                             ->where('domains.domain', $domainName)
                             ->first();

            if (!$content) {
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found for this domain'
                ], 404);
            }

            // Check if content has a video
            if (empty($content->video_url)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No video available for this content',
                    'has_video' => false
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'content_id' => $content->id,
                    'video_url' => $content->video_url,
                    'text' => $content->text,
                    'context' => $content->context,
                    'has_video' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Content video retrieval error: ' . $e->getMessage(), [
                'content_id' => $contentId,
                'domain_name' => $request->query('domain_name')
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving video'
            ], 500);
        }
    }

    /**
     * Update content with video URL (for direct uploads)
     *
     * @param Request $request
     * @param string $contentId
     * @return JsonResponse
     */
    public function updateVideoUrl(Request $request, string $contentId): JsonResponse
    {
        try {
            Log::info('=== UPDATE VIDEO URL DEBUG START ===', [
                'content_id' => $contentId,
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);
            
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('Update video URL attempt without authentication', ['content_id' => $contentId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            // Validate the request
            $validator = Validator::make($request->all(), [
                'video_url' => 'required|url',
                'file_path' => 'required|string'
            ]);

            if ($validator->fails()) {
                Log::error('Video URL update validation failed', [
                    'user_id' => $user->id,
                    'content_id' => $contentId,
                    'errors' => $validator->errors()->toArray()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            // Find the content item that belongs to the logged-in user
            $content = Content::where('id', $contentId)
                             ->where('user_id', $user->id)
                             ->first();

            if (!$content) {
                Log::error('Content not found for video URL update', [
                    'user_id' => $user->id,
                    'content_id' => $contentId
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Content not found or you do not have permission to update this content'
                ], 404);
            }

            $videoUrl = $request->input('video_url');
            $filePath = $request->input('file_path');
            
            // Update content with video URL
            $content->video_url = $videoUrl;
            $content->save();

            Log::info('Video URL updated for content', [
                'user_id' => $user->id,
                'content_id' => $contentId,
                'video_url' => $videoUrl,
                'file_path' => $filePath
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video URL updated successfully',
                'data' => [
                    'content_id' => $contentId,
                    'video_url' => $videoUrl,
                    'file_path' => $filePath
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Video URL update error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'content_id' => $contentId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating video URL'
            ], 500);
        }
    }
}
