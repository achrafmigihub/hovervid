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
        // Get user role - handle both string and enum values
        $userRole = null;
        if ($request->user()) {
            $userRole = is_object($request->user()->role) 
                ? $request->user()->role->value 
                : $request->user()->role;
        }

        // Log role check attempt
        \Illuminate\Support\Facades\Log::info('Role check attempt', [
            'required_role' => $role,
            'user_role' => $userRole,
            'user_id' => $request->user() ? $request->user()->id : null,
            'path' => $request->path(),
            'method' => $request->method()
        ]);

        if (!$request->user() || $userRole !== $role) {
            return response()->json([
                'error' => 'Unauthorized - Required role: ' . $role,
                'user_role' => $userRole
            ], 403);
        }

        return $next($request);
    }
} 
