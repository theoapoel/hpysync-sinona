<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_no', 'user_id', 'customer_id', 'status',
        'subtotal', 'discount_amount', 'discount_percent', 'coupon_code', 'coupon_discount', 'tax_amount',
        'total', 'paid_amount', 'change_amount', 'payment_method',
        'payment_details', 'notes', 'pos_class',
        'order_type', 'delivery_platform', 'table_number',
        'service_charge_pct', 'service_charge_amount',
        'pb1_pct', 'pb1_amount',
        'idempotency_key',
        'erp_pos_invoice', 'erp_synced_at',
        'erp_sync_status', 'erp_sync_error',
    ];

    protected $casts = [
        'payment_details'       => 'array',
        'erp_synced_at'         => 'datetime',
        'subtotal'              => 'decimal:2',
        'discount_amount'       => 'decimal:2',
        'coupon_discount'       => 'decimal:2',
        'tax_amount'            => 'decimal:2',
        'total'                 => 'decimal:2',
        'paid_amount'           => 'decimal:2',
        'change_amount'         => 'decimal:2',
        'service_charge_pct'    => 'decimal:2',
        'service_charge_amount' => 'decimal:2',
        'pb1_pct'               => 'decimal:2',
        'pb1_amount'            => 'decimal:2',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function generateInvoiceNo(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $last = static::whereDate('created_at', today())
            ->orderByDesc('id')->first();
        $seq = $last ? (intval(substr($last->invoice_no, -4)) + 1) : 1;
        return $prefix . '-' . $date . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function isPendingSync(): bool
    {
        return $this->erp_sync_status === 'pending';
    }

    public function isSynced(): bool
    {
        return $this->erp_sync_status === 'synced';
    }
}
