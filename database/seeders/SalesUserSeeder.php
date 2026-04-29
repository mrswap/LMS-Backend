<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class SalesUserSeeder extends Seeder
{
    public function run(): void
    {
        // Get role safely
        $role = Role::where('name', User::ROLE_SALES)->first();
        $roleId = $role?->id ?? 3; // fallback: 3 = sales

        // Default Sales Person
        User::updateOrCreate(
            ['email' => 'salesperson@netswaptech.com'],
            [
                'name' => 'Sales Person',
                'password' => Hash::make('12345678'),
                'role_id' => $roleId,
                'designation_id' => null,
                'department' => 'Sales',
                'region' => 'India',
                'city' => 'Indore',
                'mobile' => '9999999999',
                'employee_id' => 'EMP001',
                'is_active' => true,
                'created_by' => 1,
                'email_verified_at' => now(),
            ]
        );

        // Kajal User
        User::updateOrCreate(
            ['email' => 'kajalcharve6@gmail.com'],
            [
                'name' => 'Kajal Chrave',
                'password' => Hash::make('123456'),
                'role_id' => $roleId,
                'designation_id' => null,
                'department' => 'Sales',
                'region' => 'India',
                'city' => 'Indore',
                'mobile' => '8888888888',
                'employee_id' => 'EMP002',
                'is_active' => true,
                'created_by' => 1,
                'email_verified_at' => now(),
            ]
        );
    }
}