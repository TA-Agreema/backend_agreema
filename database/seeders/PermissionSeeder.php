<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'read.all.roles',
            'create.role',
            'read.role',
            'update.role',
            'delete.role',
            'read.all.users',
            'create.user',
            'read.user',
            'update.user',
            'delete.user',
            'update.user.roles',
            'update.user.status',
            'read.permission',
            // Contract Category permissions
            'read.contract_category',
            'create.contract_category',
            'update.contract_category',
            'delete.contract_category',
            // Contract permissions
            'read.contracts',
            'create.contract',
            'update.contract',
            'delete.contract',
            'terminate.contract',
            'download.contract',
            'create.contract_addendum',
            // Template permissions,
            'read.template',
            'create.template',
            'update.template',
            'delete.template',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }
}
