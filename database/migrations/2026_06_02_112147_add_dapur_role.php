<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Insert dapur role (skip if already exists)
        if (!DB::table('roles')->where('name', 'dapur')->exists()) {
            DB::table('roles')->insert([
                'name'        => 'dapur',
                'label'       => 'Dapur',
                'description' => 'Akses Kitchen Monitor untuk melihat dan mengubah status pesanan dapur.',
                'color'       => '#FF6B35',
                'is_system'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Seed permissions: kitchen = true, semua modul lain = false
        $modules = array_keys(\App\Models\RolePermission::modules());
        foreach ($modules as $module) {
            DB::table('role_permissions')->updateOrInsert(
                ['role' => 'dapur', 'module' => $module],
                ['allowed' => $module === 'kitchen', 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('role', 'dapur')->delete();
        DB::table('roles')->where('name', 'dapur')->where('is_system', true)->delete();
    }
};
