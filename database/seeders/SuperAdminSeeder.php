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
        // Get role safely
        $role = Role::where('name', User::ROLE_SUPERADMIN)->first();
        $roleId = $role?->id ?? 1;

        $users = [
            [
                'email' => 'swapnil@netswaptech.com',
                'name' => 'Super Admin',
            ],
            [
                'email' => 'ajaycharve109@gmail.com',
                'name' => 'Ajay Charve',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('12345678'),
                    'role_id' => $roleId,
                    'designation_id' => null,
                    'is_active' => true,
                    'created_by' => null,
                ]
            );
        }
    }
}
