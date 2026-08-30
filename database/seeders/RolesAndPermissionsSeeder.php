<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
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

        $tenant = Tenant::query()->first();
        if (! $tenant) {
            $tenant = Tenant::query()->create([
                'name' => 'AccMa',
                'slug' => 'default',
                'business_enabled' => true,
                'is_active' => true,
            ]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'role_id' => $adminRole->id,
                'is_active' => true,
                'tenant_id' => $tenant->id,
            ]
        );
        if (! $admin->is_super_admin || ! $admin->tenant_id) {
            $admin->update([
                'is_super_admin' => true,
                'role_id' => $adminRole->id,
                'tenant_id' => $admin->tenant_id ?: $tenant->id,
            ]);
        }
        if (! $tenant->owner_user_id) {
            $tenant->update(['owner_user_id' => $admin->id]);
        }
    }
}
