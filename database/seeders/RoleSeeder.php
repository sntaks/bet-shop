<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The fixed staff roles and what each may do. Safe to run again: it creates what is missing and
 * resets each role to exactly these permissions. Run it on deploy whenever this list changes.
 */
class RoleSeeder extends Seeder
{
    public const string CASHIER = 'cashier';

    public const string MANAGER = 'manager';

    /**
     * @var array<string, list<string>>
     */
    public const array ROLES = [
        self::CASHIER => [
            'loads.create',
            'loads.view',
            'withdrawals.view',
            'withdrawals.pay',
            'withdrawals.reject',
        ],
        self::MANAGER => [
            'loads.create',
            'loads.view',
            'withdrawals.view',
            'withdrawals.pay',
            'withdrawals.reject',
            'settings.manage',
            'users.manage',
            'activity-log.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_unique(array_merge(...array_values(self::ROLES))) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Model events may be muted (WithoutModelEvents), so the permission cache is not flushed on create.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
