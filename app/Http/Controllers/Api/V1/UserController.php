<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\RespondsWithJson;
use App\Http\Controllers\Cp\UserController as CpUserController;
use App\Models\Role;
use App\Models\User;
use App\Support\ArchiveVisibility;
use App\Support\TableSort;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends CpUserController
{
    use RespondsWithJson;

    public function index(): JsonResponse
    {
        return response()->json(TableSort::paginate(
            User::with('role')
                ->where('tenant_id', auth()->user()->tenant_id)
                ->when(! ArchiveVisibility::showing(), fn ($q) => $q->where('is_active', true)),
            [
                'name' => ['column' => 'name'],
                'email' => ['column' => 'email'],
                'role' => ['query' => fn () => Role::query()->select('name')->whereColumn('roles.id', 'users.role_id')],
                'status' => ['column' => 'is_active', 'dir' => 'desc'],
            ],
            'name',
            15
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'is_super_admin' => ['boolean'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_super_admin'] = auth()->user()->is_super_admin && $request->boolean('is_super_admin');
        $validated['tenant_id'] = auth()->user()->tenant_id;
        $validated['is_platform_admin'] = false;

        $user = User::create($validated);

        return $this->created($user->load('role'), 'تم إضافة المستخدم بنجاح.');
    }

    public function show(User $user): JsonResponse
    {
        $this->assertSameTenant($user);

        return $this->ok($user->load('role'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->assertSameTenant($user);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'is_super_admin' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', (bool) $user->is_active);
        $validated['is_super_admin'] = auth()->user()->is_super_admin
            && $request->boolean('is_super_admin', (bool) $user->is_super_admin);

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return $this->ok($user->fresh('role'), 'تم تحديث المستخدم بنجاح.');
    }
}
