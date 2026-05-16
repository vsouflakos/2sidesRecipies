<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $userPermissions = ['create-recipes', 'manage-own-ingredients'];
        $moderatorPermissions = array_merge($userPermissions, ['review-ingredients']);
        $adminPermissions = array_merge($moderatorPermissions, ['manage-users']);

        foreach ($adminPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        $userRole = Role::firstOrCreate(['name' => 'User']);
        $moderatorRole = Role::firstOrCreate(['name' => 'Moderator']);
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);

        $userRole->syncPermissions($userPermissions);
        $moderatorRole->syncPermissions($moderatorPermissions);
        $adminRole->syncPermissions($adminPermissions);

        $admin = User::firstOrCreate(
            ['email' => 'admin@twosides.test'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );

        $admin->assignRole('Admin');
    }
}
