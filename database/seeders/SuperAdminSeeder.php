<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', User::ROLE_SUPERADMIN)->first();
        $roleId = $role?->id ?? 1;

        $users = [

            /*
            |--------------------------------------------------------------------------
            | Existing Admins
            |--------------------------------------------------------------------------
            */
            [
                'email' => 'swapnil@netswaptech.com',
                'name'  => 'Super Admin',
                'password' => '12345678',
            ],
            [
                'email' => 'ajaycharve109@gmail.com',
                'name'  => 'Ajay Charve',
                'password' => '12345678',
            ],

            /*
            |--------------------------------------------------------------------------
            | Avante Admin
            |--------------------------------------------------------------------------
            */
            [
                'email' => 'admin@avante-medical.com',
                'name'  => 'Avante Admin',
                'password' => 'avante@12345',
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
                    'department' => 'Administration',
                    'is_active' => true,
                    'created_by' => null,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}