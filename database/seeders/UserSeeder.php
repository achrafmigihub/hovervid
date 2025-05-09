<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Admin User',
                'email' => 'admin@hovervid.com',
                'password' => Hash::make('Admin12345@'),
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Client User',
                'email' => 'client@hovervid.com',
                'password' => Hash::make('Client12345@'),
                'role' => 'client',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
} 