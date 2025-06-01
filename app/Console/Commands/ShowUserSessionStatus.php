<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Session;

class ShowUserSessionStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:session-status {email? : The email of the user to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show user status and session information';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $usersQuery = User::query();
        
        if ($email) {
            $usersQuery->where('email', 'like', "%{$email}%");
        }
        
        $users = $usersQuery->get();
        
        if ($users->isEmpty()) {
            $this->error('No users found with the provided criteria.');
            return 1;
        }
        
        $headers = ['ID', 'Name', 'Email', 'Status', 'Active Sessions'];
        $rows = [];
        
        foreach ($users as $user) {
            $activeSessions = Session::where('user_id', $user->id)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->count();
                
            $rows[] = [
                $user->id,
                $user->name,
                $user->email,
                $user->status->value ?? $user->status, // Handle both enum and string
                $activeSessions,
            ];
        }
        
        $this->table($headers, $rows);
        
        // Detailed sessions if only one user
        if ($users->count() === 1) {
            $user = $users->first();
            $this->info("\nDetailed sessions for {$user->name}:");
            
            $sessions = Session::where('user_id', $user->id)->get();
            
            if ($sessions->isEmpty()) {
                $this->line('No sessions found.');
                return 0;
            }
            
            $sessionHeaders = ['ID', 'IP Address', 'Last Activity', 'Expires At', 'Active'];
            $sessionRows = [];
            
            foreach ($sessions as $session) {
                $sessionRows[] = [
                    $session->id,
                    $session->ip_address,
                    $session->last_activity ? (new \DateTime())->setTimestamp($session->last_activity)->format('Y-m-d H:i:s') : 'N/A',
                    $session->expires_at ? $session->expires_at->format('Y-m-d H:i:s') : 'N/A',
                    $session->is_active ? 'Yes' : 'No',
                ];
            }
            
            $this->table($sessionHeaders, $sessionRows);
        }
        
        return 0;
    }
} 
