<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | SUPERADMIN
        |--------------------------------------------------------------------------
        */

        $superadmin = Role::where(
            'name',
            'superadmin'
        )->first();

        $superadmin->permissions()->sync(
            Permission::pluck('id')->toArray()
        );

        /*
        |--------------------------------------------------------------------------
        | ADMIN
        |--------------------------------------------------------------------------
        */

        $admin = Role::where(
            'name',
            'admin'
        )->first();

        $admin->permissions()->sync(

            Permission::whereNotIn('name', [

                'roles.delete',

            ])->pluck('id')->toArray()
        );
    }
}