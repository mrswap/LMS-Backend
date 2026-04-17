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

        $roleId = $role?->id ?? 1; // fallback: 1 = superadmin

        User::updateOrCreate(
            ['email' => 'swapnil@netswaptech.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('12345678'),
                'role_id' => $roleId,
                'designation_id' => null, // better than string
                'is_active' => true,
                'created_by' => null,
            ]
        );
    }
}
