<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Enums\UserRoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@hovervid.com',
            'password' => Hash::make('Admin12345@'),
            'role' => UserRoleEnum::ADMIN,
            'status' => UserStatusEnum::ACTIVE,
            'email_verified_at' => now(),
        ]);

        // Create client user
        User::create([
            'name' => 'Client User',
            'email' => 'client@hovervid.com',
            'password' => Hash::make('Client12345@'),
            'role' => UserRoleEnum::CLIENT,
            'status' => UserStatusEnum::ACTIVE,
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin and Client users created successfully!');
    }
}
