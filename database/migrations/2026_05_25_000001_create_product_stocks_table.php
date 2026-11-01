<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // Migrate existing stock data ke default warehouse
        $defaultWarehouse = DB::table('warehouses')->where('is_default', true)->first();
        if ($defaultWarehouse) {
            $products = DB::table('products')
                ->where('track_stock', true)
                ->select('id', 'stock')
                ->get();

            foreach ($products as $product) {
                DB::table('product_stocks')->insert([
                    'product_id'   => $product->id,
                    'warehouse_id' => $defaultWarehouse->id,
                    'quantity'     => $product->stock,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stocks');
    }
};
