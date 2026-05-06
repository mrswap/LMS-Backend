<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::insert([

            [
                'name' => 'superadmin',
                'label' => 'Super Admin',
                'is_system' => true,
                'is_active' => true,
            ],

            [
                'name' => 'admin',
                'label' => 'Admin',
                'is_system' => true,
                'is_active' => true,
            ],

            [
                'name' => 'staff',
                'label' => 'Staff',
                'is_system' => true,
                'is_active' => true,
            ],

            [
                'name' => 'sales',
                'label' => 'Sales',
                'is_system' => true,
                'is_active' => true,
            ],
        ]);
    }
}
