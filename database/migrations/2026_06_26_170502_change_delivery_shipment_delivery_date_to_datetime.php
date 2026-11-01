<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_shipments', function (Blueprint $table) {
            $table->dateTime('delivery_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_shipments', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->change();
        });
    }
};
