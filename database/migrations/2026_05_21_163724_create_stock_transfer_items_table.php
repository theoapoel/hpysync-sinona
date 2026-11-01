<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_code');
            $table->string('item_name');
            $table->string('sku')->nullable();
            $table->decimal('quantity', 15, 3);
            $table->decimal('actual_quantity', 15, 3)->nullable()->comment('Actual qty received (for incoming)');
            $table->string('unit')->default('Nos');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('stock_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
