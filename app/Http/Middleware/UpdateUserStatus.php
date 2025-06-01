<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserStatus
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $user = User::find($userId);
            
            if ($user) {
                // Priority 1: Suspended users must always show suspended status
                if ($user->is_suspended) {
                    // Make sure suspended flag and status are in sync
                    if ($user->status !== UserStatusEnum::SUSPENDED->value) {
                        User::where('id', $userId)
                            ->update([
                                'status' => UserStatusEnum::SUSPENDED->value
                            ]);
                        
                        Log::info('Corrected user status to suspended', ['user_id' => $userId]);
                    }
                    
                    // If a suspended user is trying to use the application, log them out
                    if (!$request->ajax() && !$request->wantsJson() && !str_contains($request->path(), 'login')) {
                        Auth::logout();
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                        
                        Log::info('Forced logout of suspended user', ['user_id' => $userId]);
                        
                        return redirect()->route('login')->with('suspended', true);
                    }
                } 
                // Priority 2: For unsuspended users who are logged in, set status to active
                else if (!$user->is_suspended && $user->status !== UserStatusEnum::ACTIVE->value) {
                    User::where('id', $userId)
                        ->update(['status' => UserStatusEnum::ACTIVE->value]);
                    
                    Log::info('Activating user status upon login/action', ['user_id' => $userId]);
                }
            }
        }

        return $next($request);
    }
} 
