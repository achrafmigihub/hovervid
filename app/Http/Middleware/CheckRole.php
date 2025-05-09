<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        // Log role check attempt
        \Illuminate\Support\Facades\Log::info('Role check attempt', [
            'required_role' => $role,
            'user_role' => $request->user() ? $request->user()->role : null,
            'user_id' => $request->user() ? $request->user()->id : null,
            'path' => $request->path(),
            'method' => $request->method()
        ]);

        if (!$request->user() || $request->user()->role !== $role) {
            return response()->json([
                'error' => 'Unauthorized - Required role: ' . $role,
                'user_role' => $request->user() ? $request->user()->role : null
            ], 403);
        }

        return $next($request);
    }
} 