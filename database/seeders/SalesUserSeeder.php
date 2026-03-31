<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SalesUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'salesperson@netswaptech.com'],
            [
                'name' => 'Sales Person',
                'password' => Hash::make('12345678'),
                'role' => User::ROLE_SALES,
                'designation' => 'Sales Executive',
                'department' => 'Sales',
                'region' => 'India',
                'city' => 'Indore',
                'mobile' => '9999999999',
                'employee_id' => 'EMP001',
                'is_active' => true,
                'created_by' => 1,
            ]
        );
    }
}
