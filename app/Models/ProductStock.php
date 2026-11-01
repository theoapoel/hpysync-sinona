<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = ['product_id', 'warehouse_id', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Ambil atau buat record stok untuk kombinasi produk + warehouse.
     * Gunakan ini sebelum increment/decrement agar record selalu ada.
     */
    public static function forProductWarehouse(int $productId, int $warehouseId): self
    {
        return static::firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0]
        );
    }

    public function incrementQty(float $amount): void
    {
        $this->increment('quantity', $amount);
    }

    public function decrementQty(float $amount): void
    {
        $this->decrement('quantity', $amount);
    }
}
