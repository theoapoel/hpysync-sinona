<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slice_id')->constrained('slices')->cascadeOnDelete();

            // Sumber — item yang dipotong / diissue (mis. Bolu)
            $table->foreignId('source_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('source_item_name', 200);
            $table->string('source_item_code', 100)->nullable();
            $table->decimal('source_qty', 12, 2);
            $table->string('source_uom', 30)->default('Nos');

            // Hasil — item yang terbentuk / diterima (mis. Slice)
            $table->foreignId('target_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('target_item_name', 200);
            $table->string('target_item_code', 100)->nullable();
            $table->decimal('target_qty', 12, 2);
            $table->string('target_uom', 30)->default('Nos');

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slice_items');
    }
};
