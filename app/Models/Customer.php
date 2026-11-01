<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'code', 'name', 'email', 'phone', 'address',
        'loyalty_points', 'total_purchase', 'is_active',
        'erp_customer_name', 'erp_last_sync',
    ];

    protected $casts = [
        'loyalty_points' => 'decimal:2',
        'total_purchase' => 'decimal:2',
        'is_active' => 'boolean',
        'erp_last_sync' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public static function generateCode(): string
    {
        $last = static::orderByDesc('id')->first();
        $seq = $last ? (intval(substr($last->code, 4)) + 1) : 1;
        return 'CUST' . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function scopeSearch($query, $term) {
        return $query->where(function($q) use ($term) {
            $q->where('name','LIKE',"%{$term}%")
              ->orWhere('phone','LIKE',"%{$term}%")
              ->orWhere('email','LIKE',"%{$term}%")
              ->orWhere('code','LIKE',"%{$term}%");
        });
    }
}
