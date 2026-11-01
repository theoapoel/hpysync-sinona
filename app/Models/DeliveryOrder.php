<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_no', 'customer_id', 'billing_address', 'order_date', 'delivery_date',
        'notes', 'status', 'payment_status', 'bill_as_receivable',
        'kitchen_status', 'kitchen_started_at', 'kitchen_ready_at', 'kitchen_scheduled_at', 'kitchen_confirmed_at',
        'erp_sales_order', 'erp_sales_invoice', 'erp_si_sync_error', 'erp_sync_status', 'erp_sync_error',
        'erp_material_request', 'erp_mr_sync_error',
        'subtotal', 'total', 'created_by',
    ];

    protected $casts = [
        'order_date'         => 'date',
        'delivery_date'      => 'date',
        'bill_as_receivable' => 'boolean',
        'kitchen_started_at'    => 'datetime',
        'kitchen_ready_at'      => 'datetime',
        'kitchen_scheduled_at'  => 'datetime',
        'kitchen_confirmed_at'  => 'datetime',
        'subtotal'           => 'decimal:2',
        'total'              => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(DeliveryShipment::class)->orderBy('sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DeliveryOrderPayment::class)->orderBy('payment_date');
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function outstanding(): float
    {
        return max(0, (float) $this->total - $this->totalPaid());
    }

    public function recalculatePaymentStatus(): void
    {
        $paid = $this->totalPaid();
        $total = (float) $this->total;

        if ($paid <= 0) {
            $status = 'unpaid';
        } elseif ($paid >= $total) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        $this->update(['payment_status' => $status]);
    }

    public static function generateOrderNo(): string
    {
        $prefix = 'DO-' . now()->format('Ymd') . '-';
        $last   = static::where('order_no', 'like', $prefix . '%')->orderByDesc('id')->first();
        $seq    = $last ? (intval(substr($last->order_no, -4)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function recalculateTotal(): void
    {
        $subtotal = $this->items()->sum('subtotal');
        $this->update(['subtotal' => $subtotal, 'total' => $subtotal]);
    }

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'confirmed']);
    }
}
