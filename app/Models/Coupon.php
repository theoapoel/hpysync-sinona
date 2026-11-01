<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'erp_coupon_name', 'erp_pricing_rule', 'description',
        'discount_type', 'discount_value',
        'min_purchase', 'max_uses', 'used_count', 'valid_from', 'valid_until', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_purchase'   => 'decimal:2',
        'valid_from'     => 'date',
        'valid_until'    => 'date',
        'is_active'      => 'boolean',
    ];

    public function isValid(float $subtotal = 0): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Kupon tidak aktif'];
        }
        if ($this->valid_from && now()->lt($this->valid_from->startOfDay())) {
            return ['valid' => false, 'message' => 'Kupon belum berlaku'];
        }
        if ($this->valid_until && now()->gt($this->valid_until->endOfDay())) {
            return ['valid' => false, 'message' => 'Kupon sudah kadaluarsa'];
        }
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Kupon sudah habis digunakan'];
        }
        if ($subtotal > 0 && (float) $this->min_purchase > 0 && $subtotal < (float) $this->min_purchase) {
            return ['valid' => false, 'message' => 'Minimal pembelian Rp ' . number_format($this->min_purchase, 0, ',', '.')];
        }
        return ['valid' => true, 'message' => 'Kupon berhasil diterapkan'];
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percent') {
            return round($subtotal * (float) $this->discount_value / 100, 2);
        }
        return min((float) $this->discount_value, $subtotal);
    }
}
