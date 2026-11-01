<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'transfer_no', 'type', 'status', 'local_status',
        'from_warehouse', 'to_warehouse', 'in_transit_warehouse',
        'notes', 'user_id', 'submitted_at',
        'erp_stock_entry', 'erp_source_entry',
        'erp_sync_status', 'erp_sync_error',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public static function generateTransferNo(string $type): string
    {
        $prefix = $type === 'outgoing' ? 'STO' : 'STI';
        $date = now()->format('Ymd');
        $last = static::where('transfer_no', 'like', "{$prefix}-{$date}-%")
            ->orderByDesc('id')
            ->value('transfer_no');

        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        return "{$prefix}-{$date}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function isOutgoing(): bool
    {
        return $this->type === 'outgoing';
    }

    public function isIncoming(): bool
    {
        return $this->type === 'incoming';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
