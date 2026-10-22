<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\RoleController as CpRoleController;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends CpRoleController
{
    use RespondsWithJson;

    public function index(): JsonResponse
    {
        return $this->ok(Role::withCount('users')->with('permissions')->orderBy('name')->get());
    }

    public function permissions(): JsonResponse
    {
        return $this->ok(Permission::orderBy('module')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug'],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);
        $role->permissions()->sync($validated['permissions'] ?? []);

        return $this->created($role->load('permissions'), 'تم إضافة الدور بنجاح.');
    }

    public function show(Role $role): JsonResponse
    {
        return $this->ok($role->loadCount('users')->load('permissions'));
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:roles,slug,'.$role->id],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $role->update([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
        ]);
        // Like the CP form: omitted permissions means "none". Send the full list.
        $role->permissions()->sync($validated['permissions'] ?? []);

        return $this->ok($role->fresh('permissions'), 'تم تحديث الدور بنجاح.');
    }
}
