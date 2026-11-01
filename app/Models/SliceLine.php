<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SliceLine extends Model
{
    protected $fillable = [
        'slice_id', 'line_type', 'sort_order',
        'product_id', 'item_name', 'item_code', 'qty', 'uom',
        'warehouse', 'notes',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
    ];

    public function slice()
    {
        return $this->belongsTo(Slice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
