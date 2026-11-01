<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('erp_material_request', 100)->nullable()->after('erp_si_sync_error');
            $table->text('erp_mr_sync_error')->nullable()->after('erp_material_request');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['erp_material_request', 'erp_mr_sync_error']);
        });
    }
};
