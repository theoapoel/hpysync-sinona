<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Model dua-tabel: setiap baris adalah satu item yang di-issue (keluar)
        // atau di-receipt (masuk). Menggantikan struktur pasangan sumber↔hasil.
        Schema::create('slice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slice_id')->constrained('slices')->cascadeOnDelete();
            $table->string('line_type', 10);                 // issue | receipt
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('item_name', 200);
            $table->string('item_code', 100)->nullable();
            $table->decimal('qty', 12, 2);
            $table->string('uom', 30)->default('Nos');

            // Gudang untuk baris issue (kosong = gudang default). Receipt selalu gudang default.
            $table->string('warehouse', 140)->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['slice_id', 'line_type']);
        });

        Schema::dropIfExists('slice_items');
    }

    public function down(): void
    {
        Schema::dropIfExists('slice_lines');

        // Bentuk ulang struktur lama (pasangan sumber↔hasil) bila di-rollback.
        Schema::create('slice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slice_id')->constrained('slices')->cascadeOnDelete();
            $table->foreignId('source_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('source_item_name', 200);
            $table->string('source_item_code', 100)->nullable();
            $table->decimal('source_qty', 12, 2);
            $table->string('source_uom', 30)->default('Nos');
            $table->foreignId('target_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('target_item_name', 200);
            $table->string('target_item_code', 100)->nullable();
            $table->decimal('target_qty', 12, 2);
            $table->string('target_uom', 30)->default('Nos');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};
