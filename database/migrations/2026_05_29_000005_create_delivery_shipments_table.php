<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence')->default(1);
            $table->string('recipient_name');
            $table->string('recipient_phone')->nullable();
            $table->text('shipping_address');
            $table->text('notes')->nullable();
            // Items stored as JSON: [{product_name, product_sku, qty, price, subtotal}]
            $table->json('items')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->enum('status', ['pending', 'out_for_delivery', 'delivered', 'failed'])->default('pending');
            $table->string('erp_delivery_note')->nullable();
            $table->enum('erp_sync_status', ['none', 'synced', 'failed'])->default('none');
            $table->text('erp_sync_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_shipments');
    }
};
