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
        $role = Role::where('name', User::ROLE_SALES)->first();
        $roleId = $role?->id ?? 3;

        $users = [

            /*
            |--------------------------------------------------------------------------
            | Existing Users
            |--------------------------------------------------------------------------
            */

            [
                'email' => 'salesperson@netswaptech.com',
                'name' => 'Sales Person',
                'password' => '12345678',
                'department' => 'Sales',
                'mobile' => '9999999999',
                'employee_id' => 'EMP001',
            ],

            [
                'email' => 'salesperson@avanta.com',
                'name' => 'Avanta Sales',
                'password' => '12345678',
                'department' => 'Sales',
                'mobile' => '7777777777',
                'employee_id' => 'EMP003',
            ],

            [
                'email' => 'kajalcharve6@gmail.com',
                'name' => 'Kajal Charve',
                'password' => '12345678',
                'department' => 'Sales',
                'mobile' => '8888888888',
                'employee_id' => 'EMP002',
            ],

            /*
            |--------------------------------------------------------------------------
            | Avante Sales
            |--------------------------------------------------------------------------
            */

            [
                'email' => 'sales@avante-medical.com',
                'name' => 'Avante Sales',
                'password' => 'avante@12345',
                'department' => 'Sales',
                'mobile' => null,
                'employee_id' => 'AVT-SALES-001',
            ],

            /*
            |--------------------------------------------------------------------------
            | Avante Training User
            |--------------------------------------------------------------------------
            */

            [
                'email' => 'training@avante-medical.com',
                'name' => 'Training User',
                'password' => 'avante@12345',
                'department' => 'Training',
                'mobile' => null,
                'employee_id' => 'AVT-TRN-001',
            ],

            /*
            |--------------------------------------------------------------------------
            | Avante Demo User
            |--------------------------------------------------------------------------
            */

            [
                'email' => 'demo@avante-medical.com',
                'name' => 'Demo User',
                'password' => 'avante@12345',
                'department' => 'Training',
                'mobile' => null,
                'employee_id' => 'AVT-DEMO-001',
            ],

        ];

        foreach ($users as $user) {

            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make($user['password']),
                    'role_id' => $roleId,
                    'designation_id' => null,
                    'department' => $user['department'],
                    'region' => 'USA',
                    'city' => null,
                    'mobile' => $user['mobile'],
                    'employee_id' => $user['employee_id'],
                    'is_active' => true,
                    'created_by' => 1,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}