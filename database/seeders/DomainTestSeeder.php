<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Domain;
use App\Models\User;
use Illuminate\Support\Str;

class DomainTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user first
        $testUser = User::firstOrCreate(
            ['email' => 'test@hovervid.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'role' => 'client',
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Create test domains for plugin validation
        $testDomains = [
            [
                'domain' => 'example.com',
                'platform' => 'wordpress',
                'plugin_status' => 'inactive',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'api_key' => Str::uuid(),
                'verification_token' => Str::random(32),
            ],
            [
                'domain' => 'test-site.com',
                'platform' => 'wordpress',
                'plugin_status' => 'inactive',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'api_key' => Str::uuid(),
                'verification_token' => Str::random(32),
            ],
            [
                'domain' => 'demo-website.org',
                'platform' => 'wordpress',
                'plugin_status' => 'active',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => true,
                'api_key' => Str::uuid(),
                'verification_token' => Str::random(32),
            ],
            [
                'domain' => 'inactive-domain.com',
                'platform' => 'wordpress',
                'plugin_status' => 'inactive',
                'status' => 'inactive',
                'is_active' => false,
                'is_verified' => false,
                'api_key' => Str::uuid(),
                'verification_token' => Str::random(32),
            ],
            [
                'domain' => 'localhost',
                'platform' => 'wordpress',
                'plugin_status' => 'inactive',
                'status' => 'active',
                'is_active' => true,
                'is_verified' => false,
                'api_key' => Str::uuid(),
                'verification_token' => Str::random(32),
            ],
        ];

        foreach ($testDomains as $domainData) {
            Domain::updateOrCreate(
                ['domain' => $domainData['domain']],
                array_merge($domainData, [
                    'user_id' => $testUser->id,
                    'last_checked_at' => now(),
                ])
            );
        }

        $this->command->info('Test domains created successfully!');
        $this->command->info('Created domains:');
        foreach ($testDomains as $domain) {
            $status = $domain['is_active'] ? 'ACTIVE' : 'INACTIVE';
            $plugin = $domain['plugin_status'];
            $this->command->info("  - {$domain['domain']} (Status: {$status}, Plugin: {$plugin})");
        }
    }
} 
