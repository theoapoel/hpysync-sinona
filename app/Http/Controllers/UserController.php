<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($search = $request->get('search')) {
            $query->where(fn($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%"));
        }

        if ($role = $request->get('role')) {
            $query->where('role', $role);
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles    = Role::allOrdered();
        $rolePerms = $this->buildRolePermsForJs($roles);
        return view('users.create', compact('roles', 'rolePerms'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|max:150|unique:users,email',
            'password'  => ['nullable', Password::min(8)],
            'role'      => 'required|exists:roles,name',
            'pin'       => 'nullable|digits:6',
            'is_active' => 'boolean',
        ]);

        User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->filled('password') ? $request->password : \Illuminate\Support\Str::random(32)),
            'role'      => $request->role,
            'pin'       => $request->filled('pin') ? $request->pin : null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('users.index')
            ->with('success', "User {$request->name} berhasil ditambahkan.");
    }

    public function edit(User $user)
    {
        $roles     = Role::allOrdered();
        $rolePerms = $this->buildRolePermsForJs($roles);
        return view('users.edit', compact('user', 'roles', 'rolePerms'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'email'     => 'required|email|max:150|unique:users,email,' . $user->id,
            'password'  => ['nullable', Password::min(8)],
            'role'      => 'required|exists:roles,name',
            'pin'       => 'nullable|digits:6',
            'is_active' => 'boolean',
        ]);

        if ($user->id === auth()->id() && $request->role !== 'admin') {
            return back()->withErrors(['role' => 'Anda tidak dapat mengubah role akun Anda sendiri.'])->withInput();
        }

        $data = [
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'is_active' => $user->id === auth()->id() ? true : $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->filled('pin')) {
            $data['pin'] = $request->pin;
        } elseif ($request->boolean('clear_pin')) {
            $data['pin'] = null;
        }

        $user->update($data);

        return redirect()->route('users.index')
            ->with('success', "User {$user->name} berhasil diperbarui.");
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['success' => false, 'error' => 'Tidak dapat menonaktifkan akun Anda sendiri.']);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success'   => true,
            'is_active' => $user->is_active,
            'message'   => $user->is_active ? "{$user->name} diaktifkan." : "{$user->name} dinonaktifkan.",
        ]);
    }

    private function buildRolePermsForJs($roles): array
    {
        $modules = array_keys(RolePermission::modules());
        $result  = [];
        foreach ($roles as $role) {
            if ($role->name === 'admin') {
                $result[$role->name] = array_fill_keys($modules, true);
            } else {
                $perms = RolePermission::forRole($role->name);
                $result[$role->name] = array_fill_keys($modules, false);
                foreach ($modules as $m) {
                    $result[$role->name][$m] = (bool) ($perms[$m] ?? false);
                }
            }
        }
        return $result;
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        if ($user->transactions()->exists()) {
            return back()->with('error', "User {$user->name} memiliki data transaksi dan tidak dapat dihapus. Nonaktifkan saja.");
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "User {$name} berhasil dihapus.");
    }
}
