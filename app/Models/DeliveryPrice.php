<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryPrice extends Model
{
    protected $fillable = ['erp_item_code', 'platform', 'price'];

    protected $casts = ['price' => 'float'];

    /**
     * Return all prices grouped by platform as nested arrays:
     * ['gofood' => ['ITEM-001' => 25000, ...], ...]
     */
    public static function allGrouped(): array
    {
        $base = ['gofood' => [], 'grabfood' => [], 'shopeefood' => []];

        $grouped = static::all()
            ->groupBy('platform')
            ->map(fn($rows) => $rows->pluck('price', 'erp_item_code')->toArray())
            ->toArray();

        return array_merge($base, $grouped);
    }
}
