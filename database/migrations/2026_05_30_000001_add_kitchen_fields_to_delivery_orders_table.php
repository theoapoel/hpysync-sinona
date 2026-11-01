<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('kitchen_status', 20)->nullable()->after('status');
            // null  = belum masuk dapur (draft/cancelled)
            // pending   = menunggu diproses
            // preparing = sedang diproses
            // ready     = siap dikirim
            $table->timestamp('kitchen_started_at')->nullable()->after('kitchen_status');
            $table->timestamp('kitchen_ready_at')->nullable()->after('kitchen_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['kitchen_status', 'kitchen_started_at', 'kitchen_ready_at']);
        });
    }
};
