<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRequestItem extends Model
{
    protected $fillable = [
        'stock_request_id', 'product_id', 'item_name',
        'item_code', 'qty', 'uom', 'notes',
    ];

    public function request()
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
