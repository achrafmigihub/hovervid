<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Domain;
use App\Models\User;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing domains
        Domain::truncate();

        // Get some test users for assignment
        $clientUser = User::where('email', 'client@hovervid.com')->first();
        $testClient = User::where('email', 'client@example.com')->first();

        // Create test domains
        $domains = [
            [
                'domain' => 'example.com',
                'user_id' => $clientUser?->id,
                'platform' => 'wordpress',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'plugin_status' => 'active'
            ],
            [
                'domain' => 'testsite.com',
                'user_id' => $testClient?->id,
                'platform' => 'shopify',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => false,
                'plugin_status' => 'inactive'
            ],
            [
                'domain' => 'unassigned-domain.com',
                'user_id' => null,
                'platform' => 'wordpress',
                'status' => 'inactive',
                'is_active' => false,
                'is_verified' => false,
                'plugin_status' => 'not_installed'
            ],
            [
                'domain' => 'demo-site.com',
                'user_id' => $clientUser?->id,
                'platform' => 'wix',
                'status' => 'pending',
                'is_active' => false,
                'is_verified' => false,
                'plugin_status' => 'not_installed'
            ]
        ];

        foreach ($domains as $domainData) {
            Domain::create($domainData);
        }

        // Update users to link them to their primary domains
        if ($clientUser) {
            $primaryDomain = Domain::where('user_id', $clientUser->id)->where('status', 'active')->first();
            if ($primaryDomain) {
                $clientUser->domain_id = $primaryDomain->id;
                $clientUser->save();
            }
        }

        if ($testClient) {
            $primaryDomain = Domain::where('user_id', $testClient->id)->where('status', 'active')->first();
            if ($primaryDomain) {
                $testClient->domain_id = $primaryDomain->id;
                $testClient->save();
            }
        }

        $this->command->info('Domain seeder completed! Created ' . count($domains) . ' test domains.');
    }
} 
