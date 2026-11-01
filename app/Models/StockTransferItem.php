<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id', 'product_id',
        'item_code', 'item_name', 'sku',
        'quantity', 'actual_quantity', 'unit', 'notes',
    ];

    protected $casts = [
        'quantity'        => 'float',
        'actual_quantity' => 'float',
    ];

    public function stockTransfer()
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
