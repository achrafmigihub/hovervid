<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VideoProxyController extends Controller
{
    /**
     * Stream video from Wasabi through Laravel to avoid CORS issues
     *
     * @param string $encodedUrl Base64 encoded video URL
     * @return Response
     */
    public function streamVideo($encodedUrl)
    {
        try {
            // Decode the video URL
            $videoUrl = base64_decode(urldecode($encodedUrl));
            
            if (empty($videoUrl) || !filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                return response('Invalid video URL', 400);
            }
            
            // Check if it's a Wasabi URL (security check)
            if (!str_contains($videoUrl, 'wasabisys.com')) {
                return response('Unauthorized video source', 403);
            }
            
            Log::info('Proxying video request', ['url' => $videoUrl]);
            
            // Stream the video content
            $response = Http::timeout(30)->get($videoUrl);
            
            if ($response->failed()) {
                Log::error('Failed to fetch video', [
                    'url' => $videoUrl,
                    'status' => $response->status()
                ]);
                return response('Video not available', 404);
            }
            
            // Get the content type from the original response
            $contentType = $response->header('Content-Type') ?? 'video/mp4';
            
            // Return video with proper headers
            return response($response->body())
                ->header('Content-Type', $contentType)
                ->header('Accept-Ranges', 'bytes')
                ->header('Cache-Control', 'public, max-age=3600')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET')
                ->header('Access-Control-Allow-Headers', 'Range');
                
        } catch (\Exception $e) {
            Log::error('Video proxy error', [
                'encoded_url' => $encodedUrl,
                'error' => $e->getMessage()
            ]);
            
            return response('Internal server error', 500);
        }
    }
} 