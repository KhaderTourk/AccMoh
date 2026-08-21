<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeOrAbort();
    }

    protected function authorizeOrAbort(): void
    {
        if (!auth()->user()?->canAccess('users')) {
            abort(403, 'ليس لديك صلاحية إدارة المستخدمين.');
        }
    }

    public function index()
    {
        $users = User::with('role')->orderBy('name')->paginate(15);
        return view('cp.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::orderBy('name')->get();
        return view('cp.users.create', compact('roles'));
    }

    public function store(Request $request)
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
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_super_admin'] = auth()->user()->is_super_admin && $request->boolean('is_super_admin');

        User::create($validated);
        return redirect()->route('cp.users.index')->with('success', 'تم إضافة المستخدم بنجاح.');
    }

    public function edit(User $user)
    {
        $roles = Role::orderBy('name')->get();
        return view('cp.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['boolean'],
            'is_super_admin' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_super_admin'] = auth()->user()->is_super_admin && $request->boolean('is_super_admin');

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        return redirect()->route('cp.users.index')->with('success', 'تم تحديث المستخدم بنجاح.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'لا يمكنك حذف حسابك.');
        }
        $user->delete();
        return redirect()->route('cp.users.index')->with('success', 'تم حذف المستخدم.');
    }
}
