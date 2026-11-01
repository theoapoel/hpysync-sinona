<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrderPayment extends Model
{
    protected $fillable = [
        'delivery_order_id', 'payment_method', 'amount', 'payment_date',
        'reference_no', 'notes',
        'erp_payment_entry', 'erp_sync_status', 'erp_sync_error',
        'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function methodLabel(): string
    {
        // Legacy values from old enum; new records store ERP MOP name directly.
        return match ($this->payment_method) {
            'cash'     => 'Cash',
            'transfer' => 'Transfer Bank',
            'qris'     => 'QRIS',
            'card'     => 'Kartu',
            default    => $this->payment_method,
        };
    }

    public function methodIcon(): string
    {
        $m = strtolower($this->payment_method);
        if (str_contains($m, 'cash'))                         return 'fa-money-bill-wave';
        if (str_contains($m, 'qris') || str_contains($m, 'qr')) return 'fa-qrcode';
        if (str_contains($m, 'card') || str_contains($m, 'credit') || str_contains($m, 'debit')) return 'fa-credit-card';
        if (str_contains($m, 'bank') || str_contains($m, 'transfer') || str_contains($m, 'wire')) return 'fa-university';
        if (str_contains($m, 'cheque') || str_contains($m, 'check'))   return 'fa-file-alt';
        return 'fa-money-bill';
    }
}
