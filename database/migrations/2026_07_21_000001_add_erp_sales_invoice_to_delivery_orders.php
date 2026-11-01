<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('erp_sales_invoice')->nullable()->after('erp_sales_order');
            $table->text('erp_si_sync_error')->nullable()->after('erp_sales_invoice');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['erp_sales_invoice', 'erp_si_sync_error']);
        });
    }
};
