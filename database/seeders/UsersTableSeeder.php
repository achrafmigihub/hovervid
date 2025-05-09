<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@hovervid.com',
            'password' => Hash::make('Admin12345@'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create client user
        User::create([
            'name' => 'Client User',
            'email' => 'client@hovervid.com',
            'password' => Hash::make('Client12345@'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }
} 