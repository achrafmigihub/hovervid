<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleOptions
{
    /**
     * Handle an incoming request and quickly respond to OPTIONS requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If this is an OPTIONS request, return a response immediately
        if ($request->isMethod('OPTIONS')) {
            // Get CORS config
            $cors = config('cors');
            
            // Create empty response with 200 status code
            $response = new Response('', 200);
            
            // Set CORS headers
            $response->headers->set('Access-Control-Allow-Methods', implode(',', $cors['allowed_methods']));
            $response->headers->set('Access-Control-Allow-Headers', implode(',', $cors['allowed_headers']));
            $response->headers->set('Access-Control-Allow-Origin', $request->header('Origin'));
            $response->headers->set('Access-Control-Max-Age', $cors['max_age']);
            
            if ($cors['supports_credentials']) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }
            
            return $response;
        }
        
        return $next($request);
    }
} 