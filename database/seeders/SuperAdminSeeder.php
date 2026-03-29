<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'swapnil@netswaptech.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'role' => User::ROLE_SUPERADMIN,
                'designation' => 'System Owner',
                'is_active' => true,
                'created_by' => null,
            ]
        );
    }
}