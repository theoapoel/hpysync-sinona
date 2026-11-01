<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryShipment extends Model
{
    protected $fillable = [
        'delivery_order_id', 'sequence',
        'recipient_name', 'recipient_phone', 'shipping_address', 'delivery_date',
        'notes', 'items', 'total',
        'status', 'erp_delivery_note', 'erp_sync_status', 'erp_sync_error',
        'delivered_at',
    ];

    protected $casts = [
        'items'         => 'array',
        'total'         => 'decimal:2',
        'delivery_date' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function recalculateTotal(): void
    {
        $total = collect($this->items ?? [])->sum(fn($i) => ($i['subtotal'] ?? 0));
        $this->update(['total' => $total]);
    }
}
