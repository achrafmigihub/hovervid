<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JsonApiResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Continue to the next middleware and get the response
        $response = $next($request);
        
        // Only proceed if this is an API request
        if (strpos($request->getPathInfo(), '/api') === 0) {
            
            // Check if the response is HTML
            if ($response instanceof Response) {
                $contentType = $response->headers->get('Content-Type');
                
                // If HTML response or no content type
                if (!$contentType || strpos($contentType, 'text/html') !== false) {
                    
                    $content = $response->getContent();
                    $statusCode = $response->getStatusCode();
                    
                    // If HTML content
                    if (is_string($content) && strpos($content, '<!DOCTYPE html>') !== false) {
                        
                        // Extract title if possible (often contains error message)
                        $message = 'API error occurred';
                        if (preg_match('/<title>(.*?)<\/title>/i', $content, $matches)) {
                            $message = $matches[1];
                        }
                        
                        // Create JSON response
                        $jsonResponse = response()->json([
                            'status' => 'error',
                            'message' => $message,
                            'code' => $statusCode,
                            'debug' => [
                                'url' => $request->fullUrl(),
                                'method' => $request->method(),
                                'path' => $request->path(),
                            ]
                        ], $statusCode);
                        
                        return $jsonResponse;
                    }
                }
            }
        }
        
        return $response;
    }
} 