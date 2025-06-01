<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all client users
        $clientUsers = User::where('role', 'client')->get();
        
        // Get all plans
        $plans = Plan::all();
        
        if ($plans->isEmpty()) {
            $this->command->error('No plans found. Please run the PlanSeeder first.');
            return;
        }
        
        foreach ($clientUsers as $user) {
            // Skip if user already has a subscription
            if ($user->subscriptions()->exists()) {
                $this->command->info("User {$user->name} already has a subscription.");
                continue;
            }
            
            // Randomly select a plan
            $plan = $plans->random();
            
            // Create a subscription with one month duration
            $startDate = Carbon::now()->subDays(rand(1, 15));
            $expiryDate = (clone $startDate)->addMonth();
            
            Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => $startDate,
                'expires_at' => $expiryDate,
            ]);
            
            $this->command->info("Created {$plan->name} subscription for user {$user->name}.");
        }
    }
} 
