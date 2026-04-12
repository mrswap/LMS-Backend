<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run()
    {
        Role::insert([
            [
                'name' => 'superadmin',
                'label' => 'Super Admin',
                'is_system' => true,
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'is_system' => true,
            ],
            [
                'name' => 'sales',
                'label' => 'Sales',
                'is_system' => true,
            ],
        ]);
    }
}
