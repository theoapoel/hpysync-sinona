<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no')->unique();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->text('billing_address')->nullable();
            $table->date('delivery_date');
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'delivering', 'completed', 'cancelled'])->default('draft');
            $table->string('erp_sales_order')->nullable();
            $table->enum('erp_sync_status', ['none', 'synced', 'failed'])->default('none');
            $table->text('erp_sync_error')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};
