<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
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
        // Skip logging for assets, images, etc.
        if ($this->shouldSkipLogging($request)) {
            return $next($request);
        }

        // Start time for performance measurement
        $startTime = microtime(true);
        
        // Log incoming request details
        $this->logRequest($request);
        
        // Process the request
        $response = $next($request);
        
        // Calculate execution time
        $executionTime = microtime(true) - $startTime;
        
        // Log response details
        $this->logResponse($request, $response, $executionTime);
        
        return $response;
    }
    
    /**
     * Log request details
     *
     * @param Request $request
     * @return void
     */
    protected function logRequest(Request $request): void
    {
        // Don't log passwords or sensitive data
        $filteredInput = $this->filterSensitiveData($request->all());
        
        Log::debug('API Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'headers' => $this->filterHeaders($request->headers->all()),
            'input' => $filteredInput,
            'raw_content' => $request->getContent(),
            'content_type' => $request->header('Content-Type'),
            'is_ajax' => $request->ajax(),
            'is_json' => $request->isJson(),
            'user_id' => $request->user() ? $request->user()->id : 'unauthenticated',
        ]);
    }
    
    /**
     * Log response details
     *
     * @param Request $request
     * @param Response $response
     * @param float $executionTime
     * @return void
     */
    protected function logResponse(Request $request, Response $response, float $executionTime): void
    {
        $responseData = null;
        
        // Only attempt to decode JSON responses
        if (str_contains($response->headers->get('Content-Type') ?? '', 'application/json')) {
            $content = $response->getContent();
            try {
                $responseData = json_decode($content, true);
            } catch (\Exception $e) {
                $responseData = null;
            }
        }
        
        Log::debug('API Response', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'execution_time' => round($executionTime * 1000, 2) . 'ms', // Convert to milliseconds
            'response_headers' => $this->filterHeaders($response->headers->all()),
            'response_data' => $responseData,
        ]);
    }
    
    /**
     * Filter sensitive data from request input
     *
     * @param array $input
     * @return array
     */
    protected function filterSensitiveData(array $input): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'current_password', 'secret', 'token', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            if (isset($input[$field])) {
                $input[$field] = '[FILTERED]';
            }
        }
        
        return $input;
    }
    
    /**
     * Filter out sensitive headers
     *
     * @param array $headers
     * @return array
     */
    protected function filterHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'set-cookie', 'x-csrf-token', 'x-xsrf-token'];
        
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = '[FILTERED]';
            }
        }
        
        return $headers;
    }
    
    /**
     * Check if request should be skipped from logging
     *
     * @param Request $request
     * @return bool
     */
    protected function shouldSkipLogging(Request $request): bool
    {
        // Skip static assets and healthchecks
        $skipPaths = [
            '_debugbar',
            'js/',
            'css/',
            'fonts/',
            'images/',
            'health',
            'favicon.ico',
            'robots.txt',
        ];
        
        $path = $request->path();
        
        foreach ($skipPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return true;
            }
        }
        
        return false;
    }
} 