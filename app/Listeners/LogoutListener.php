<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\Session;
use Illuminate\Support\Facades\Log;

class LogoutListener
{
    /**
     * Handle the event.
     */
    public function handle(Logout $event): void
    {
        try {
            if ($event->user && request()->session()) {
                $sessionId = request()->session()->getId();
                
                // Deactivate the current session
                Session::where('id', $sessionId)
                    ->where('user_id', $event->user->id)
                    ->update([
                        'is_active' => false,
                        'expires_at' => now()
                    ]);
                
                Log::info('Session deactivated on logout', [
                    'user_id' => $event->user->id,
                    'session_id' => $sessionId
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error deactivating session on logout', [
                'error' => $e->getMessage()
            ]);
        }
    }
} 
