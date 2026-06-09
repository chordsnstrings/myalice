<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Roles & capabilities per §4.3 of the functional spec.
 * Owner / Manager / Agent / Developer.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'billing.manage',
            'channels.manage',
            'team.manage',
            'bots.manage',
            'broadcasts.create',
            'inbox.work',
            'orders.create',
            'reports.view-all',
            'api.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name);
        }

        $roles = [
            'owner' => $permissions, // everything
            'manager' => ['channels.manage', 'team.manage', 'bots.manage', 'broadcasts.create', 'inbox.work', 'orders.create', 'reports.view-all'],
            'agent' => ['inbox.work', 'orders.create'],
            'developer' => ['api.manage'],
        ];

        foreach ($roles as $role => $grants) {
            Role::findOrCreate($role)->syncPermissions($grants);
        }
    }
}
