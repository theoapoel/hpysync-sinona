<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->date('order_date')->nullable()->after('billing_address');
        });

        Schema::table('delivery_shipments', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('shipping_address');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('order_date');
        });

        Schema::table('delivery_shipments', function (Blueprint $table) {
            $table->dropColumn('delivery_date');
        });
    }
};
