<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();           // ERPNext warehouse name (primary key from ERP)
            $table->string('warehouse_name')->nullable(); // display name
            $table->string('company')->nullable();
            $table->string('warehouse_type')->nullable(); // Stores, Transit, Supplier, etc.
            $table->string('parent_warehouse')->nullable();
            $table->boolean('is_group')->default(false);
            $table->boolean('is_active')->default(false);  // assigned to this POS
            $table->boolean('is_default')->default(false); // default for POS transactions
            $table->boolean('is_transit')->default(false); // in-transit for stock transfer
            $table->timestamp('erp_last_pulled')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
