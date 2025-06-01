<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Session;
use App\Enums\UserStatusEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class SyncUserStatusWithSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-status {--force : Force update all user statuses} {--detailed : Show detailed information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize user status based on active sessions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting user status synchronization based on active sessions...');
        $forceUpdate = $this->option('force');
        $isDetailed = $this->option('detailed');

        // Step 0: Ensure suspended users have their status always set to suspended
        $suspendedFixed = User::where('is_suspended', true)
            ->where('status', '!=', UserStatusEnum::SUSPENDED->value)
            ->update(['status' => UserStatusEnum::SUSPENDED->value]);
        
        if ($suspendedFixed > 0 || $isDetailed) {
            $this->info("Fixed {$suspendedFixed} users with suspended flag but incorrect status.");
        }
        
        // Ensure all users with suspended status have is_suspended=true
        $suspendedFlagFixed = User::where('status', UserStatusEnum::SUSPENDED->value)
            ->where('is_suspended', false)
            ->update(['is_suspended' => true]);
            
        if ($suspendedFlagFixed > 0 || $isDetailed) {
            $this->info("Fixed {$suspendedFlagFixed} users with suspended status but incorrect suspended flag.");
        }

        // Step 1: Clean up expired sessions by setting is_active to false
        $now = Carbon::now();
        
        // First, let's just mark the sessions as inactive without touching expires_at
        $expiredCount = DB::table('sessions')
            ->where(function($query) use ($now) {
                $query->where('expires_at', '<', $now)
                      ->orWhere(function($q) use ($now) {
                          $q->whereNull('expires_at')
                            ->where('last_activity', '<', $now->copy()->subDays(2)->timestamp);
                      });
            })
            ->update(['is_active' => false]);

        $this->info("Marked {$expiredCount} expired sessions as inactive.");

        // Step 2: Get users with truly active sessions
        $activeUserIds = Session::query()
            ->select('user_id')
            ->whereNotNull('user_id')
            ->where('is_active', true)
            ->where(function($query) use ($now) {
                $query->where('expires_at', '>', $now)
                    ->orWhere(function($q) use ($now) {
                        $q->whereNull('expires_at')
                          ->where('last_activity', '>', $now->copy()->subHours(2)->timestamp);
                    });
            })
            ->distinct()
            ->pluck('user_id')
            ->toArray();

        if ($isDetailed) {
            $this->line("Found " . count($activeUserIds) . " users with active sessions");
            if (count($activeUserIds) > 0) {
                $this->line("User IDs with active sessions: " . implode(', ', $activeUserIds));
            }
            
            // Show the count of new users (with inactive status who have never logged in)
            $newUserCount = User::whereNotIn('id', $activeUserIds)
                ->where('status', '=', UserStatusEnum::INACTIVE->value)
                ->count();
            $this->line("Found {$newUserCount} new users who have not logged in yet (inactive status)");
            
            // Get count of suspended users
            $suspendedCount = User::where('is_suspended', true)->count();
            $this->line("Found {$suspendedCount} suspended users");
        }

        // Step 3: Update active users (except suspended users)
        $query = User::whereIn('id', $activeUserIds)
            ->where('is_suspended', false) // Never change suspended users' status
            ->where('status', '!=', UserStatusEnum::SUSPENDED->value);
            
        if (!$forceUpdate) {
            $query->where('status', '!=', UserStatusEnum::ACTIVE->value);
        }
        $activeUpdated = $query->update(['status' => UserStatusEnum::ACTIVE->value]);

        $this->info("Updated {$activeUpdated} users to active status.");

        // Step 4: Update inactive users (users with no active sessions), except suspended users
        $query = User::whereNotIn('id', $activeUserIds)
            ->where('is_suspended', false) // Never change suspended users' status
            ->where('status', '!=', UserStatusEnum::SUSPENDED->value); // Never change suspended status
            
        if (!$forceUpdate) {
            $query->where('status', '=', UserStatusEnum::ACTIVE->value);
        }
        
        // Don't change status for pending users
        $query->where('status', '!=', UserStatusEnum::PENDING->value);
        
        $inactiveUpdated = $query->update(['status' => UserStatusEnum::INACTIVE->value]);

        $this->info("Updated {$inactiveUpdated} users to inactive status.");

        // Step 5: Detailed report on mismatch between status and sessions
        if ($isDetailed) {
            $this->line("\nUsers with active status but no active sessions:");
            $problematicUsers = User::where('status', UserStatusEnum::ACTIVE->value)
                ->whereNotIn('id', $activeUserIds)
                ->get(['id', 'name', 'email', 'status', 'is_suspended']);
            
            if ($problematicUsers->count() > 0) {
                $rows = [];
                foreach ($problematicUsers as $user) {
                    $rows[] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status,
                        'is_suspended' => $user->is_suspended ? 'Yes' : 'No'
                    ];
                }
                $this->table(['ID', 'Name', 'Email', 'Status', 'Suspended'], $rows);
            } else {
                $this->line("None found.");
            }
            
            $this->line("\nSuspended users:");
            $suspendedUsers = User::where('is_suspended', true)
                ->orWhere('status', UserStatusEnum::SUSPENDED->value)
                ->get(['id', 'name', 'email', 'status', 'is_suspended']);
            
            if ($suspendedUsers->count() > 0) {
                $rows = [];
                foreach ($suspendedUsers as $user) {
                    $rows[] = [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'status' => $user->status,
                        'is_suspended' => $user->is_suspended ? 'Yes' : 'No'
                    ];
                }
                $this->table(['ID', 'Name', 'Email', 'Status', 'Suspended'], $rows);
            } else {
                $this->line("None found.");
            }
        }

        $this->info('User status synchronization completed.');

        return 0;
    }
} 
