<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku', 'barcode', 'name', 'description', 'category_id', 'item_category_id',
        'price', 'cost_price', 'stock', 'min_stock', 'unit',
        'image', 'erp_image', 'is_active', 'track_stock', 'tax_rate',
        'erp_item_code', 'erp_last_sync',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
        'erp_last_sync' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function itemCategory(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockInWarehouse(int $warehouseId): int
    {
        return $this->productStocks->firstWhere('warehouse_id', $warehouseId)?->quantity ?? 0;
    }

    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock <= $this->min_stock;
    }

    public function scopeActive($query) { return $query->where('is_active', true); }

    /**
     * Filter products to the Item Groups (categories) configured for a given screen.
     * The setting stores a JSON array of category IDs; empty/unset = show all.
     */
    public function scopeInItemGroups($query, string $settingKey)
    {
        $ids = json_decode(Setting::get($settingKey, '[]'), true);
        if (is_array($ids) && count($ids)) {
            $query->whereIn('category_id', $ids);
        }
        return $query;
    }

    public function scopeSearch($query, $term) {
        // Pisah jadi kata: tiap kata harus cocok (urutan bebas).
        // Jadi "baso yamin" tetap menemukan "YAMIN MANIS BASO URAT".
        $words = preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function($outer) use ($words, $term) {
            // Cocok penuh pada sku/barcode (kode biasanya utuh, tanpa spasi).
            $outer->where('sku', 'LIKE', "%{$term}%")
                  ->orWhere('barcode', 'LIKE', "%{$term}%");

            // Atau: semua kata muncul di nama (AND antar kata).
            $outer->orWhere(function($q) use ($words) {
                foreach ($words as $word) {
                    $q->where('name', 'LIKE', "%{$word}%");
                }
            });
        });
    }
}
