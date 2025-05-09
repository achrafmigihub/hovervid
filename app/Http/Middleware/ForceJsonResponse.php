<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Force Accept header to be application/json
        $request->headers->set('Accept', 'application/json');
        
        // Get the response
        $response = $next($request);
        
        // If it's not already a JSON response and we're in the API route group
        if (!$response->headers->has('Content-Type') || 
            !str_contains($response->headers->get('Content-Type'), 'application/json')) {
            
            // Check if the response is an error page (HTML)
            $content = $response->getContent();
            if ($content && str_starts_with(trim($content), '<!DOCTYPE html>')) {
                // Extract error message if possible
                $message = 'API error occurred';
                
                // Get status code
                $statusCode = $response->getStatusCode();
                
                // Create a JSON error response
                return response()->json([
                    'message' => $message,
                    'status' => 'error',
                    'code' => $statusCode,
                    'trace' => app()->environment('production') ? null : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
                ], $statusCode);
            }
        }
        
        return $response;
    }
} 