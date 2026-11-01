<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks that a Delivery Order's production schedule has been confirmed in
     * Pulling Order. This is the gate for entering the Kitchen Monitor queue /
     * calendar — a DO is never activated to the kitchen until this is set.
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dateTime('kitchen_confirmed_at')->nullable()->after('kitchen_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('kitchen_confirmed_at');
        });
    }
};
