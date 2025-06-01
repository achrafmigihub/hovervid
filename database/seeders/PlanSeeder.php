<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Plan;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Basic',
                'price' => 29.99,
                'duration' => '1 month',
                'features' => [
                    '10 Videos',
                    'Basic Support',
                    '1 Website',
                    'Analytics Dashboard'
                ]
            ],
            [
                'name' => 'Pro',
                'price' => 59.99,
                'duration' => '1 month',
                'features' => [
                    'Unlimited Videos',
                    'Priority Support',
                    '3 Websites',
                    'Advanced Analytics',
                    'API Access'
                ]
            ],
            [
                'name' => 'Enterprise',
                'price' => 99.99,
                'duration' => '1 month',
                'features' => [
                    'Unlimited Videos',
                    'Dedicated Support',
                    'Unlimited Websites',
                    'Custom Analytics',
                    'API Access',
                    'Custom Branding',
                    'Team Management'
                ]
            ],
        ];

        foreach ($plans as $planData) {
            // Only create if plan doesn't exist
            if (!Plan::where('name', $planData['name'])->exists()) {
                Plan::create($planData);
                $this->command->info("Created plan: {$planData['name']}");
            } else {
                $this->command->info("Plan already exists: {$planData['name']}");
            }
        }
    }
} 
