<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'product_id', 'system_qty', 'actual_qty', 'difference',
    ];

    public function stockOpname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getStatusAttribute(): string
    {
        if ($this->difference > 0) return 'lebih';
        if ($this->difference < 0) return 'kurang';
        return 'sama';
    }
}
