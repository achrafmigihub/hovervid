<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\JsonResponse;

class FormatJsonResponse
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
        $response = $next($request);
        
        // Only process JSON responses
        if ($response instanceof JsonResponse) {
            $statusCode = $response->getStatusCode();
            $data = $response->getData(true);
            
            // Don't modify validation error responses (422), they have a specific format
            if ($statusCode === 422) {
                return $response;
            }
            
            // Don't modify 204 No Content responses
            if ($statusCode === 204) {
                return $response;
            }
            
            // Don't format responses that are already properly formatted
            if (isset($data['users']) && isset($data['totalUsers']) && isset($data['page'])) {
                return $response;
            }
            
            // If this is a paginated response without the expected format
            if (isset($data['data']) && isset($data['meta']) && isset($data['links'])) {
                $formattedData = [
                    'users' => $data['data'],
                    'totalUsers' => $data['meta']['total'],
                    'page' => $data['meta']['current_page'],
                    'totalPages' => $data['meta']['last_page'],
                ];
                
                return new JsonResponse($formattedData, $statusCode);
            }
            
            // For error responses (4xx, 5xx), ensure they have a 'message' field
            if ($statusCode >= 400 && !isset($data['message'])) {
                $formattedData = [
                    'message' => 'An error occurred',
                ];
                
                if (isset($data['error'])) {
                    $formattedData['error'] = $data['error'];
                }
                
                return new JsonResponse($formattedData, $statusCode);
            }
        }
        
        return $response;
    }
} 