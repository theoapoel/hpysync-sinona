<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('coupon_code', 50)->nullable()->after('discount_percent');
            $table->decimal('coupon_discount', 12, 2)->default(0)->after('coupon_code');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['coupon_code', 'coupon_discount']);
        });
    }
};
