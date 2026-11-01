<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderByDesc('is_system')->orderBy('label')->get();
        $modules = RolePermission::modules();
        return view('roles.index', compact('roles', 'modules'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'label'       => 'required|string|max:50',
            'name'        => ['required', 'string', 'max:50', 'unique:roles,name', 'regex:/^[a-z][a-z0-9_]*$/'],
            'description' => 'nullable|string|max:200',
            'color'       => 'required|string|max:7',
        ], [
            'name.regex' => 'Slug hanya boleh huruf kecil, angka, dan underscore.',
        ]);

        $role = Role::create([
            'name'        => $request->name,
            'label'       => $request->label,
            'description' => $request->description,
            'color'       => $request->color,
            'is_system'   => false,
        ]);

        // Seed default permissions (all false) for the new role
        $now = now();
        $rows = [];
        foreach (array_keys(RolePermission::modules()) as $module) {
            $rows[] = ['role' => $role->name, 'module' => $module, 'allowed' => false, 'created_at' => $now, 'updated_at' => $now];
        }
        RolePermission::insert($rows);

        return back()->with('success', "Role \"{$role->label}\" berhasil ditambahkan. Atur hak aksesnya di halaman Hak Akses.");
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'label'       => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
            'color'       => 'required|string|max:7',
        ]);

        $role->update([
            'label'       => $request->label,
            'description' => $request->description,
            'color'       => $request->color,
        ]);

        return response()->json(['success' => true, 'message' => "Role \"{$role->label}\" berhasil diperbarui."]);
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', "Role sistem \"{$role->label}\" tidak dapat dihapus.");
        }

        $userCount = $role->users()->count();
        if ($userCount > 0) {
            return back()->with('error', "Role \"{$role->label}\" masih digunakan oleh {$userCount} user. Pindahkan user terlebih dahulu.");
        }

        RolePermission::where('role', $role->name)->delete();
        RolePermission::clearCache($role->name);
        $role->delete();

        return back()->with('success', "Role \"{$role->label}\" berhasil dihapus.");
    }

    /** Auto-generate slug from label */
    public function generateSlug(Request $request)
    {
        $slug = Str::slug($request->label, '_');
        $exists = Role::where('name', $slug)->exists();
        return response()->json(['slug' => $slug, 'available' => !$exists]);
    }
}
