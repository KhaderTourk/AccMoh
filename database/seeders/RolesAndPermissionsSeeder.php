<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'النظام المالي', 'slug' => 'finance', 'module' => 'المالية'],
            ['name' => 'إدارة المستخدمين والصلاحيات', 'slug' => 'users', 'module' => 'النظام'],
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['slug' => $p['slug']], $p);
        }

        // Remove obsolete website permissions if present
        Permission::query()->whereIn('slug', ['settings', 'home'])->delete();

        $adminRole = Role::firstOrCreate(['slug' => 'admin'], [
            'name' => 'مدير',
            'description' => 'صلاحيات كاملة للوحة التحكم',
        ]);
        $adminRole->permissions()->sync(Permission::pluck('id'));

        $editorRole = Role::firstOrCreate(['slug' => 'editor'], [
            'name' => 'محرر',
            'description' => 'صلاحيات النظام المالي',
        ]);
        $editorRole->permissions()->sync(
            Permission::whereIn('slug', ['finance'])->pluck('id')
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'role_id' => $adminRole->id,
                'is_active' => true,
            ]
        );
        if (! $admin->is_super_admin) {
            $admin->update(['is_super_admin' => true, 'role_id' => $adminRole->id]);
        }
    }
}
