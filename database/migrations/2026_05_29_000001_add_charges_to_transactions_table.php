<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('order_type')->nullable()->after('notes');           // dine_in, take_away, delivery
            $table->string('delivery_platform')->nullable()->after('order_type'); // gofood, grabfood, shopeefood
            $table->decimal('service_charge_pct', 5, 2)->default(0)->after('delivery_platform');
            $table->decimal('service_charge_amount', 15, 2)->default(0)->after('service_charge_pct');
            $table->decimal('pb1_pct', 5, 2)->default(0)->after('service_charge_amount');
            $table->decimal('pb1_amount', 15, 2)->default(0)->after('pb1_pct');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'order_type', 'delivery_platform',
                'service_charge_pct', 'service_charge_amount',
                'pb1_pct', 'pb1_amount',
            ]);
        });
    }
};
