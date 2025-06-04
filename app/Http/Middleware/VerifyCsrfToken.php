<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',  // Exclude all API routes
        'sanctum/csrf-cookie',  // Exclude Sanctum CSRF cookie route
        'api/auth/login',  // Exclude login endpoint
        'api/auth/register',  // Exclude register endpoint
        'api/auth/logout',  // Exclude logout endpoint
        'api/auth/verify-email',  // Exclude email verification
        'api/auth/resend-verification',  // Exclude resend verification
    ];
}
