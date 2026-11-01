<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        $modules = RolePermission::modules();
        $roles   = Role::configurable(); // all except admin, ordered

        $permissions = [];
        foreach ($roles as $role) {
            $permissions[$role->name] = RolePermission::forRole($role->name);
        }

        return view('permissions.index', compact('modules', 'roles', 'permissions'));
    }

    public function save(Request $request)
    {
        $modules = array_keys(RolePermission::modules());
        $roles   = Role::configurable()->pluck('name');
        $data    = $request->input('permissions', []);

        foreach ($roles as $role) {
            foreach ($modules as $module) {
                $allowed = (bool) ($data[$role][$module] ?? false);
                RolePermission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    ['allowed' => $allowed]
                );
            }
        }

        RolePermission::clearCache();

        return response()->json(['success' => true, 'message' => 'Hak akses berhasil disimpan.']);
    }
}
