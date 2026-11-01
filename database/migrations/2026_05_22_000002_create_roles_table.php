<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();        // slug: admin, manager, cashier, supervisor
            $table->string('label');                  // display: Admin, Manager, Kasir
            $table->string('description')->nullable();
            $table->string('color', 7)->default('#4285F4');
            $table->boolean('is_system')->default(false); // system roles cannot be deleted
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            ['name' => 'admin',   'label' => 'Admin',   'description' => 'Akses penuh ke semua fitur sistem.',          'color' => '#4285F4', 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'manager', 'label' => 'Manager', 'description' => 'Akses operasional: POS, produk, stok, sync.', 'color' => '#E37400', 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
            ['name' => 'cashier', 'label' => 'Kasir',   'description' => 'Hanya akses POS dan lihat transaksi.',        'color' => '#34A853', 'is_system' => true,  'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
