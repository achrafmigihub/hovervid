<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Custom reporting logic if needed
        });

        // Override the handling of validation exceptions
        $this->renderable(function (ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                    'status' => 'error',
                    'status_code' => 422
                ], 422);
            }
        });

        // Handle authentication exceptions
        $this->renderable(function (AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'status' => 'error',
                    'status_code' => 401
                ], 401);
            }
        });

        // Handle model not found exceptions
        $this->renderable(function (ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Resource not found',
                    'status' => 'error',
                    'status_code' => 404
                ], 404);
            }
        });

        // Handle general HTTP exceptions
        $this->renderable(function (HttpException $e, $request) {
            if ($request->expectsJson()) {
                $statusCode = $e->getStatusCode();
                return response()->json([
                    'message' => $e->getMessage() ?: $this->getHttpStatusMessage($statusCode),
                    'status' => 'error',
                    'status_code' => $statusCode
                ], $statusCode);
            }
        });
        
        // Handle all other exceptions in JSON format for API requests
        $this->renderable(function (Throwable $e, $request) {
            if ($request->expectsJson()) {
                $statusCode = 500;
                $message = app()->environment('production') 
                    ? 'An unexpected error occurred.' 
                    : $e->getMessage();
                
                // Log the exception with details
                Log::error('API Error', [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'request_url' => $request->fullUrl(),
                    'request_method' => $request->method(),
                    'request_data' => $request->all()
                ]);
                
                return response()->json([
                    'message' => $message,
                    'status' => 'error',
                    'status_code' => $statusCode,
                    'debug' => app()->environment('production') ? null : [
                        'exception' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => collect($e->getTrace())->take(5)->toArray()
                    ]
                ], $statusCode);
            }
        });
    }
    
    /**
     * Get HTTP status code message.
     *
     * @param int $statusCode
     * @return string
     */
    protected function getHttpStatusMessage(int $statusCode): string
    {
        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            419 => 'Page Expired',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];
        
        return $messages[$statusCode] ?? 'Error';
    }
} 