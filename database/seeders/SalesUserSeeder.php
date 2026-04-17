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
            ]
        );
    }
}
