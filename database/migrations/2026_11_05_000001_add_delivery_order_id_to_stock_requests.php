<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permintaan FG yang dibuat otomatis saat Delivery Order dikonfirmasi
     * menyimpan asal DO-nya di sini. NULL = permintaan manual biasa.
     */
    public function up(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('delivery_order_id')->nullable()->after('requested_by');
            $table->index('delivery_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_requests', function (Blueprint $table) {
            $table->dropIndex(['delivery_order_id']);
            $table->dropColumn('delivery_order_id');
        });
    }
};
