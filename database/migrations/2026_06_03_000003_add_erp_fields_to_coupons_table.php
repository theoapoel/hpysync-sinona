<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('erp_coupon_name')->nullable()->after('code');
            $table->string('erp_pricing_rule')->nullable()->after('erp_coupon_name');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['erp_coupon_name', 'erp_pricing_rule']);
        });
    }
};
