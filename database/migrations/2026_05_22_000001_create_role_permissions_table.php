<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('module');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'module']);
        });

        // Seed defaults
        $now = now();
        $all = ['dashboard','pos','transactions','products','customers','stock_transfer','sync'];

        $rows = [];
        foreach ($all as $module) {
            $rows[] = ['role' => 'manager', 'module' => $module, 'allowed' => true,  'created_at' => $now, 'updated_at' => $now];
        }
        foreach ($all as $module) {
            $rows[] = ['role' => 'cashier', 'module' => $module, 'allowed' => in_array($module, ['pos','transactions']), 'created_at' => $now, 'updated_at' => $now];
        }

        DB::table('role_permissions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
