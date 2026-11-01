<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockOpname extends Model
{
    protected $fillable = [
        'warehouse_id', 'created_by', 'opname_date', 'status', 'notes',
        'erp_sync_status', 'erp_sync_error', 'erp_entry_issue', 'erp_entry_receipt',
    ];

    protected $casts = [
        'opname_date' => 'date',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'     => 'Draft',
            'submitted' => 'Submitted',
            'cancelled' => 'Dibatalkan',
            default     => $this->status,
        };
    }
}
